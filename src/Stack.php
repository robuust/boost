<?php

namespace Robuust\Boost;

use Robuust\Boost\Support\Json;

/**
 * Reads a project's manifests to tell which stack and packages it uses.
 *
 * Detection reads `composer.json`, `composer.lock` and `package.json` only, so
 * it needs no framework bootstrap.
 */
final class Stack
{
    public const KIND_BOOST = 'boost';

    public const KIND_CRAFT5 = 'craft5';

    private static ?string $currentRoot = null;

    /** @var array<string, self> */
    private static array $cache = [];

    /**
     * @param array<string, mixed>  $composer    composer.json
     * @param array<string, string> $locked      package name => locked version
     * @param array<string, mixed>  $packageJson package.json
     */
    private function __construct(
        private readonly string $root,
        private readonly array $composer,
        private readonly array $locked,
        private readonly array $packageJson,
    ) {
    }

    /**
     * Read the stack of the project at the given root.
     */
    public static function fromPath(string $root): self
    {
        $root = rtrim($root, '/\\');
        $composer = Json::readFile($root.'/composer.json') ?? [];
        $lock = Json::readFile($root.'/composer.lock') ?? [];
        $packageJson = Json::readFile($root.'/package.json') ?? [];

        $locked = [];
        foreach (['packages', 'packages-dev'] as $section) {
            $packages = $lock[$section] ?? [];
            if (!\is_array($packages)) {
                continue;
            }
            foreach ($packages as $package) {
                if (\is_array($package) && \is_string($package['name'] ?? null)) {
                    $locked[$package['name']] = \is_string($package['version'] ?? null) ? $package['version'] : '';
                }
            }
        }

        return new self($root, $composer, $locked, $packageJson);
    }

    /**
     * Set the project root that {@see self::current()} reads, for example Laravel's base path.
     */
    public static function useRoot(?string $root): void
    {
        self::$currentRoot = $root;
        self::$cache = [];
    }

    /**
     * The stack of the current project, cached per root. Used by Blade guidelines.
     */
    public static function current(): self
    {
        $root = self::$currentRoot ?? (string) getcwd();

        return self::$cache[$root] ??= self::fromPath($root);
    }

    public function root(): string
    {
        return $this->root;
    }

    /**
     * Absolute path of a project-relative path.
     */
    public function path(string $relative = ''): string
    {
        return '' === $relative ? $this->root : $this->root.'/'.ltrim($relative, '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function composerJson(): array
    {
        return $this->composer;
    }

    /**
     * Whether the root composer.json requires the package directly.
     */
    public function requires(string $package): bool
    {
        return null !== $this->constraint($package);
    }

    /**
     * Whether the root package.json lists the package as a dependency.
     */
    public function requiresNpm(string $package): bool
    {
        return null !== $this->npmConstraint($package);
    }

    public function constraint(string $package): ?string
    {
        foreach (['require', 'require-dev'] as $section) {
            $value = $this->composer[$section][$package] ?? null;
            if (\is_string($value)) {
                return $value;
            }
        }

        return null;
    }

    public function npmConstraint(string $package): ?string
    {
        foreach (['dependencies', 'devDependencies'] as $section) {
            $value = $this->packageJson[$section][$package] ?? null;
            if (\is_string($value)) {
                return $value;
            }
        }

        return null;
    }

    public function lockedVersion(string $package): ?string
    {
        return $this->locked[$package] ?? null;
    }

    /**
     * The Craft CMS major the project runs, from the lock file first and the constraint second.
     */
    public function craftMajor(): ?int
    {
        $constraint = $this->constraint('craftcms/cms');
        $locked = $this->lockedVersion('craftcms/cms');

        if (null === $constraint && null === $locked) {
            return null;
        }

        foreach ([$locked, $constraint] as $version) {
            if (null === $version) {
                continue;
            }
            // An inline alias such as "dev-branch as 6.0.0-beta.1" names the version it stands for.
            if (1 === preg_match('/\bas\s+v?(\d+)/', $version, $matches)) {
                return (int) $matches[1];
            }
            if (1 === preg_match('/^\s*(?:[~^]|[<>]=?|=)?\s*v?(\d+)/', $version, $matches)) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    /**
     * Whether the project itself requires Inertia, not only through Craft 6.
     */
    public function usesInertia(): bool
    {
        return $this->requires('inertiajs/inertia-laravel');
    }

    public function usesBoost(): bool
    {
        return $this->requires('laravel/boost') && is_file($this->path('artisan'));
    }

    /**
     * Which sync route applies: Laravel Boost, the Craft 5 writer, or none.
     */
    public function kind(): ?string
    {
        if ($this->usesBoost()) {
            return self::KIND_BOOST;
        }

        if (5 === $this->craftMajor()) {
            return self::KIND_CRAFT5;
        }

        return null;
    }

    /**
     * The Composer vendor directory, relative to the root.
     */
    public function vendorDir(): string
    {
        $dir = $this->composer['config']['vendor-dir'] ?? 'vendor';

        return \is_string($dir) ? rtrim($dir, '/') : 'vendor';
    }

    /**
     * The Composer bin directory, relative to the root.
     */
    public function binDir(): string
    {
        $dir = $this->composer['config']['bin-dir'] ?? null;

        return \is_string($dir) ? rtrim($dir, '/') : $this->vendorDir().'/bin';
    }

    /**
     * The command that installs the project's npm packages.
     */
    public function npmInstallCommand(): string
    {
        return match (true) {
            is_file($this->path('yarn.lock')) => 'yarn install',
            is_file($this->path('pnpm-lock.yaml')) => 'pnpm install',
            is_file($this->path('bun.lock')), is_file($this->path('bun.lockb')) => 'bun install',
            default => 'npm install',
        };
    }
}
