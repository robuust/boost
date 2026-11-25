<?php

namespace Robuust\Boost\Support;

/**
 * Reads the flat YAML frontmatter that skills and Boost rule files use.
 *
 * Supports scalar values (plain, single- or double-quoted), inline lists
 * (`[a, b]`) and block lists (`- a`). Nested mappings are ignored.
 */
final class Frontmatter
{
    /**
     * @return array{data: array<string, string|list<string>>, body: string}|null null when there is no frontmatter block
     */
    public static function parse(string $content): ?array
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        if (1 !== preg_match('/^\s*---[ \t]*\n(.*?)\n---[ \t]*(?:\n|$)/s', $content, $matches)) {
            return null;
        }

        $data = [];
        $key = null;

        foreach (explode("\n", $matches[1]) as $line) {
            if ('' === trim($line) || str_starts_with(ltrim($line), '#')) {
                continue;
            }

            if (null !== $key && 1 === preg_match('/^\s+-\s*(.*)$/', $line, $item)) {
                $list = $data[$key] ?? [];
                $list = \is_array($list) ? $list : [];
                $list[] = self::scalar($item[1]);
                $data[$key] = $list;

                continue;
            }

            if (1 === preg_match('/^([A-Za-z0-9_-]+):\s*(.*)$/', $line, $pair)) {
                $key = $pair[1];
                $value = trim($pair[2]);

                if ('' === $value) {
                    $data[$key] = [];
                } elseif (str_starts_with($value, '[') && str_ends_with($value, ']')) {
                    $inner = trim(substr($value, 1, -1));
                    $data[$key] = '' === $inner ? [] : array_map(self::scalar(...), self::splitInline($inner));
                } else {
                    $data[$key] = self::scalar($value);
                }

                continue;
            }

            // Continuation lines of nested mappings or folded scalars are not needed here.
            if (!str_starts_with($line, ' ')) {
                $key = null;
            }
        }

        return ['data' => $data, 'body' => substr($content, \strlen($matches[0]))];
    }

    private static function scalar(string $value): string
    {
        $value = trim($value);

        if (\strlen($value) >= 2 && "'" === $value[0] && str_ends_with($value, "'")) {
            return str_replace("''", "'", substr($value, 1, -1));
        }

        if (\strlen($value) >= 2 && '"' === $value[0] && str_ends_with($value, '"')) {
            $decoded = json_decode($value);

            return \is_string($decoded) ? $decoded : substr($value, 1, -1);
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function splitInline(string $inner): array
    {
        $items = [];
        $current = '';
        $quote = null;

        foreach (str_split($inner) as $char) {
            if (null === $quote && ("'" === $char || '"' === $char)) {
                $quote = $char;
            } elseif ($quote === $char) {
                $quote = null;
            } elseif (null === $quote && ',' === $char) {
                $items[] = $current;
                $current = '';

                continue;
            }
            $current .= $char;
        }
        $items[] = $current;

        return $items;
    }
}
