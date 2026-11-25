<?php

namespace Robuust\Boost\Rules;

use Robuust\Boost\Support\Frontmatter;

/**
 * Migrates rules recorded by the legacy `craft_twig_rules` MCP to Boost's rule format.
 *
 * That MCP kept each rule's glob only in a `# Project rules` table in `.ai/rules/index.md`;
 * its rule files hold `## Title` sections without `paths:` frontmatter. Boost's index is
 * built from that frontmatter, so regenerating the index without this migration would
 * drop every legacy rule from it.
 */
final class LegacyRules
{
    public const HEADING = '# Project rules';

    private const ROW = '/^\| `([^`]+)` \| \[([^\]]+)\]\(([^)]+)\) \| (.+) \|$/m';

    /**
     * Rule files rewritten with the globs the legacy index lists for them.
     *
     * Files that already declare `paths:` are left alone. A file the index lists but that is
     * missing is recreated from the rule text the index keeps.
     *
     * @return array<string, string> path relative to the project root => new content
     */
    public static function migration(string $root): array
    {
        $index = @file_get_contents($root.'/'.RulesIndex::INDEX);
        if (false === $index || !str_starts_with(ltrim($index), self::HEADING."\n")) {
            return [];
        }

        preg_match_all(self::ROW, $index, $rows, PREG_SET_ORDER);

        /** @var array<string, array{globs: list<string>, rules: list<array{title: string, note: string}>}> $files */
        $files = [];
        foreach ($rows as [, $glob, $title, $file, $note]) {
            $name = preg_replace('#^\./#', '', $file);
            if (1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.md$/', (string) $name) || 'index.md' === $name) {
                continue;
            }
            $files[$name]['globs'] ??= [];
            $files[$name]['rules'] ??= [];
            if (!\in_array($glob, $files[$name]['globs'], true)) {
                $files[$name]['globs'][] = $glob;
            }
            $files[$name]['rules'][] = ['title' => $title, 'note' => trim($note)];
        }

        $migration = [];
        foreach ($files as $name => $entry) {
            $relative = RulesIndex::DIRECTORY.'/'.$name;
            $existing = @file_get_contents($root.'/'.$relative);

            if (false !== $existing && null !== Frontmatter::parse($existing)) {
                continue;
            }

            $body = false === $existing || '' === trim($existing)
                ? implode("\n", array_map(static fn (array $rule): string => "## {$rule['title']}\n\n{$rule['note']}\n", $entry['rules']))
                : trim($existing)."\n";

            $migration[$relative] = self::frontmatter($entry['globs'])."\n".'# '.self::area($name)."\n\n".$body;
        }

        ksort($migration);

        return $migration;
    }

    /**
     * @param list<string> $globs
     */
    private static function frontmatter(array $globs): string
    {
        $lines = array_map(static fn (string $glob): string => "  - '".str_replace("'", "''", $glob)."'", $globs);

        return "---\npaths:\n".implode("\n", $lines)."\n---\n";
    }

    private static function area(string $name): string
    {
        return ucfirst(str_replace(['-', '_'], ' ', basename($name, '.md')));
    }
}
