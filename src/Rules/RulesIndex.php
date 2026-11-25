<?php

namespace Robuust\Boost\Rules;

use Robuust\Boost\Support\Frontmatter;

/**
 * Renders `.ai/rules/index.md` in Laravel Boost's format.
 *
 * Rule files are `.ai/rules/*.md` and `.ai/rules/boost/*.md` with `paths:`
 * frontmatter. The output matches `php artisan boost:index-rules`, so a Craft 5
 * project keeps the same index after it moves to Craft 6 and Boost.
 */
final class RulesIndex
{
    public const DIRECTORY = '.ai/rules';

    public const INDEX = '.ai/rules/index.md';

    /**
     * @param array<string, string> $planned content that replaces or adds rule files, keyed by path relative to the root
     *
     * @return list<array{file: string, paths: list<string>}> rule files relative to the project root, sorted
     */
    public static function ruleFiles(string $root, array $planned = []): array
    {
        $contents = [];

        foreach ([self::DIRECTORY, self::DIRECTORY.'/boost'] as $directory) {
            foreach (glob($root.'/'.$directory.'/*.md') ?: [] as $path) {
                $contents[$directory.'/'.basename($path)] = (string) file_get_contents($path);
            }
        }

        $contents = array_merge($contents, $planned);
        unset($contents[self::INDEX]);

        $files = [];
        foreach ($contents as $file => $content) {
            $parsed = Frontmatter::parse($content);
            $paths = $parsed['data']['paths'] ?? [];
            $paths = \is_array($paths) ? $paths : [$paths];

            $files[] = [
                'file' => $file,
                'paths' => array_values(array_filter($paths, static fn (string $glob): bool => '' !== $glob)),
            ];
        }

        usort($files, static fn (array $left, array $right): int => strcmp($left['file'], $right['file']));

        return $files;
    }

    /**
     * Rule files the index skips because they declare no paths.
     *
     * @param array<string, string> $planned see ruleFiles()
     *
     * @return list<string>
     */
    public static function unindexed(string $root, array $planned = []): array
    {
        return array_values(array_map(
            static fn (array $rule): string => $rule['file'],
            array_filter(self::ruleFiles($root, $planned), static fn (array $rule): bool => [] === $rule['paths']),
        ));
    }

    /**
     * @param array<string, string> $planned see ruleFiles()
     */
    public static function render(string $root, array $planned = []): string
    {
        $rows = [];

        foreach (self::ruleFiles($root, $planned) as $rule) {
            if ([] === $rule['paths']) {
                continue;
            }
            $rows[] = '| '.implode(', ', $rule['paths']).' | '.$rule['file'].' |';
        }

        $table = [] === $rows
            ? 'No rules recorded yet.'
            : "| Applies to | Rule file |\n| --- | --- |\n".implode("\n", $rows);

        return "# Project Rules Index\n\n"
            ."Before planning or editing, find the row whose globs match the file's path and read that rule file.\n\n"
            .$table."\n";
    }
}
