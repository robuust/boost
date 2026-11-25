<?php

namespace Robuust\Boost\Tests;

use Robuust\Boost\Docs\CraftDocs;
use Robuust\Boost\Docs\DocsSearch;
use Robuust\Boost\Docs\HttpClient;
use Robuust\Boost\Stack;

/**
 * @internal
 */
final class CraftDocsTest extends TestCase
{
    private const REVISION = 'a8ba13427aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testResultsNameTheRequestedAndServedMajorUrlPathAndRevision(): void
    {
        $result = $this->docs()->search('cache tag', 5);

        self::assertSame('5', $result['requested_major']);
        self::assertSame('5', $result['served_major']);
        self::assertSame(self::REVISION, $result['revision']);
        self::assertSame('craftcms/docs', $result['source']);
        self::assertNotEmpty($result['results']);

        $first = $result['results'][0];
        self::assertSame('docs/5.x/reference/twig/tags.md', $first['path']);
        self::assertSame('https://craftcms.com/docs/5.x/reference/twig/tags.html', $first['url']);
        self::assertSame('5', $first['requested_major']);
        self::assertSame('5', $first['served_major']);
        self::assertSame(self::REVISION, $first['revision']);
        self::assertStringContainsString('{% cache %}', $first['excerpt']);
    }

    public function testMissingMajorDoesNotFallBack(): void
    {
        $result = $this->docs()->search('cache tag', 6);

        self::assertSame('6', $result['requested_major']);
        self::assertNull($result['served_major']);
        self::assertNull($result['revision']);
        self::assertSame([], $result['results']);
        self::assertStringContainsString('No Craft 6 docs index', $result['message']);
        self::assertStringContainsString('does not fall back', $result['message']);
    }

    public function testIndexForAnotherMajorIsNotServed(): void
    {
        $result = $this->docs()->search('cache', 7);

        self::assertSame('7', $result['requested_major']);
        self::assertSame('6', $result['served_major']);
        self::assertSame([], $result['results']);
        self::assertStringContainsString('is for Craft 6, not Craft 7', $result['message']);
    }

    public function testTitleCollisionsAreResolvedByPath(): void
    {
        $docs = $this->docs();
        $pages = $docs->pages((array) json_decode((string) file_get_contents(self::fixture('docs/5/index.json')), true));

        self::assertSame(
            ['docs/5.x/reference/twig/tags.md', 'docs/5.x/development/tags.md', 'docs/5.x/development/eager-loading.md', 'docs/5.x/development/element-queries.md'],
            array_column($pages, 'path'),
            'pages are keyed by path: a repeated path is dropped, a repeated title is kept',
        );
        self::assertSame('Tags', $pages[0]['title'], 'the first entry for a path wins');

        $result = $docs->search('tags', 5, 10);
        $tags = array_values(array_filter($result['results'], static fn (array $page): bool => 'Tags' === $page['title']));

        self::assertCount(2, $tags);
        self::assertSame(
            ['docs/5.x/development/tags.md', 'docs/5.x/reference/twig/tags.md'],
            array_values(array_unique(array_merge(...array_map(static fn (array $page): array => [$page['path']], $tags)))),
        );
        self::assertNotSame($tags[0]['url'], $tags[1]['url']);
    }

    public function testBodyOnlyTermIsFoundThroughTheFullText(): void
    {
        $urls = new \ArrayObject();
        $docs = $this->recordingDocs($urls);

        foreach (['andWhere', 'andWhere()'] as $query) {
            $result = $docs->search($query, 5);

            self::assertCount(1, $result['results'], $query);
            $first = $result['results'][0];
            self::assertSame('docs/5.x/development/element-queries.md', $first['path']);
            self::assertSame('https://craftcms.com/docs/5.x/development/element-queries.html', $first['url']);
            self::assertSame('5', $first['requested_major']);
            self::assertSame('5', $first['served_major']);
            self::assertSame(self::REVISION, $first['revision']);
            self::assertStringContainsString('andWhere(', $first['excerpt']);
        }

        self::assertCount(1, $this->fullTextRequests($urls), 'the full text is downloaded once per index revision');
    }

    public function testFullTextTitleSharedByPagesYieldsEachPage(): void
    {
        $urls = new \ArrayObject();
        $result = $this->recordingDocs($urls)->search('tagGroupId', 5);

        self::assertSame(['docs/5.x/development/tags.md'], array_column($result['results'], 'path'));
        self::assertContains('file://'.self::fixture('docs').'/5/pages/docs/5.x/reference/twig/tags.md', $urls->getArrayCopy(), 'both pages titled Tags are candidates');
    }

    public function testFullTextIsNotRequestedWhenTheIndexMatches(): void
    {
        $urls = new \ArrayObject();
        $result = $this->recordingDocs($urls)->search('cache tag', 5);

        self::assertNotEmpty($result['results']);
        self::assertSame([], $this->fullTextRequests($urls));
    }

    public function testFailedFullTextDownloadFindsNothing(): void
    {
        foreach (['error', 'unreachable'] as $fullText) {
            $urls = new \ArrayObject();
            $result = $this->recordingDocs($urls, $fullText)->search('andWhere', 5);

            self::assertCount(1, $this->fullTextRequests($urls), $fullText);
            self::assertSame([], $result['results'], $fullText);
            self::assertStringContainsString('No matching pages', $result['message'], $fullText);
        }
    }

    public function testProjectMajorIsTheDefault(): void
    {
        $search = new DocsSearch(Stack::fromPath(self::fixture('craft5')), $this->docs());

        self::assertSame('5', $search->craft('eager loading')['requested_major']);
        self::assertSame('6', $search->craft('eager loading', 6)['requested_major']);
    }

    public function testBaseUrlPrefersTheEnvironment(): void
    {
        self::assertSame(CraftDocs::DEFAULT_BASE_URL, CraftDocs::baseUrl());
        self::assertSame('https://example.com/docs', CraftDocs::baseUrl('https://example.com/docs/'));

        putenv('ROBUUST_DOCS_BASE_URL=https://docs.example.com');
        self::assertSame('https://docs.example.com', CraftDocs::baseUrl('https://example.com/docs'));
    }

    private function docs(): CraftDocs
    {
        return new CraftDocs('file://'.self::fixture('docs'));
    }

    /**
     * Docs over the fixtures through a client that records each requested URL.
     *
     * @param \ArrayObject<int, string> $urls
     * @param string                    $fullText `serve`, `error` for a 500 response or `unreachable` for a failed request
     */
    private function recordingDocs(\ArrayObject $urls, string $fullText = 'serve'): CraftDocs
    {
        $client = new class($urls, $fullText) extends HttpClient {
            /**
             * @param \ArrayObject<int, string> $urls
             */
            public function __construct(private readonly \ArrayObject $urls, private readonly string $fullText)
            {
            }

            public function get(string $url, array $headers = []): array
            {
                $this->urls->append($url);

                if (str_ends_with($url, '/llms-full.txt') && 'error' === $this->fullText) {
                    return ['status' => 500, 'body' => ''];
                }

                if (str_ends_with($url, '/llms-full.txt') && 'unreachable' === $this->fullText) {
                    throw new \RuntimeException('Request failed: '.$url);
                }

                return parent::get($url, $headers);
            }
        };

        return new CraftDocs('file://'.self::fixture('docs'), $client);
    }

    /**
     * @param \ArrayObject<int, string> $urls
     *
     * @return list<string>
     */
    private function fullTextRequests(\ArrayObject $urls): array
    {
        return array_values(array_filter($urls->getArrayCopy(), static fn (string $url): bool => str_ends_with($url, '/llms-full.txt')));
    }
}
