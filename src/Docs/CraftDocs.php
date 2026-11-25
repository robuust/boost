<?php

namespace Robuust\Boost\Docs;

/**
 * Searches the versioned Craft CMS docs index published by `robuust/craft-llms`.
 *
 * Layout under the base URL: `{major}/index.json`, `{major}/pages/{path}` and
 * `{major}/llms-full.txt`. Pages are keyed by their source path; titles may
 * repeat. When the requested major has no index, the search says so and never
 * serves another major.
 */
final class CraftDocs
{
    public const DEFAULT_BASE_URL = 'https://craft-llms.netlify.app';

    public const ENV = 'ROBUUST_DOCS_BASE_URL';

    /** @var array<string, array<string, mixed>|null> */
    private array $indexes = [];

    /** @var array<string, list<array{title: string, body: string}>> */
    private array $fullTexts = [];

    public function __construct(
        private readonly string $baseUrl = self::DEFAULT_BASE_URL,
        private readonly HttpClient $http = new HttpClient(),
    ) {
    }

    /**
     * The base URL: the environment variable wins over config, config over the default.
     */
    public static function baseUrl(?string $configured = null): string
    {
        $env = getenv(self::ENV);

        if (\is_string($env) && '' !== $env) {
            return rtrim($env, '/');
        }

        return rtrim(null !== $configured && '' !== $configured ? $configured : self::DEFAULT_BASE_URL, '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function search(string $query, int $major, int $maxResults = 5): array
    {
        if ('' === trim($query)) {
            throw new \InvalidArgumentException('query is required.');
        }

        if ($maxResults < 1 || $maxResults > 10) {
            throw new \InvalidArgumentException('max_results must be an integer between 1 and 10.');
        }

        $requested = (string) $major;
        $indexUrl = $this->baseUrl.'/'.$requested.'/index.json';
        $index = $this->index($requested, $indexUrl);

        $response = [
            'query' => $query,
            'requested_major' => $requested,
            'served_major' => null,
            'source' => null,
            'revision' => null,
            'generated_at' => null,
            'index_url' => $indexUrl,
            'results' => [],
        ];

        if (null === $index) {
            $response['message'] = \sprintf('No Craft %s docs index is published at %s. The search does not fall back to another Craft major.', $requested, $indexUrl);

            return $response;
        }

        $served = (string) ($index['major'] ?? '');
        if ($served !== $requested) {
            $response['served_major'] = '' === $served ? null : $served;
            $response['message'] = \sprintf('The index at %s is for Craft %s, not Craft %s. The search does not fall back to another Craft major.', $indexUrl, '' === $served ? 'an unknown major' : $served, $requested);

            return $response;
        }

        $response['served_major'] = $served;
        $response['source'] = \is_string($index['source'] ?? null) ? $index['source'] : null;
        $response['revision'] = \is_string($index['revision'] ?? null) ? $index['revision'] : null;
        $response['generated_at'] = \is_string($index['generated_at'] ?? null) ? $index['generated_at'] : null;

        $pages = $this->pages($index);
        $limit = max(8, $maxResults * 2);
        $candidates = $this->candidates($pages, $query, $limit);
        if ([] === $candidates) {
            $candidates = $this->fullTextCandidates($pages, $query, $limit, $requested, $response['revision']);
        }

        $results = [];
        foreach ($candidates as $candidate) {
            $page = $candidate['page'];
            $pageUrl = $this->baseUrl.'/'.$requested.'/pages/'.implode('/', array_map('rawurlencode', explode('/', $page['path'])));
            $fetched = $this->http->get($pageUrl);
            if (200 !== $fetched['status']) {
                continue;
            }

            $detailTokens = array_values(array_diff(Text::tokens($query), Text::tokens($page['title'])));
            $match = Text::bestMatch($fetched['body'], $query, $detailTokens);
            if (null === $match) {
                continue;
            }

            $results[] = [
                'score' => $candidate['score'] + $match['score'],
                'title' => $page['title'],
                'path' => $page['path'],
                'url' => $page['url'],
                'requested_major' => $requested,
                'served_major' => $served,
                'revision' => $response['revision'],
                'source_url' => $pageUrl,
                'line_start' => $match['line_start'],
                'line_end' => $match['line_end'],
                'excerpt' => $match['excerpt'],
            ];
        }

        usort($results, static fn (array $left, array $right): int => [$right['score'], $left['path']] <=> [$left['score'], $right['path']]);

        $response['results'] = array_map(static function (array $result): array {
            unset($result['score']);

            return $result;
        }, \array_slice($results, 0, $maxResults));

        if ([] === $response['results']) {
            $response['message'] = 'No matching pages. Try other words, for example a tag, method or setting name.';
        }

        return $response;
    }

    /**
     * Pages of an index, deduplicated by path.
     *
     * @param array<string, mixed> $index
     *
     * @return list<array{path: string, title: string, url: string, headings: list<string>}>
     */
    public function pages(array $index): array
    {
        $pages = [];

        foreach (\is_array($index['pages'] ?? null) ? $index['pages'] : [] as $page) {
            if (!\is_array($page) || !\is_string($page['path'] ?? null) || isset($pages[$page['path']])) {
                continue;
            }

            $headings = \is_array($page['headings'] ?? null) ? array_values(array_filter($page['headings'], 'is_string')) : [];
            $pages[$page['path']] = [
                'path' => $page['path'],
                'title' => \is_string($page['title'] ?? null) ? $page['title'] : basename($page['path'], '.md'),
                'url' => \is_string($page['url'] ?? null) ? $page['url'] : '',
                'headings' => $headings,
            ];
        }

        return array_values($pages);
    }

    /**
     * Pages ranked by how well their title, headings and path match the query.
     *
     * @param list<array{path: string, title: string, url: string, headings: list<string>}> $pages
     *
     * @return list<array{page: array{path: string, title: string, url: string, headings: list<string>}, score: int}>
     */
    private function candidates(array $pages, string $query, int $limit): array
    {
        $queryTokens = Text::tokens($query);
        $normalizedQuery = strtolower(trim($query));
        $ranked = [];

        foreach ($pages as $page) {
            $titleTokens = Text::tokens($page['title']);
            $headingTokens = Text::tokens(implode(' ', $page['headings']));
            $pathTokens = Text::tokens($page['path']);
            $score = str_contains(strtolower($page['title']), $normalizedQuery) ? 20 : 0;

            foreach ($queryTokens as $token) {
                $score += \in_array($token, $titleTokens, true) ? 10 : 0;
                $score += \in_array($token, $headingTokens, true) ? 4 : 0;
                $score += \in_array($token, $pathTokens, true) ? 3 : 0;
            }

            if ($score > 0) {
                $ranked[] = ['page' => $page, 'score' => $score];
            }
        }

        usort($ranked, static fn (array $left, array $right): int => [$right['score'], $left['page']['path']] <=> [$left['score'], $right['page']['path']]);

        return \array_slice($ranked, 0, $limit);
    }

    /**
     * Pages whose body in the full text mentions the query, ranked by hit count.
     *
     * The index has no body terms, so this finds names that only appear in page
     * text. It matches the query's code terms, or its tokens when it has none.
     * Full-text sections have a title but no path, so a title shared by several
     * pages yields all of them.
     *
     * @param list<array{path: string, title: string, url: string, headings: list<string>}> $pages
     *
     * @return list<array{page: array{path: string, title: string, url: string, headings: list<string>}, score: int}>
     */
    private function fullTextCandidates(array $pages, string $query, int $limit, string $major, ?string $revision): array
    {
        $terms = Text::codeTerms($query) ?: Text::tokens($query);
        if ([] === $terms) {
            return [];
        }

        $pagesByTitle = [];
        foreach ($pages as $page) {
            $pagesByTitle[strtolower(trim($page['title']))][] = $page;
        }

        $ranked = [];
        foreach ($this->fullText($major, $revision) as $section) {
            $hits = array_sum(array_map(static fn (string $term): int => substr_count($section['body'], $term), $terms));
            if (0 === $hits) {
                continue;
            }

            foreach ($pagesByTitle[$section['title']] ?? [] as $page) {
                $ranked[$page['path']] = ['page' => $page, 'score' => max($hits, $ranked[$page['path']]['score'] ?? 0)];
            }
        }

        $ranked = array_values($ranked);
        usort($ranked, static fn (array $left, array $right): int => [$right['score'], $left['page']['path']] <=> [$left['score'], $right['page']['path']]);

        return \array_slice($ranked, 0, $limit);
    }

    /**
     * Lowercase title and body of each page in `{major}/llms-full.txt`, fetched once per index revision.
     *
     * A page starts at a line `---` followed by a `# Title` line; anything before
     * the first page is the file header. A failed download is not cached.
     *
     * @return list<array{title: string, body: string}> empty when the file cannot be fetched
     */
    private function fullText(string $major, ?string $revision): array
    {
        $key = $major.'@'.($revision ?? '');
        if (isset($this->fullTexts[$key])) {
            return $this->fullTexts[$key];
        }

        try {
            $response = $this->http->get($this->baseUrl.'/'.$major.'/llms-full.txt');
        } catch (\RuntimeException) {
            return [];
        }

        if (200 !== $response['status']) {
            return [];
        }

        $sections = [];
        $chunks = preg_split('/^---\n(?=# )/m', str_replace("\r\n", "\n", $response['body'])) ?: [];
        foreach (\array_slice($chunks, 1) as $chunk) {
            $lines = explode("\n", $chunk, 2);
            $sections[] = [
                'title' => strtolower(trim(substr($lines[0], 2))),
                'body' => strtolower($lines[1] ?? ''),
            ];
        }

        return $this->fullTexts[$key] = $sections;
    }

    /**
     * @return array<string, mixed>|null null when the major has no index
     */
    private function index(string $major, string $url): ?array
    {
        if (\array_key_exists($major, $this->indexes)) {
            return $this->indexes[$major];
        }

        $response = $this->http->get($url);

        if (404 === $response['status']) {
            return $this->indexes[$major] = null;
        }

        if (200 !== $response['status']) {
            throw new \RuntimeException(\sprintf('Craft docs index request failed: %d %s', $response['status'], $url));
        }

        $data = json_decode($response['body'], true);
        if (!\is_array($data)) {
            throw new \RuntimeException(\sprintf('Craft docs index at %s is not valid JSON.', $url));
        }

        return $this->indexes[$major] = $data;
    }
}
