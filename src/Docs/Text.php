<?php

namespace Robuust\Boost\Docs;

/**
 * Tokenizing and excerpt helpers shared by the docs searches.
 */
final class Text
{
    private const STOP_WORDS = ['a', 'an', 'and', 'are', 'can', 'do', 'does', 'for', 'from', 'how', 'in', 'is', 'it', 'of', 'on', 'or', 'should', 'the', 'to', 'use', 'what', 'when', 'which', 'why', 'with', 'work', 'works'];

    /**
     * Lines an excerpt may grow by on either side to keep a code fence whole.
     */
    private const MAX_FENCE_EXTENSION = 30;

    /**
     * Unique lowercase search tokens.
     *
     * @return list<string>
     */
    public static function tokens(string $value): array
    {
        $parts = preg_split('/[^a-z0-9]+/', strtolower(str_replace('@', '', $value))) ?: [];

        return array_values(array_unique(array_filter(
            $parts,
            static fn (string $token): bool => \strlen($token) > 1 && !\in_array($token, self::STOP_WORDS, true),
        )));
    }

    /**
     * Code terms in a query, such as `@apply`, `with()` or a backticked name, lowercased as they appear in source.
     *
     * @return list<string>
     */
    public static function codeTerms(string $value): array
    {
        preg_match_all('/`([^`]+)`|(@[a-z][\w-]*)|([a-z_][\w.:-]*)\(\)/i', $value, $matches, \PREG_SET_ORDER);

        $terms = [];
        foreach ($matches as $match) {
            $terms[] = strtolower(match (true) {
                '' !== ($match[3] ?? '') => $match[3].'(',
                '' !== ($match[2] ?? '') => $match[2],
                default => rtrim($match[1], '()'),
            });
        }

        return array_values(array_unique(array_filter($terms, static fn (string $term): bool => \strlen($term) > 2)));
    }

    /**
     * The best matching excerpt of a document, or null when no line matches.
     *
     * A code term from the query that appears literally, such as `@apply`, weighs
     * most. A matching Markdown or HTML heading outside a code fence weighs extra and
     * starts the excerpt, so it opens at that section. An excerpt never starts or ends
     * inside a code fence when it can avoid it: it skips a fence that lies wholly before
     * the match, and grows to include any other cut fence within MAX_FENCE_EXTENSION
     * lines per side, so code examples are not cut off.
     *
     * @param string       $contents    the document text
     * @param string       $query       the search query
     * @param list<string> $boostTokens tokens that weigh extra, such as those not already in the title
     * @param int          $before      lines of context before the matching line
     * @param int          $after       lines of context from the matching line on
     *
     * @return array{score: int, line_start: int, line_end: int, excerpt: string}|null
     */
    public static function bestMatch(string $contents, string $query, array $boostTokens = [], int $before = 4, int $after = 8): ?array
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $contents));
        $normalizedLines = array_map('strtolower', $lines);
        $queryTokens = self::tokens($query);
        $codeTerms = self::codeTerms($query);
        $normalizedQuery = strtolower(trim($query));
        $best = null;
        $inFence = false;
        $fences = [];
        $fenceOpen = 0;
        $fenceMarker = '';

        foreach ($normalizedLines as $index => $normalizedLine) {
            // As in CommonMark, only a bare run of the opening character, at least as long, closes a fence.
            if (1 === preg_match('/^\s*(`{3,}|~{3,})(.*)$/', $normalizedLine, $marker)) {
                if (!$inFence) {
                    $inFence = true;
                    $fenceOpen = $index;
                    $fenceMarker = $marker[1];
                } elseif ($marker[1][0] === $fenceMarker[0] && \strlen($marker[1]) >= \strlen($fenceMarker) && '' === trim($marker[2])) {
                    $inFence = false;
                    $fences[] = [$fenceOpen, $index];
                }
            }

            $matched = array_filter($queryTokens, static fn (string $token): bool => str_contains($normalizedLine, $token));
            $literal = array_filter($codeTerms, static fn (string $term): bool => str_contains($normalizedLine, $term));
            if ([] === $matched && [] === $literal) {
                continue;
            }

            $boosted = array_filter($boostTokens, static fn (string $token): bool => str_contains($normalizedLine, $token));
            $heading = !$inFence && 1 === preg_match('/^\s*(?:#{1,6}\s|<h[1-6][\s>]|<a href="#)/', $normalizedLine);
            $score = ('' !== $normalizedQuery && str_contains($normalizedLine, $normalizedQuery) ? 20 : 0)
                + \count($matched) * 4
                + \count($boosted) * 10
                + \count($literal) * 24
                + ($heading ? 8 : 0);

            if (null !== $best && $best['score'] >= $score) {
                continue;
            }

            $best = [
                'score' => $score,
                'index' => $index,
                'start' => $heading ? $index : max(0, $index - $before),
                'end' => min(\count($lines), $index + $after),
            ];
        }

        if (null === $best) {
            return null;
        }

        if ($inFence) {
            $fences[] = [$fenceOpen, \count($lines) - 1];
        }

        [$start, $end] = self::keepFencesWhole($best['index'], $best['start'], $best['end'], $fences);

        return [
            'score' => $best['score'],
            'line_start' => $start + 1,
            'line_end' => $end,
            'excerpt' => trim(implode("\n", \array_slice($lines, $start, $end - $start))),
        ];
    }

    /**
     * Moves the edges of an excerpt window out of code fences, so no fence is cut.
     *
     * A window that starts inside a fence before the matching line skips that fence;
     * one that starts inside the fence holding the match, or ends inside any fence,
     * grows to the whole fence when its boundary lies within MAX_FENCE_EXTENSION
     * lines. A longer code block keeps the original window on that side.
     *
     * @param int                   $match  index of the matching line
     * @param int                   $start  index of the window's first line
     * @param int                   $end    index after the window's last line
     * @param list<array{int, int}> $fences indexes of each fence's opening and closing line
     *
     * @return array{int, int} the adjusted start and end
     */
    private static function keepFencesWhole(int $match, int $start, int $end, array $fences): array
    {
        foreach ($fences as [$open, $close]) {
            if ($open < $start && $start <= $close) {
                if ($close < $match) {
                    $start = $close + 1;
                } elseif ($start - $open <= self::MAX_FENCE_EXTENSION) {
                    $start = $open;
                }
            }

            if ($open < $end && $close >= $end && $close + 1 - $end <= self::MAX_FENCE_EXTENSION) {
                $end = $close + 1;
            }
        }

        return [$start, $end];
    }
}
