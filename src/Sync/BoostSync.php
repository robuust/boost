<?php

namespace Robuust\Boost\Sync;

use Robuust\Boost\Rules\LegacyRules;
use Robuust\Boost\Rules\RulesIndex;
use Robuust\Boost\Stack;
use Robuust\Boost\Support\Json;

/**
 * Runs `php artisan boost:update` behind the package guard.
 */
final class BoostSync
{
    public const CONFIG = 'boost.json';

    /**
     * Files and directories Boost writes, per agent: the default guidelines, MCP config and skills
     * paths of the agent classes in `laravel/boost` (`src/Install/Agents`). Where an agent picks a
     * path with `file_exists()`, every candidate is listed: Claude Code writes its guidelines to
     * `CLAUDE.md` or `AGENTS.md`, and OpenCode its MCP config to `opencode.jsonc` or `opencode.json`.
     * The other agents use fixed defaults. `sync --check` refuses agents missing from this list
     * and projects that override these paths in `config/boost.php`.
     *
     * @var array<string, list<string>>
     */
    public const AGENT_PATHS = [
        'amp' => ['AGENTS.md', '.amp/settings.json', '.agents/skills'],
        'antigravity' => ['AGENTS.md', '.agents/mcp_config.json', '.agents/skills'],
        'claude_code' => ['CLAUDE.md', 'AGENTS.md', '.mcp.json', '.claude/skills'],
        'codex' => ['AGENTS.md', '.codex/config.toml', '.agents/skills'],
        'copilot' => ['AGENTS.md', '.vscode/mcp.json', '.github/skills'],
        'cursor' => ['AGENTS.md', '.cursor/mcp.json', '.cursor/skills'],
        'factory' => ['AGENTS.md', '.factory/mcp.json', '.factory/skills'],
        'grok_build' => ['AGENTS.md', '.grok/config.toml', '.grok/skills'],
        'junie' => ['AGENTS.md', '.junie/mcp/mcp.json', '.junie/skills'],
        'kiro' => ['AGENTS.md', '.kiro/settings/mcp.json', '.kiro/skills'],
        'opencode' => ['AGENTS.md', 'opencode.jsonc', 'opencode.json', '.agents/skills'],
        'pi' => ['AGENTS.md', '.pi/mcp.json', '.pi/skills'],
        'zed' => ['AGENTS.md', '.zed/settings.json', '.agents/skills'],
    ];

    /** Paths the Craft 5 sync writes, which the first Boost sync after a move to Craft 6 retires. */
    public const CRAFT5_PATHS = ['AGENTS.md', '.agents/skills', '.claude/skills', '.mcp.json', '.codex/config.toml'];

    /** The `config/boost.php` keys that move an agent's output away from the paths above. */
    private const PATH_OVERRIDES = ['guidelines_path', 'skills_path', 'mcp_config_path'];

    /** The block Boost writes to `AGENTS.md`. */
    private const BOOST_BLOCK = '<laravel-boost-guidelines>';

    public function __construct(private readonly ProcessRunner $runner = new ProcessRunner())
    {
    }

    /**
     * @return array<string, mixed>
     */
    public static function config(Stack $stack): array
    {
        $config = Json::readFile($stack->path(self::CONFIG));

        if (null === $config) {
            throw new \RuntimeException('boost.json is missing. Run `php artisan boost:install` first, selecting Codex, Claude Code and robuust/boost.');
        }

        return $config;
    }

    /**
     * @return list<string>
     */
    public static function packages(Stack $stack): array
    {
        $packages = self::config($stack)['packages'] ?? [];

        return \is_array($packages) ? array_values(array_filter($packages, 'is_string')) : [];
    }

    /**
     * Run Boost's update.
     *
     * @return int the exit code of `boost:update`
     */
    public function update(Stack $stack): int
    {
        self::migrateRules($stack);

        // After a move from Craft 5, Boost takes over: the Craft 5 block, skills and MCP entries go,
        // but only once Boost has written its replacement. Otherwise everything is put back.
        $cutover = Craft5Sync::hasOutput($stack) || self::hasCraft5Block($stack);
        $before = $cutover ? Snapshot::capture($stack->root(), self::CRAFT5_PATHS) : null;
        if ($cutover) {
            self::retireCraft5($stack);
        }

        // Boost registers its commands only in a local or debug environment, and never in tests.
        $exit = $this->runner->run(
            [PHP_BINARY, 'artisan', 'boost:update', '--no-interaction'],
            $stack->root(),
            ['APP_ENV' => 'local'],
        );

        if (null !== $before && (0 !== $exit || !self::hasBoostBlock($stack))) {
            $before->restore();

            if (0 === $exit) {
                throw new \RuntimeException('php artisan boost:update wrote no <laravel-boost-guidelines> block to AGENTS.md, so the Craft 5 agent files were put back. Check the agents and guidelines in boost.json, then run the sync again.');
            }
        }

        return $exit;
    }

    /**
     * Run Boost's update, report what it changed and put every file back.
     *
     * @return array{exit: int, changes: list<string>}
     */
    public function check(Stack $stack): array
    {
        $unknown = self::unknownAgents($stack);
        if ([] !== $unknown) {
            throw new \RuntimeException(\sprintf('sync --check cannot check the agent(s) %s in boost.json: robuust/boost does not know which files they write, so nothing was run. Known agents: %s.', implode(', ', $unknown), implode(', ', array_keys(self::AGENT_PATHS))));
        }

        $overrides = self::pathOverrides($stack);
        if ([] !== $overrides) {
            throw new \RuntimeException(\sprintf('sync --check cannot check this project: config/boost.php sets %s, and robuust/boost cannot restore files Boost writes outside its default paths, so nothing was run.', implode(', ', $overrides)));
        }

        $before = Snapshot::capture($stack->root(), $this->paths($stack));

        try {
            $exit = $this->update($stack);
            $after = Snapshot::capture($stack->root(), $this->paths($stack));
        } finally {
            $before->restore();
        }

        return ['exit' => $exit, 'changes' => $before->diff($after)];
    }

    /**
     * Move legacy `craft_twig_rules` globs into the rule files and rebuild the index in Boost's
     * format. Boost leaves an index alone unless it manages rules itself.
     */
    private static function migrateRules(Stack $stack): void
    {
        $legacy = LegacyRules::migration($stack->root());
        foreach ($legacy as $path => $content) {
            file_put_contents($stack->path($path), $content);
        }

        $index = $stack->path(RulesIndex::INDEX);
        if ([] !== $legacy || (is_file($index) && str_starts_with(ltrim((string) file_get_contents($index)), LegacyRules::HEADING))) {
            file_put_contents($index, RulesIndex::render($stack->root(), $legacy));
        }
    }

    /**
     * Remove what the Craft 5 sync wrote: Boost's own block, skills and MCP server replace it.
     */
    private static function retireCraft5(Stack $stack): void
    {
        self::removeCraft5Block($stack);

        $changes = new Changeset($stack->root());
        foreach (array_keys(Craft5Sync::SKILL_DIRECTORIES) as $agent) {
            Craft5Sync::planRetire($stack, $changes, $agent);
        }
        $changes->apply();
    }

    private static function hasCraft5Block(Stack $stack): bool
    {
        return null !== self::craft5Block(self::agentsMd($stack));
    }

    private static function hasBoostBlock(Stack $stack): bool
    {
        return str_contains(self::agentsMd($stack), self::BOOST_BLOCK);
    }

    /**
     * The `config/boost.php` keys that move agent output, which `sync --check` cannot follow.
     *
     * @return list<string>
     */
    private static function pathOverrides(Stack $stack): array
    {
        $path = $stack->path('config/boost.php');
        $source = is_file($path) ? (string) file_get_contents($path) : '';

        return array_values(array_filter(
            self::PATH_OVERRIDES,
            static fn (string $key): bool => 1 === preg_match('/[\'"]'.$key.'[\'"]\s*=>/', $source),
        ));
    }

    private static function agentsMd(Stack $stack): string
    {
        $path = $stack->path('AGENTS.md');

        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    /**
     * @return array{0: string, 1: int}|null the Craft 5 block and its offset
     */
    private static function craft5Block(string $content): ?array
    {
        $pattern = '/'.preg_quote(Craft5Sync::BLOCK_OPEN, '/').'.*?'.preg_quote(Craft5Sync::BLOCK_CLOSE, '/').'/s';

        return 1 === preg_match($pattern, $content, $match, PREG_OFFSET_CAPTURE) ? $match[0] : null;
    }

    /**
     * Remove the block the Craft 5 sync writes to `AGENTS.md`. After a move to Craft 6,
     * Boost's own block carries these guidelines, so the old block would repeat them.
     */
    private static function removeCraft5Block(Stack $stack): void
    {
        $path = $stack->path('AGENTS.md');
        $content = self::agentsMd($stack);
        $block = self::craft5Block($content);

        if (null === $block) {
            return;
        }

        [$text, $offset] = $block;
        $before = rtrim(substr($content, 0, $offset));
        $after = ltrim(substr($content, $offset + \strlen($text)));
        $content = '' === $after ? $before : ('' === $before ? '' : $before."\n\n").$after;

        file_put_contents($path, '' === $content ? '' : rtrim($content)."\n");
    }

    /**
     * Agents in boost.json whose output paths are unknown.
     *
     * @return list<string>
     */
    public static function unknownAgents(Stack $stack): array
    {
        return array_values(array_filter(
            self::agents($stack),
            static fn (string $agent): bool => !\array_key_exists($agent, self::AGENT_PATHS),
        ));
    }

    /**
     * The paths Boost may write for the agents in boost.json.
     *
     * @return list<string>
     */
    public function paths(Stack $stack): array
    {
        // Boost's RuleRepository rewrites or deletes `.ai/rules/index.md` with the managed rules,
        // and update() moves legacy rule globs into the rule files' frontmatter.
        $paths = [self::CONFIG, 'AGENTS.md', '.ai/rules', '.ai/skills', ...self::CRAFT5_PATHS];

        foreach (self::agents($stack) as $agent) {
            $paths = array_merge($paths, self::AGENT_PATHS[$agent] ?? []);
        }

        return array_values(array_unique($paths));
    }

    /**
     * @return list<string>
     */
    private static function agents(Stack $stack): array
    {
        $agents = self::config($stack)['agents'] ?? [];

        return \is_array($agents) ? array_values(array_filter($agents, 'is_string')) : [];
    }
}
