<?php

namespace Robuust\Boost\Tests;

use Robuust\Boost\Rules\RulesIndex;
use Robuust\Boost\Support\Frontmatter;

/**
 * @internal
 */
final class RulesIndexTest extends TestCase
{
    public function testRendersBoostsIndexFormat(): void
    {
        $root = $this->project('craft5');
        $this->write($root.'/.ai/rules/boost/inertia.md', "---\npaths: ['resources/js/**', \"app/Http/**\"]\n---\n\n# Inertia\n");
        $this->write($root.'/.ai/rules/index.md', 'old index');

        self::assertSame(
            "# Project Rules Index\n\n"
            ."Before planning or editing, find the row whose globs match the file's path and read that rule file.\n\n"
            ."| Applies to | Rule file |\n| --- | --- |\n"
            ."| resources/js/**, app/Http/** | .ai/rules/boost/inertia.md |\n"
            ."| templates/** | .ai/rules/templates.md |\n",
            RulesIndex::render($root),
        );
        self::assertSame(['.ai/rules/notes.md'], RulesIndex::unindexed($root));
    }

    public function testEmptyIndex(): void
    {
        $root = $this->project('laravel');

        self::assertStringEndsWith("No rules recorded yet.\n", RulesIndex::render($root));
    }

    public function testIndexRulesCommandRegeneratesTheIndex(): void
    {
        $root = $this->project('craft5');

        $result = $this->cli(['index-rules', '--root='.$root]);

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString('Skipped .ai/rules/notes.md: no valid `paths` frontmatter.', $result['stderr']);
        self::assertSame(RulesIndex::render($root), file_get_contents($root.'/.ai/rules/index.md'));
    }

    public function testFrontmatterBlockLists(): void
    {
        $parsed = Frontmatter::parse("---\nname: fe-craft\npaths:\n  - 'it''s/**'\n  - \"b/*.twig\"\n  - c\nmetadata:\n  version: \"1.0\"\n---\nBody\n");

        self::assertNotNull($parsed);
        self::assertSame('fe-craft', $parsed['data']['name']);
        self::assertSame(["it's/**", 'b/*.twig', 'c'], $parsed['data']['paths']);
        self::assertSame("Body\n", $parsed['body']);
    }
}
