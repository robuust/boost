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
}
