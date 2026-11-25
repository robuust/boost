<?php

namespace Robuust\Boost\Sync;

/**
 * A planned set of file writes and deletions under a project root.
 *
 * The Craft 5 sync builds a changeset first, so `--check` can report the same
 * changes that a real sync would apply.
 */
final class Changeset
{
    /** @var array<string, string> relative path => content */
    private array $writes = [];

    /** @var array<string, true> relative paths of files or directories */
    private array $deletes = [];

    /** @var array<string, string> relative path => symlink target */
    private array $links = [];

    public function __construct(private readonly string $root)
    {
    }

    public function put(string $relative, string $content): void
    {
        unset($this->deletes[$relative]);
        $this->writes[$relative] = $content;
    }

    /**
     * Remove a file or a whole directory.
     */
    public function delete(string $relative): void
    {
        unset($this->writes[$relative], $this->links[$relative]);
        $this->deletes[$relative] = true;
    }

    /**
     * Make a path a symlink to a target relative to the link's directory, replacing what is there.
     */
    public function link(string $relative, string $target): void
    {
        unset($this->writes[$relative], $this->deletes[$relative]);
        $this->links[$relative] = $target;
    }

    /**
     * The planned content of a path, or what is on disk when nothing is planned.
     */
    public function contents(string $relative): ?string
    {
        if (\array_key_exists($relative, $this->writes)) {
            return $this->writes[$relative];
        }

        $path = $this->absolute($relative);

        return is_file($path) ? (string) file_get_contents($path) : null;
    }

    /**
     * Paths whose content on disk differs from the plan.
     *
     * @return list<string>
     */
    public function changes(): array
    {
        $changes = [];

        foreach ($this->writes as $relative => $content) {
            $path = $this->absolute($relative);
            if (!is_file($path) || file_get_contents($path) !== $content) {
                $changes[] = $relative;
            }
        }

        foreach (array_keys($this->deletes) as $relative) {
            if (file_exists($this->absolute($relative)) || is_link($this->absolute($relative))) {
                $changes[] = $relative;
            }
        }

        foreach ($this->links as $relative => $target) {
            if (!$this->isLinkTo($relative, $target)) {
                $changes[] = $relative;
            }
        }

        sort($changes);

        return $changes;
    }

    /**
     * Write the plan to disk.
     */
    public function apply(): void
    {
        foreach (array_keys($this->deletes) as $relative) {
            Filesystem::remove($this->absolute($relative));
        }

        foreach ($this->links as $relative => $target) {
            if ($this->isLinkTo($relative, $target)) {
                continue;
            }
            $path = $this->absolute($relative);
            Filesystem::remove($path);
            Filesystem::ensureDirectory(\dirname($path));
            if (!symlink($target, $path)) {
                throw new \RuntimeException(\sprintf('Failed to link %s to %s.', $relative, $target));
            }
        }

        foreach ($this->writes as $relative => $content) {
            $path = $this->absolute($relative);
            if (is_file($path) && file_get_contents($path) === $content) {
                continue;
            }
            // A shipped path can turn from a directory into a file.
            if (is_dir($path) && !is_link($path)) {
                Filesystem::remove($path);
            }
            Filesystem::ensureDirectory(\dirname($path));
            if (false === file_put_contents($path, $content)) {
                throw new \RuntimeException(\sprintf('Failed to write %s.', $relative));
            }
        }
    }

    private function isLinkTo(string $relative, string $target): bool
    {
        $path = $this->absolute($relative);

        return is_link($path) && readlink($path) === $target;
    }

    private function absolute(string $relative): string
    {
        return $this->root.'/'.ltrim($relative, '/');
    }
}
