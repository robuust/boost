<?php

namespace Robuust\Boost\Docs;

/**
 * Tokenizing and excerpt helpers shared by the docs searches.
 */
final class Text
{
    private const STOP_WORDS = ['a', 'an', 'and', 'are', 'can', 'do', 'does', 'for', 'from', 'how', 'in', 'is', 'it', 'of', 'on', 'or', 'should', 'the', 'to', 'use', 'what', 'when', 'which', 'why', 'with', 'work', 'works'];

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
     * starts the excerpt, so it opens at that section.
     *
     * @param list<string> $boostTokens tokens that weigh extra, such as those not already in the title
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

        foreach ($normalizedLines as $index => $normalizedLine) {
            if (1 === preg_match('/^\s*(?:```|~~~)/', $normalizedLine)) {
                $inFence = !$inFence;
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

            $start = $heading ? $index : max(0, $index - $before);
            $end = min(\count($lines), $index + $after);
            $best = [
                'score' => $score,
                'line_start' => $start + 1,
                'line_end' => $end,
                'excerpt' => trim(implode("\n", \array_slice($lines, $start, $end - $start))),
            ];
        }

        return $best;
    }
}
