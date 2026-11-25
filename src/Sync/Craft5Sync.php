<?php

namespace Robuust\Boost\Sync;

use Robuust\Boost\Rules\LegacyRules;
use Robuust\Boost\Rules\RulesIndex;
use Robuust\Boost\Stack;
use Robuust\Boost\Support\Frontmatter;
use Robuust\Boost\Support\Json;

/**
 * Writes the agent files for Craft 5 projects, which cannot run Laravel Boost.
 *
 * - a managed block in `AGENTS.md` with its own tag; after a move to Craft 6, the Boost
 *   route removes it because Boost's block then carries these guidelines;
 * - a `CLAUDE.md` that imports `@AGENTS.md`;
 * - `.ai/rules/index.md` in Boost's format, after moving legacy `craft_twig_rules` globs into
 *   `paths:` frontmatter;
 * - skills in `.agents/skills` (Codex) with a manifest, so skills that are no longer shipped
 *   get removed; with Claude Code as well, `.claude/skills` is a symlink to that directory,
 *   so the project holds one copy;
 * - the `robuust` MCP server in `.codex/config.toml` and `.mcp.json`.
 */
final class Craft5Sync
{
    public const BLOCK_OPEN = '<robuust-agents-guidelines>';

    public const BLOCK_CLOSE = '</robuust-agents-guidelines>';

    public const MANIFEST = '.robuust-agents-manifest.json';

    public const MCP_SERVER = 'robuust';

    /** @var array<string, string> agent => skills directory */
    public const SKILL_DIRECTORIES = [
        'codex' => '.agents/skills',
        'claude_code' => '.claude/skills',
    ];

    /** Where `.claude/skills` points when both agents are configured, relative to `.claude`. */
    public const CLAUDE_SKILLS_LINK = '../.agents/skills';

    public function __construct(private readonly string $packageRoot)
    {
    }

    /**
     * Plan every file the sync writes. Does not touch the disk.
     */
    public function plan(Stack $stack, Craft5Config $config): Changeset
    {
        $changes = new Changeset($stack->root());

        if (!$config->exists) {
            $changes->put(Craft5Config::FILE, $config->toJson());
        }

        $this->planGuidelines($changes);

        if ($config->uses('claude_code')) {
            $this->planClaudeImport($changes);
        }

        $legacy = LegacyRules::migration($stack->root());
        foreach ($legacy as $path => $content) {
            $changes->put($path, $content);
        }
        $changes->put(RulesIndex::INDEX, RulesIndex::render($stack->root(), $legacy));

        $skills = $this->skills($stack, $config);
        $linked = $config->uses('codex') && $config->uses('claude_code');
        foreach ($config->agents as $agent) {
            if ($linked && 'claude_code' === $agent) {
                continue;
            }
            $this->planSkills($stack, $changes, self::SKILL_DIRECTORIES[$agent], $skills);
        }

        if ($linked) {
            $this->planClaudeSkillsLink($stack, $changes);
        } elseif ($config->uses('claude_code') && is_link($stack->path(self::SKILL_DIRECTORIES['claude_code']))) {
            // Codex was deselected: Claude Code gets its own directory again.
            $changes->delete(self::SKILL_DIRECTORIES['claude_code']);
        }

        $command = 'php';
        $args = [$stack->binDir().'/robuust-agents', 'mcp'];

        if ($config->uses('codex')) {
            $this->planCodexMcp($changes, $command, $args);
        }

        if ($config->uses('claude_code')) {
            $this->planClaudeMcp($changes, $command, $args);
        }

        return $changes;
    }

    /**
     * The composed guidelines that go inside the managed block.
     */
    public function guidelines(): string
    {
        $files = ['robuust/boost/core' => $this->packageRoot.'/resources/boost/guidelines/core.md'];

        foreach (glob($this->packageRoot.'/resources/craft5/guidelines/*.md') ?: [] as $path) {
            $files['robuust/boost/craft5/'.basename($path, '.md')] = $path;
        }

        $sections = [];
        foreach ($files as $key => $path) {
            $content = trim((string) file_get_contents($path));
            if ('' !== $content) {
                $sections[] = "\n=== {$key} rules ===\n\n".$content;
            }
        }

        return trim(implode("\n\n", $sections));
    }

    /**
     * Skills to install: the Craft 5 skills, then those of each selected package.
     *
     * @return array<string, string> skill name => source directory
     */
    public function skills(Stack $stack, Craft5Config $config): array
    {
        $skills = $this->skillsIn($this->packageRoot.'/resources/craft5/skills');

        foreach ($config->packages as $package) {
            $path = Guard::installedPath($stack, $package);
            if (null !== $path) {
                $skills = array_merge($skills, $this->skillsIn($path.'/resources/boost/skills'));
            }
        }

        ksort($skills);

        return $skills;
    }

    private function planGuidelines(Changeset $changes): void
    {
        $block = self::BLOCK_OPEN."\n".$this->guidelines()."\n\n".self::BLOCK_CLOSE;
        $existing = $changes->contents('AGENTS.md') ?? '';
        $pattern = '/'.preg_quote(self::BLOCK_OPEN, '/').'.*?'.preg_quote(self::BLOCK_CLOSE, '/').'/s';

        if (1 === preg_match($pattern, $existing)) {
            $content = (string) preg_replace_callback($pattern, static fn (): string => $block, $existing, 1);
        } else {
            $existing = rtrim($existing);
            $content = ('' === $existing ? '' : $existing."\n\n").$block;
        }

        if (!str_ends_with($content, "\n")) {
            $content .= "\n";
        }

        $changes->put('AGENTS.md', $content);
    }

    private function planClaudeImport(Changeset $changes): void
    {
        $existing = $changes->contents('CLAUDE.md');

        if (null === $existing || '' === trim($existing)) {
            $changes->put('CLAUDE.md', "@AGENTS.md\n");

            return;
        }

        if (1 === preg_match('/^@AGENTS\.md\s*$/m', $existing)) {
            return;
        }

        $changes->put('CLAUDE.md', "@AGENTS.md\n\n".$existing);
    }

    /**
     * @param array<string, string> $skills
     */
    private function planSkills(Stack $stack, Changeset $changes, string $directory, array $skills): void
    {
        $manifestPath = $directory.'/'.self::MANIFEST;
        $previous = [];
        $manifest = Json::readFile($stack->path($manifestPath));
        if (\is_array($manifest['skills'] ?? null)) {
            $previous = array_filter($manifest['skills'], 'is_string');
        }

        foreach ($previous as $name) {
            if (!isset($skills[$name]) && self::isSafeName($name)) {
                $changes->delete($directory.'/'.$name);
            }
        }

        foreach ($skills as $name => $source) {
            $target = $directory.'/'.$name;
            $sourceFiles = Filesystem::files($source);

            foreach ($sourceFiles as $file) {
                $changes->put($target.'/'.$file, (string) file_get_contents($source.'/'.$file));
            }

            foreach (array_diff(Filesystem::files($stack->path($target)), $sourceFiles) as $stale) {
                $changes->delete($target.'/'.$stale);
            }
        }

        $changes->put($manifestPath, Json::encode(['skills' => array_keys($skills)]));
    }

    /**
     * Point `.claude/skills` at `.agents/skills`. A directory written by an earlier sync is replaced;
     * one that holds skills the sync did not write stops the sync, so nothing hand-written is lost.
     */
    private function planClaudeSkillsLink(Stack $stack, Changeset $changes): void
    {
        $directory = self::SKILL_DIRECTORIES['claude_code'];
        $path = $stack->path($directory);

        if (is_dir($path) && !is_link($path)) {
            $manifest = Json::readFile($path.'/'.self::MANIFEST);
            $generated = \is_array($manifest['skills'] ?? null) ? array_filter($manifest['skills'], 'is_string') : [];
            $own = array_values(array_diff(Filesystem::entries($path), [self::MANIFEST, ...$generated]));

            if ([] !== $own) {
                throw new \RuntimeException(\sprintf('%s holds skills that robuust-agents did not write: %s. Move them to %s, then run the sync again. Nothing was written.', $directory, implode(', ', $own), self::SKILL_DIRECTORIES['codex']));
            }
        }

        $changes->link($directory, self::CLAUDE_SKILLS_LINK);
    }

    /**
     * @param list<string> $args
     */
    private function planCodexMcp(Changeset $changes, string $command, array $args): void
    {
        $path = '.codex/config.toml';
        $existing = $changes->contents($path) ?? '';
        $header = '[mcp_servers.'.self::MCP_SERVER.']';
        $quoted = array_map(static fn (string $arg): string => json_encode($arg, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $args);
        $table = $header."\n"
            .'command = '.json_encode($command, JSON_THROW_ON_ERROR)."\n"
            .'args = ['.implode(', ', $quoted).']'."\n";

        $lines = '' === $existing ? [] : explode("\n", rtrim($existing, "\n"));
        $start = array_search($header, array_map('trim', $lines), true);

        if (false === $start) {
            $content = ([] === $lines ? '' : implode("\n", $lines)."\n\n").$table;
        } else {
            $end = \count($lines);
            for ($i = $start + 1; $i < \count($lines); ++$i) {
                if (str_starts_with(ltrim($lines[$i]), '[')) {
                    $end = $i;

                    break;
                }
            }
            $before = \array_slice($lines, 0, $start);
            $after = \array_slice($lines, $end);
            $content = ([] === $before ? '' : implode("\n", $before)."\n").$table
                .([] === $after ? '' : "\n".implode("\n", $after)."\n");
        }

        $changes->put($path, $content);
    }

    /**
     * @param list<string> $args
     */
    private function planClaudeMcp(Changeset $changes, string $command, array $args): void
    {
        $path = '.mcp.json';
        $existing = $changes->contents($path);
        $data = new \stdClass();

        if (null !== $existing && '' !== trim($existing)) {
            $data = json_decode($existing);
            if (!$data instanceof \stdClass) {
                throw new \RuntimeException('.mcp.json must contain a JSON object.');
            }
        }

        $server = (object) ['command' => $command, 'args' => $args];

        if (!isset($data->mcpServers) || !$data->mcpServers instanceof \stdClass) {
            $data->mcpServers = new \stdClass();
        } elseif (isset($data->mcpServers->{self::MCP_SERVER}) && $data->mcpServers->{self::MCP_SERVER} == $server) {
            return;
        }

        $data->mcpServers->{self::MCP_SERVER} = $server;
        $changes->put($path, Json::encode($data));
    }

    /**
     * @return array<string, string>
     */
    private function skillsIn(string $directory): array
    {
        $skills = [];

        foreach (Filesystem::directories($directory) as $dir) {
            $skillFile = $directory.'/'.$dir.'/SKILL.md';
            if (!is_file($skillFile)) {
                continue;
            }
            $parsed = Frontmatter::parse((string) file_get_contents($skillFile));
            $name = $parsed['data']['name'] ?? null;
            $name = \is_string($name) && '' !== $name ? $name : $dir;
            if (self::isSafeName($name)) {
                $skills[$name] = $directory.'/'.$dir;
            }
        }

        return $skills;
    }

    private static function isSafeName(string $name): bool
    {
        return 1 === preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $name);
    }
}
