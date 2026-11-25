<?php

namespace Robuust\Boost\Sync;

/**
 * The state of a set of project paths, used to check and undo a Boost update.
 *
 * It records files with their content and permission bits, symlinks with their target and
 * directories (empty ones too), plus the directories above each captured root, so a restore
 * also removes directories the update created.
 */
final class Snapshot
{
    /**
     * @param array<string, array{type: 'file'|'link'|'dir', value: string, mode: int}> $entries relative path => entry
     * @param list<string>                                                              $paths   the captured roots
     */
    private function __construct(
        private readonly string $root,
        private readonly array $paths,
        private readonly array $entries,
    ) {
    }

    /**
     * @param list<string> $paths files or directories relative to the root
     */
    public static function capture(string $root, array $paths): self
    {
        $entries = [];

        foreach ($paths as $path) {
            for ($parent = \dirname($path); '.' !== $parent && '' !== $parent; $parent = \dirname($parent)) {
                if (!isset($entries[$parent]) && is_dir($root.'/'.$parent) && !is_link($root.'/'.$parent)) {
                    $entries[$parent] = ['type' => 'dir', 'value' => '', 'mode' => self::mode($root.'/'.$parent)];
                }
            }

            self::captureInto($root, $path, $entries);
        }

        ksort($entries, SORT_STRING);

        return new self($root, $paths, $entries);
    }

    /**
     * Files and symlinks whose content, target, type or executable bit differ from another snapshot of the same paths.
     *
     * Directories are left out: Git does not track them, so they never make generated output out of date.
     *
     * @return list<string>
     */
    public function diff(self $other): array
    {
        $changed = [];

        foreach ($this->entries + $other->entries as $path => $entry) {
            $before = $this->entries[$path] ?? null;
            $after = $other->entries[$path] ?? null;

            if ('dir' === ($before['type'] ?? 'dir') && 'dir' === ($after['type'] ?? 'dir')) {
                continue;
            }

            if (null === $before || null === $after || $before['type'] !== $after['type'] || $before['value'] !== $after['value']
                || ('file' === $before['type'] && ($before['mode'] & 0o111) !== ($after['mode'] & 0o111))) {
                $changed[] = $path;
            }
        }

        sort($changed, SORT_STRING);

        return $changed;
    }

    /**
     * Put every captured path back as it was, touching only the paths that changed.
     */
    public function restore(): void
    {
        $current = self::capture($this->root, $this->paths)->entries;

        // Remove what was added or changed type, deepest paths first.
        krsort($current, SORT_STRING);
        foreach ($current as $path => $entry) {
            if (($this->entries[$path]['type'] ?? null) !== $entry['type']) {
                $absolute = $this->root.'/'.$path;
                if (is_link($absolute) || file_exists($absolute)) {
                    Filesystem::remove($absolute);
                }
            }
        }

        // Recreate what was removed or changed, parents first.
        foreach ($this->entries as $path => $entry) {
            $absolute = $this->root.'/'.$path;
            $now = $current[$path] ?? null;

            if (null === $now || $now['type'] !== $entry['type'] || $now['value'] !== $entry['value']) {
                self::write($absolute, $entry);
            }
        }

        // Put permission bits back last and deepest first, so a read-only directory never blocks a write below it.
        foreach (array_reverse($this->entries, true) as $path => $entry) {
            $absolute = $this->root.'/'.$path;

            if ('link' !== $entry['type'] && self::mode($absolute) !== $entry['mode']) {
                chmod($absolute, $entry['mode']);
            }
        }
    }

    /**
     * @param array{type: 'file'|'link'|'dir', value: string, mode: int} $entry
     */
    private static function write(string $absolute, array $entry): void
    {
        if ('dir' === $entry['type']) {
            Filesystem::ensureDirectory($absolute);

            return;
        }

        if (is_link($absolute) || file_exists($absolute)) {
            Filesystem::remove($absolute);
        }

        Filesystem::ensureDirectory(\dirname($absolute));

        if ('link' === $entry['type']) {
            symlink($entry['value'], $absolute);
        } else {
            file_put_contents($absolute, $entry['value']);
        }
    }

    private static function mode(string $absolute): int
    {
        clearstatcache(true, $absolute);

        return fileperms($absolute) & 0o7777;
    }

    /**
     * @param array<string, array{type: 'file'|'link'|'dir', value: string, mode: int}> $entries
     */
    private static function captureInto(string $root, string $path, array &$entries): void
    {
        $absolute = $root.'/'.$path;

        if (is_link($absolute)) {
            $entries[$path] = ['type' => 'link', 'value' => (string) readlink($absolute), 'mode' => 0];

            return;
        }

        if (is_file($absolute)) {
            $entries[$path] = ['type' => 'file', 'value' => (string) file_get_contents($absolute), 'mode' => self::mode($absolute)];

            return;
        }

        if (is_dir($absolute)) {
            $entries[$path] = ['type' => 'dir', 'value' => '', 'mode' => self::mode($absolute)];

            foreach (array_diff((array) scandir($absolute), ['.', '..']) as $child) {
                self::captureInto($root, $path.'/'.$child, $entries);
            }
        }
    }
}
