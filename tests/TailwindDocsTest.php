<?php

namespace Robuust\Boost\Tests;

use Robuust\Boost\Docs\TailwindDocs;
use Robuust\Boost\Stack;

/**
 * @internal
 */
final class TailwindDocsTest extends TestCase
{
    private const COMMIT = '0123456789abcdef0123456789abcdef01234567';

    public function testSearchesOfficialSourcesPinnedToACommit(): void
    {
        $root = $this->project('craft5');

        $result = (new TailwindDocs(new FakeTailwindClient()))->search(Stack::fromPath($root), '@utility');

        self::assertSame('^4.3.3', $result['tailwind']['declared_version']);
        self::assertSame('4.3.3', $result['tailwind']['installed_version']);
        self::assertNull($result['tailwind']['locked_version']);
        self::assertSame(self::COMMIT, $result['source']['commit']);
        self::assertSame('Functions and directives', $result['results'][0]['title']);
        self::assertSame('https://tailwindcss.com/docs/functions-and-directives', $result['results'][0]['documentation_url']);
        self::assertMatchesRegularExpression('/'.self::COMMIT.'.+#L5$/', $result['results'][0]['source_url']);
        self::assertStringStartsWith('### @utility', $result['results'][0]['excerpt']);
    }

    public function testRejectsProjectsNotOnTailwind4BeforeRequesting(): void
    {
        $root = $this->project('craft5');
        $this->write($root.'/node_modules/tailwindcss/package.json', '{"version": "3.4.17"}');
        $client = new FakeTailwindClient();

        try {
            (new TailwindDocs($client))->search(Stack::fromPath($root), 'theme');
            self::fail('Expected an exception.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('only supports Tailwind CSS v4; this project uses 3.4.17', $exception->getMessage());
        }

        self::assertSame(0, $client->requests);
    }

    public function testCodeTermsOutrankWordsThatAppearOnEveryPage(): void
    {
        $root = $this->project('craft5');

        $result = (new TailwindDocs(new FakeTailwindClient()))->search(Stack::fromPath($root), 'how does @apply work in Tailwind 4');

        self::assertSame('Functions and directives', $result['results'][0]['title']);
        self::assertSame(9, $result['results'][0]['line_start']);
        self::assertStringStartsWith('### @apply', $result['results'][0]['excerpt']);
        self::assertNotContains('Adding custom styles', array_column($result['results'], 'title'), 'the product name and question words match nothing');
    }

    public function testReadsTheLockedVersionFromYarnBerryWithoutNodeModules(): void
    {
        $root = $this->project('craft5');
        unlink($root.'/node_modules/tailwindcss/package.json');
        $this->write($root.'/yarn.lock', "\"@tailwindcss/vite@npm:^4.1.0\":\n  version: 4.1.0\n  resolution: \"@tailwindcss/vite@npm:4.1.0\"\n\n\"tailwindcss@npm:4.3.4, tailwindcss@npm:^4.3.3\":\n  version: 4.3.4\n  resolution: \"tailwindcss@npm:4.3.4\"\n");

        $version = TailwindDocs::version(Stack::fromPath($root));

        self::assertNull($version['installed_version']);
        self::assertSame('4.3.4', $version['locked_version']);
    }

    public function testReadsTheLockedVersionFromPackageLock(): void
    {
        $root = $this->project('craft5');
        unlink($root.'/node_modules/tailwindcss/package.json');
        unlink($root.'/yarn.lock');
        $this->write($root.'/package-lock.json', '{"packages": {"node_modules/tailwindcss": {"version": "4.3.2"}}}');

        self::assertSame('4.3.2', TailwindDocs::version(Stack::fromPath($root))['locked_version']);
    }

    public function testRejectsALockedTailwind3BeforeRequesting(): void
    {
        $root = $this->project('craft5');
        unlink($root.'/node_modules/tailwindcss/package.json');
        $this->write($root.'/yarn.lock', "tailwindcss@^3.4.0:\n  version \"3.4.17\"\n  resolved \"https://registry.yarnpkg.com/tailwindcss/-/tailwindcss-3.4.17.tgz\"\n");
        $client = new FakeTailwindClient();

        try {
            (new TailwindDocs($client))->search(Stack::fromPath($root), 'theme');
            self::fail('Expected an exception.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('this project uses 3.4.17', $exception->getMessage());
        }

        self::assertSame(0, $client->requests);
    }

    public function testKeepsTheProductNameInsideCode(): void
    {
        $root = $this->project('craft5');

        $result = (new TailwindDocs(new FakeTailwindClient()))->search(Stack::fromPath($root), '@tailwind directive in Tailwind CSS v4');

        self::assertSame('Upgrade guide', $result['results'][0]['title']);
        self::assertStringContainsString('`@tailwind` directives', $result['results'][0]['excerpt']);
    }

    public function testPrefersTheLockEntryForTheDeclaredRange(): void
    {
        $root = $this->project('craft5');
        unlink($root.'/node_modules/tailwindcss/package.json');
        $this->write($root.'/yarn.lock', "tailwindcss@^3.4.0:\r\n  version \"3.4.17\"\r\n\r\ntailwindcss@^4.3.3:\r\n  version \"4.3.4\"\r\n");

        self::assertSame('4.3.4', TailwindDocs::version(Stack::fromPath($root))['locked_version']);
    }
}
