<?php

namespace Robuust\Boost\Sync;

/**
 * Small filesystem helpers without dependencies.
 */
final class Filesystem
{
    public static function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0o755, true) && !is_dir($path)) {
            throw new \RuntimeException(\sprintf('Failed to create directory %s.', $path));
        }
    }

    /**
     * Remove a file, symlink or directory tree. Symlinked directories are unlinked, not followed.
     */
    public static function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        foreach (self::children($path) as $child) {
            self::remove($path.'/'.$child);
        }

        rmdir($path);
    }

    /**
     * Relative paths of all files below a directory, sorted.
     *
     * @return list<string>
     */
    public static function files(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        foreach (self::children($directory) as $child) {
            $path = $directory.'/'.$child;
            if (is_dir($path) && !is_link($path)) {
                foreach (self::files($path) as $nested) {
                    $files[] = $child.'/'.$nested;
                }
            } elseif (is_file($path)) {
                $files[] = $child;
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Names of the entries of a directory, sorted.
     *
     * @return list<string>
     */
    public static function entries(string $directory): array
    {
        return is_dir($directory) ? self::children($directory) : [];
    }

    /**
     * Names of the subdirectories of a directory, sorted.
     *
     * @return list<string>
     */
    public static function directories(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        return array_values(array_filter(
            self::children($directory),
            static fn (string $child): bool => is_dir($directory.'/'.$child),
        ));
    }

    /**
     * @return list<string>
     */
    private static function children(string $directory): array
    {
        $children = array_values(array_diff((array) scandir($directory), ['.', '..']));
        sort($children);

        return array_map('strval', $children);
    }
}
