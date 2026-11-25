<?php

namespace Robuust\Boost\Sync;

use Robuust\Boost\Rules\LegacyRules;
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
     * The other agents use fixed defaults. Paths a project overrides in
     * `config/boost.php` are not followed; `sync --check` refuses agents missing from this list.
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
        self::removeCraft5Block($stack);
        foreach (LegacyRules::migration($stack->root()) as $path => $content) {
            file_put_contents($stack->path($path), $content);
        }

        // Boost registers its commands only in a local or debug environment, and never in tests.
        return $this->runner->run(
            [PHP_BINARY, 'artisan', 'boost:update', '--no-interaction'],
            $stack->root(),
            ['APP_ENV' => 'local'],
        );
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
     * Remove the block the Craft 5 sync writes to `AGENTS.md`. After a move to Craft 6,
     * Boost's own block carries these guidelines, so the old block would repeat them.
     */
    private static function removeCraft5Block(Stack $stack): void
    {
        $path = $stack->path('AGENTS.md');
        $content = is_file($path) ? (string) file_get_contents($path) : '';
        $pattern = '/'.preg_quote(Craft5Sync::BLOCK_OPEN, '/').'.*?'.preg_quote(Craft5Sync::BLOCK_CLOSE, '/').'/s';

        if (1 !== preg_match($pattern, $content, $match, PREG_OFFSET_CAPTURE)) {
            return;
        }

        $before = rtrim(substr($content, 0, $match[0][1]));
        $after = ltrim(substr($content, $match[0][1] + \strlen($match[0][0])));
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
        $paths = [self::CONFIG, 'AGENTS.md', '.ai/rules', '.ai/skills'];

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
