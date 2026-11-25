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

    /** @var array<string, string> agent => MCP config file */
    public const MCP_CONFIGS = [
        'codex' => '.codex/config.toml',
        'claude_code' => '.mcp.json',
    ];

    /** A `[mcp_servers.robuust]` table header, bare or quoted, with an optional trailing comment. */
    private const CODEX_TABLE = '/^\s*\[\s*mcp_servers\s*\.\s*(?:robuust|"robuust"|\'robuust\')\s*\]\s*(?:#.*)?$/';

    /** A header of a `[mcp_servers.robuust.*]` sub-table, such as `env` or `tools.<name>`. */
    private const CODEX_SUBTABLE = '/^\s*\[\s*mcp_servers\s*\.\s*(?:robuust|"robuust"|\'robuust\')\s*\./';

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

        foreach (array_keys(self::SKILL_DIRECTORIES) as $agent) {
            if (!$config->uses($agent)) {
                self::planRetire($stack, $changes, $agent);
            }
        }

        return $changes;
    }

    /**
     * Whether an earlier Craft 5 sync left generated skills behind for any agent.
     */
    public static function hasOutput(Stack $stack): bool
    {
        $claudeSkills = $stack->path(self::SKILL_DIRECTORIES['claude_code']);
        if (is_link($claudeSkills) && self::CLAUDE_SKILLS_LINK === readlink($claudeSkills)) {
            return true;
        }

        foreach (self::SKILL_DIRECTORIES as $directory) {
            if (is_file($stack->path($directory.'/'.self::MANIFEST))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Plan the removal of what a sync generated for an agent: the skills in its manifest (or the
     * `.claude/skills` symlink) and the `robuust` MCP entry. Hand-written skills, other MCP servers
     * and other settings stay.
     */
    public static function planRetire(Stack $stack, Changeset $changes, string $agent): void
    {
        $directory = self::SKILL_DIRECTORIES[$agent];
        $path = $stack->path($directory);

        if (is_link($path)) {
            if (self::CLAUDE_SKILLS_LINK === readlink($path)) {
                $changes->delete($directory);
            }
        } elseif (is_file($path.'/'.self::MANIFEST)) {
            foreach (self::manifestSkills($path) as $name) {
                $changes->delete($directory.'/'.$name);
            }
            $changes->delete($directory.'/'.self::MANIFEST);
        }

        if ('codex' === $agent) {
            self::planRemoveCodexMcp($changes);
        } else {
            self::planRemoveClaudeMcp($changes);
        }
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

        // Claude Code ignores imports inside fenced code blocks, so an example there does not count.
        $active = (string) preg_replace('/^[ \t]*(`{3,}|~{3,}).*?^[ \t]*\1[ \t]*$/ms', '', $existing);
        if (1 === preg_match('/^@AGENTS\.md\s*$/m', $active)) {
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
        $previous = self::manifestSkills($stack->path($directory));

        // A skill directory the sync did not write may hold hand-written files; never take it over.
        $own = array_values(array_filter(
            array_keys($skills),
            static fn (string $name): bool => !\in_array($name, $previous, true) && file_exists($stack->path($directory.'/'.$name)),
        ));
        if ([] !== $own) {
            throw new \RuntimeException(\sprintf('%s holds skills that robuust-agents did not write but would overwrite: %s. Rename or remove them, then run the sync again. Nothing was written.', $directory, implode(', ', $own)));
        }

        foreach ($previous as $name) {
            if (!isset($skills[$name])) {
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
            $own = array_values(array_diff(Filesystem::entries($path), [self::MANIFEST, ...self::manifestSkills($path)]));

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
        $path = self::MCP_CONFIGS['codex'];
        $existing = $changes->contents($path) ?? '';
        $quoted = array_map(static fn (string $arg): string => json_encode($arg, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $args);
        $owned = [
            'command = '.json_encode($command, JSON_THROW_ON_ERROR),
            'args = ['.implode(', ', $quoted).']',
        ];

        $lines = '' === $existing ? [] : explode("\n", rtrim($existing, "\n"));
        $range = self::codexTable($lines, self::CODEX_TABLE);

        if (null === $range) {
            $table = '[mcp_servers.'.self::MCP_SERVER."]\n".implode("\n", $owned)."\n";
            $changes->put($path, ([] === $lines ? '' : implode("\n", $lines)."\n\n").$table);

            return;
        }

        // Replace only the keys the sync owns; keep the header and every other key, such as `env`.
        [$start, $end] = $range;
        $body = self::withoutKeys(\array_slice($lines, $start + 1, $end - $start - 1), ['command', 'args']);
        $lines = [...\array_slice($lines, 0, $start + 1), ...$owned, ...$body, ...\array_slice($lines, $end)];

        $changes->put($path, implode("\n", $lines)."\n");
    }

    /**
     * Remove the `[mcp_servers.robuust]` table and its sub-tables from the Codex config.
     */
    private static function planRemoveCodexMcp(Changeset $changes): void
    {
        $path = self::MCP_CONFIGS['codex'];
        $existing = $changes->contents($path);
        if (null === $existing) {
            return;
        }

        $lines = explode("\n", rtrim($existing, "\n"));
        $removed = false;
        foreach ([self::CODEX_TABLE, self::CODEX_SUBTABLE] as $pattern) {
            while (null !== $range = self::codexTable($lines, $pattern)) {
                array_splice($lines, $range[0], $range[1] - $range[0]);
                $removed = true;
            }
        }

        if (!$removed) {
            return;
        }

        $content = trim((string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));
        if ('' === $content) {
            $changes->delete($path);
        } else {
            $changes->put($path, $content."\n");
        }
    }

    /**
     * Remove the `robuust` server from `.mcp.json`.
     */
    private static function planRemoveClaudeMcp(Changeset $changes): void
    {
        $path = self::MCP_CONFIGS['claude_code'];
        $existing = $changes->contents($path);
        $data = null === $existing ? null : json_decode($existing);
        if (!$data instanceof \stdClass || !($data->mcpServers ?? null) instanceof \stdClass || !isset($data->mcpServers->{self::MCP_SERVER})) {
            return;
        }

        unset($data->mcpServers->{self::MCP_SERVER});

        if ([] === get_object_vars($data->mcpServers) && 1 === \count(get_object_vars($data))) {
            $changes->delete($path);
        } else {
            $changes->put($path, Json::encode($data));
        }
    }

    /**
     * The first table whose header matches, as [header line, line after its last line].
     *
     * @param list<string> $lines
     *
     * @return array{0: int, 1: int}|null
     */
    private static function codexTable(array $lines, string $header): ?array
    {
        foreach ($lines as $start => $line) {
            if (1 !== preg_match($header, $line)) {
                continue;
            }

            for ($end = $start + 1; $end < \count($lines); ++$end) {
                if (str_starts_with(ltrim($lines[$end]), '[')) {
                    break;
                }
            }

            return [$start, $end];
        }

        return null;
    }

    /**
     * Drop the given keys from a table body, including values that span several lines.
     *
     * @param list<string> $lines
     * @param list<string> $keys
     *
     * @return list<string>
     */
    private static function withoutKeys(array $lines, array $keys): array
    {
        $pattern = '/^\s*(?:'.implode('|', array_map(static fn (string $key): string => preg_quote($key, '/'), $keys)).')\s*=/';
        $kept = [];
        $depth = 0;

        foreach ($lines as $line) {
            if ($depth > 0 || 1 === preg_match($pattern, $line)) {
                // Count brackets outside strings, so a multi-line array is skipped to its end.
                $bare = (string) preg_replace('/"(?:\\\.|[^"\\\])*"|\'[^\']*\'/', '', $line);
                $depth = max(0, $depth + substr_count($bare, '[') - substr_count($bare, ']'));

                continue;
            }

            $kept[] = $line;
        }

        return $kept;
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

    /**
     * The skill names a sync recorded in a directory's manifest.
     *
     * @return list<string>
     */
    private static function manifestSkills(string $directory): array
    {
        $manifest = Json::readFile($directory.'/'.self::MANIFEST);
        $skills = \is_array($manifest['skills'] ?? null) ? $manifest['skills'] : [];

        return array_values(array_filter($skills, static fn (mixed $name): bool => \is_string($name) && self::isSafeName($name)));
    }

    private static function isSafeName(string $name): bool
    {
        return 1 === preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $name);
    }
}
