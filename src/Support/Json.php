<?php

namespace Robuust\Boost\Support;

/**
 * JSON helpers that fail with a message naming the file.
 */
final class Json
{
    /**
     * Decode a JSON file into an associative array.
     *
     * @return array<string, mixed>|null null when the file does not exist
     *
     * @throws \RuntimeException when the file is not a JSON object
     */
    public static function readFile(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        return self::decode((string) file_get_contents($path), $path);
    }

    /**
     * Decode a JSON object.
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException when the string is not a JSON object
     */
    public static function decode(string $json, string $source): array
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException(\sprintf('%s is not valid JSON: %s', $source, $exception->getMessage()), 0, $exception);
        }

        if (!\is_array($data)) {
            throw new \RuntimeException(\sprintf('%s must contain a JSON object.', $source));
        }

        return $data;
    }

    /**
     * Encode a value as pretty-printed JSON with a trailing newline.
     */
    public static function encode(mixed $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
    }
}
