<?php

namespace Robuust\Boost\Tests;

use Robuust\Boost\Docs\Text;

/**
 * @internal
 */
final class TextTest extends TestCase
{
    public function testCodeTermsKeepTheirSourceSpelling(): void
    {
        self::assertSame(['@apply'], Text::codeTerms('how does @apply work'));
        self::assertSame(['with('], Text::codeTerms('eager-load relations with()'));
        self::assertSame(['craft.entries'], Text::codeTerms('what does `craft.entries()` return'));
        self::assertSame([], Text::codeTerms('eager loading'));
    }

    public function testQuestionWordsAreNotSearchTokens(): void
    {
        self::assertSame(['apply'], Text::tokens('How does @apply work?'));
    }

    public function testAMatchingHeadingStartsTheExcerpt(): void
    {
        $contents = "# Eager-Loading Elements\n\nIntro about elements.\n\n## The with param\n\nUse `.with()` to eager-load related elements.\n";

        $match = Text::bestMatch($contents, 'with()');

        self::assertNotNull($match);
        self::assertStringContainsString('`.with()`', $match['excerpt'], 'a code term matches even when its word is a stop word');

        $match = Text::bestMatch($contents, 'param');

        self::assertNotNull($match);
        self::assertSame(5, $match['line_start']);
        self::assertStringStartsWith('## The with param', $match['excerpt']);
    }

    public function testACommentInACodeFenceIsNoHeading(): void
    {
        $contents = "# Setup\n\nRun the installer:\n\n```sh\n# install the package\nnpm install tailwindcss\n```\n";

        $match = Text::bestMatch($contents, 'install package', [], 2, 2);

        self::assertNotNull($match);
        self::assertSame(4, $match['line_start'], 'the fenced comment keeps its leading context, as a heading would not');
    }

    public function testAnExcerptKeepsACodeExampleWhole(): void
    {
        $contents = implode("\n", [
            '## Lazy Eager-Loading',
            '',
            'Call `.eagerly()` on down-stream queries:',
            '',
            '```twig',
            '{% set posts = craft.entries()',
            "  .section('news')",
            '  .with([',
            "    ['featureImage'],",
            '  ])',
            '  .all() %}',
            '```',
            '',
            'More text.',
        ]);

        $match = Text::bestMatch($contents, 'Lazy Eager-Loading');

        self::assertNotNull($match);
        self::assertSame(1, $match['line_start']);
        self::assertSame(12, $match['line_end'], 'the window grows to the closing fence instead of cutting the example');
        self::assertStringEndsWith("  .all() %}\n```", $match['excerpt']);
    }

    public function testAnExcerptSkipsAFenceBeforeTheMatch(): void
    {
        $contents = "```php\n\$a = 1;\n\$b = 2;\n```\n\nThe after param narrows the results.\n";

        $match = Text::bestMatch($contents, 'after param');

        self::assertNotNull($match);
        self::assertSame(5, $match['line_start'], 'the leading context no longer starts inside the earlier code block');
        self::assertStringStartsWith('The after param', $match['excerpt']);
    }

    public function testAMatchInsideACodeBlockStartsAtItsFence(): void
    {
        $contents = implode("\n", ['Intro.', '```php', 'one();', 'two();', 'three();', 'four();', 'five();', 'needle();', '```']);

        $match = Text::bestMatch($contents, 'needle');

        self::assertNotNull($match);
        self::assertSame(2, $match['line_start'], 'the window grows back to the opening fence of the block holding the match');
        self::assertSame(9, $match['line_end']);
    }

    public function testAnUnclosedCodeBlockRunsToTheEnd(): void
    {
        $contents = implode("\n", ['## Example', '```php', ...array_fill(0, 10, 'echo 1;')]);

        $match = Text::bestMatch($contents, 'Example');

        self::assertNotNull($match);
        self::assertSame(12, $match['line_end']);
    }

    public function testOnlyAMatchingMarkerClosesACodeBlock(): void
    {
        $contents = implode("\n", ['````md', '```js', 'code', '````', '', 'Prose one.', 'Prose two.', 'Prose three.', 'The needle line.']);

        $match = Text::bestMatch($contents, 'needle');

        self::assertNotNull($match);
        self::assertSame(5, $match['line_start'], 'the nested ``` line neither closes nor reopens the outer block');
        self::assertStringStartsWith('Prose one.', $match['excerpt']);
    }

    public function testAVeryLongCodeBlockKeepsTheWindow(): void
    {
        $contents = implode("\n", ['## Long example', '```php', ...array_fill(0, 40, 'echo 1;'), '```']);

        $match = Text::bestMatch($contents, 'Long example');

        self::assertNotNull($match);
        self::assertSame(8, $match['line_end'], 'a fence beyond the extension limit keeps the original window');
    }
}
