<?php

namespace Robuust\Boost\Docs;

use Robuust\Boost\Stack;
use Robuust\Boost\Support\Json;

/**
 * Searches the official Tailwind CSS v4 documentation source, pinned to a commit.
 *
 * Ported from `mcp/craft-twig-rules/lib/tailwind-docs.mjs` in robuust-agents.
 */
final class TailwindDocs
{
    private const REPOSITORY = 'tailwindlabs/tailwindcss.com';

    private const CORE_DOCS = [
        'adding-custom-styles.mdx',
        'colors.mdx',
        'compatibility.mdx',
        'detecting-classes-in-source-files.mdx',
        'functions-and-directives.mdx',
        'hover-focus-and-other-states.mdx',
        'preflight.mdx',
        'responsive-design.mdx',
        'styling-with-utility-classes.mdx',
        'theme.mdx',
        'upgrade-guide.mdx',
    ];

    private const ROUTING_TERMS = [
        'adding-custom-styles.mdx' => ['component', 'custom', 'css', 'layer', 'style'],
        'colors.mdx' => ['color', 'colour', 'oklch', 'palette'],
        'compatibility.mdx' => ['browser', 'compatibility', 'less', 'sass', 'stylus'],
        'detecting-classes-in-source-files.mdx' => ['detect', 'dynamic', 'safelist', 'scan', 'source'],
        'functions-and-directives.mdx' => ['apply', 'config', 'custom-variant', 'directive', 'plugin', 'reference', 'source', 'theme', 'utility', 'variant'],
        'hover-focus-and-other-states.mdx' => ['active', 'disabled', 'focus', 'hover', 'state', 'variant'],
        'preflight.mdx' => ['base', 'normalize', 'preflight', 'reset'],
        'responsive-design.mdx' => ['breakpoint', 'mobile', 'responsive', 'screen'],
        'styling-with-utility-classes.mdx' => ['apply', 'class', 'reuse', 'utility'],
        'theme.mdx' => ['breakpoint', 'design-token', 'namespace', 'theme', 'token', 'variable'],
        'upgrade-guide.mdx' => ['deprecated', 'migration', 'upgrade', 'v3', 'v4'],
    ];

    private const GITHUB_HEADERS = [
        'Accept' => 'application/vnd.github+json',
        'X-GitHub-Api-Version' => '2022-11-28',
    ];

    /** @var array{commit: string, paths: list<string>}|null */
    private ?array $index = null;

    public function __construct(private readonly HttpClient $http = new HttpClient())
    {
    }

    /**
     * The declared version, the installed version from `node_modules` and the version
     * locked in `yarn.lock` or `package-lock.json`, so the check works before an install.
     *
     * @return array{declared_version: string, installed_version: string|null, locked_version: string|null}
     */
    public static function version(Stack $stack): array
    {
        if (!is_file($stack->path('package.json'))) {
            throw new \RuntimeException('Cannot verify Tailwind CSS v4: package.json does not exist.');
        }

        $declared = $stack->npmConstraint('tailwindcss');
        if (null === $declared) {
            throw new \RuntimeException('Cannot verify Tailwind CSS v4: tailwindcss is not declared in package.json.');
        }

        $installedPackage = Json::readFile($stack->path('node_modules/tailwindcss/package.json'));
        $installed = \is_string($installedPackage['version'] ?? null) ? $installedPackage['version'] : null;
        $locked = self::lockedVersion($stack, $declared);

        $checked = preg_replace('/^npm:tailwindcss@/', '', $installed ?? $locked ?? $declared) ?? '';
        if (1 !== preg_match('/^\s*(?:[~^]|[<>]=?|=)?\s*v?(\d+)/', $checked, $matches) || 4 !== (int) $matches[1]) {
            throw new \RuntimeException(\sprintf('search-tailwind-docs only supports Tailwind CSS v4; this project uses %s.', $installed ?? $locked ?? $declared));
        }

        return ['declared_version' => $declared, 'installed_version' => $installed, 'locked_version' => $locked];
    }

    /**
     * The tailwindcss version in `yarn.lock` (Yarn 1 or Berry) or `package-lock.json`, if any.
     *
     * A lock file can hold several tailwindcss entries, such as a transitive v3 next to
     * the project's v4; the entry resolving the declared range wins.
     */
    private static function lockedVersion(Stack $stack, string $declared): ?string
    {
        $yarnLock = $stack->path('yarn.lock');
        if (is_file($yarnLock)) {
            $contents = str_replace("\r\n", "\n", (string) file_get_contents($yarnLock));
            preg_match_all('/^("?tailwindcss@[^\n]*):\n(?:[ \t]+[^\n]*\n)*?[ \t]+version:? "?([^"\s]+)"?/m', $contents, $entries, \PREG_SET_ORDER);

            foreach ($entries as $entry) {
                $descriptors = array_map(static fn (string $descriptor): string => trim($descriptor, ' "'), explode(',', $entry[1]));
                if (array_intersect(['tailwindcss@'.$declared, 'tailwindcss@npm:'.$declared], $descriptors)) {
                    return $entry[2];
                }
            }

            if ([] !== $entries) {
                return $entries[0][2];
            }
        }

        $packageLock = Json::readFile($stack->path('package-lock.json'));
        $version = $packageLock['packages']['node_modules/tailwindcss']['version'] ?? $packageLock['dependencies']['tailwindcss']['version'] ?? null;

        return \is_string($version) ? $version : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function search(Stack $stack, string $query, int $maxResults = 5): array
    {
        if ('' === trim($query)) {
            throw new \InvalidArgumentException('query is required.');
        }

        if ($maxResults < 1 || $maxResults > 10) {
            throw new \InvalidArgumentException('max_results must be an integer between 1 and 10.');
        }

        $version = self::version($stack);
        $index = $this->loadIndex();
        $terms = self::withoutProductName($query);
        $pageScores = $this->pageScores($index['paths'], $terms);
        $selected = $this->selectPaths($index['paths'], $pageScores);

        $results = [];
        $loaded = 0;
        foreach ($selected as $path) {
            $url = 'https://raw.githubusercontent.com/'.self::REPOSITORY.'/'.$index['commit'].'/'.$path;
            $response = $this->http->get($url);
            if (200 !== $response['status']) {
                continue;
            }
            ++$loaded;

            $match = Text::bestMatch($response['body'], $terms, [], 5, 7);
            if (null === $match) {
                continue;
            }

            $slug = basename($path, '.mdx');
            $results[] = [
                'score' => ($pageScores[$path] ?? 0) + $match['score'],
                'title' => $this->titleOf($path, $response['body']),
                'documentation_url' => 'https://tailwindcss.com/docs/'.$slug,
                'source_url' => 'https://github.com/'.self::REPOSITORY.'/blob/'.$index['commit'].'/'.$path.'#L'.$match['line_start'],
                'source_path' => $path,
                'line_start' => $match['line_start'],
                'line_end' => $match['line_end'],
                'excerpt' => $match['excerpt'],
            ];
        }

        if (0 === $loaded) {
            throw new \RuntimeException('No Tailwind documentation pages could be loaded.');
        }

        usort($results, static fn (array $left, array $right): int => [$right['score'], $left['source_path']] <=> [$left['score'], $right['source_path']]);

        return [
            'query' => $query,
            'tailwind' => $version + ['documentation_track' => 'current Tailwind CSS v4'],
            'source' => ['repository' => 'https://github.com/'.self::REPOSITORY, 'commit' => $index['commit']],
            'searched_pages' => \count($selected),
            'results' => array_map(static function (array $result): array {
                unset($result['score']);

                return $result;
            }, \array_slice($results, 0, $maxResults)),
        ];
    }

    /**
     * @return array{commit: string, paths: list<string>}
     */
    private function loadIndex(): array
    {
        if (null !== $this->index) {
            return $this->index;
        }

        $commit = $this->json('https://api.github.com/repos/'.self::REPOSITORY.'/commits/main');
        $sha = \is_string($commit['sha'] ?? null) ? $commit['sha'] : throw new \RuntimeException('Tailwind documentation commit has no sha.');
        $tree = $this->json('https://api.github.com/repos/'.self::REPOSITORY.'/git/trees/'.$sha.'?recursive=1');

        $paths = [];
        foreach (\is_array($tree['tree'] ?? null) ? $tree['tree'] : [] as $entry) {
            if (\is_array($entry) && 'blob' === ($entry['type'] ?? null) && \is_string($entry['path'] ?? null)
                && 1 === preg_match('#^src/docs/[^/]+\.mdx$#', $entry['path'])) {
                $paths[] = $entry['path'];
            }
        }

        return $this->index = ['commit' => $sha, 'paths' => $paths];
    }

    /**
     * @return array<string, mixed>
     */
    private function json(string $url): array
    {
        $response = $this->http->get($url, self::GITHUB_HEADERS);
        if (200 !== $response['status']) {
            throw new \RuntimeException(\sprintf('Tailwind documentation request failed: %d %s', $response['status'], $url));
        }

        return Json::decode($response['body'], $url);
    }

    /**
     * Pages ranked by routing score, plus every core page.
     *
     * @param list<string>       $paths
     * @param array<string, int> $scores routing score per path, from {@see self::pageScores()}
     *
     * @return list<string>
     */
    private function selectPaths(array $paths, array $scores): array
    {
        $ranked = array_keys($scores);
        usort($ranked, static fn (string $left, string $right): int => [$scores[$right], $left] <=> [$scores[$left], $right]);

        $selected = \array_slice($ranked, 0, 12);
        $core = array_filter($paths, static fn (string $path): bool => \in_array(basename($path), self::CORE_DOCS, true));

        return array_values(array_unique(array_merge($selected, $core)));
    }

    /**
     * Routing score per page whose file name or routing terms match the query.
     *
     * @param list<string> $paths
     *
     * @return array<string, int>
     */
    private function pageScores(array $paths, string $query): array
    {
        $queryTokens = Text::tokens($query);
        $scores = [];

        foreach ($paths as $path) {
            $file = basename($path);
            $pathTokens = Text::tokens(substr($file, 0, -4));
            $aliases = self::ROUTING_TERMS[$file] ?? [];
            $score = 0;

            foreach ($queryTokens as $token) {
                if (\in_array($token, $pathTokens, true)) {
                    $score += 8;
                } elseif ([] !== array_filter($pathTokens, static fn (string $pathToken): bool => str_contains($pathToken, $token) || str_contains($token, $pathToken))) {
                    $score += 4;
                } elseif (\in_array($token, $aliases, true)) {
                    $score += 6;
                }
            }

            if ($score > 0) {
                $scores[$path] = $score;
            }
        }

        return $scores;
    }

    /**
     * The query without the words "Tailwind", "Tailwind CSS" or a version after them: every page matches those.
     * Code such as `@tailwind`, `tailwind.config.js` or `@tailwindcss/vite` stays.
     */
    private static function withoutProductName(string $query): string
    {
        $terms = trim((string) preg_replace('/(?<![@\w.\/`-])tailwind(?:\s*css)?(?:\s+v?\d+(?:\.\d+)*)?(?![\w.\/`-])/i', ' ', $query));

        return '' === $terms ? $query : $terms;
    }

    private function titleOf(string $path, string $contents): string
    {
        if (1 === preg_match('/^export const title = ["\'](.+?)["\'];?$/m', $contents, $matches)) {
            return $matches[1];
        }

        return implode(' ', array_map('ucfirst', explode('-', basename($path, '.mdx'))));
    }
}
