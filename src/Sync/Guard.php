<?php

namespace Robuust\Boost\Sync;

use Robuust\Boost\Stack;

/**
 * Checks that every package selected in a project's agent config is installed.
 *
 * Sync runs this before writing anything, so a Composer update that runs before
 * `yarn install` cannot drop a selected npm package and its skills.
 */
final class Guard
{
    /**
     * @param list<string> $packages
     *
     * @return list<string> one message per missing package
     */
    public static function missing(Stack $stack, array $packages, string $configFile): array
    {
        $messages = [];

        foreach ($packages as $package) {
            if (null !== self::installedPath($stack, $package)) {
                continue;
            }

            if (self::isNpm($stack, $package)) {
                $messages[] = \sprintf(
                    '%s is selected in %s but missing from node_modules/. Run `%s` and sync again.',
                    $package,
                    $configFile,
                    $stack->npmInstallCommand(),
                );

                continue;
            }

            $messages[] = \sprintf(
                '%s is selected in %s but missing from %s/. Run `composer install` and sync again.',
                $package,
                $configFile,
                $stack->vendorDir(),
            );
        }

        return $messages;
    }

    /**
     * The absolute path of an installed package, or null when it is not installed.
     */
    public static function installedPath(Stack $stack, string $package): ?string
    {
        $npm = $stack->path('node_modules/'.$package);
        $composer = $stack->path($stack->vendorDir().'/'.$package);

        $candidates = self::isNpm($stack, $package)
            ? [[$npm, 'package.json']]
            : [[$composer, 'composer.json'], [$npm, 'package.json']];

        foreach ($candidates as [$path, $manifest]) {
            if (is_file($path.'/'.$manifest)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Whether a selected package name refers to an npm package.
     */
    public static function isNpm(Stack $stack, string $package): bool
    {
        if ($stack->requiresNpm($package) || str_starts_with($package, '@')) {
            return true;
        }

        if ($stack->requires($package)) {
            return false;
        }

        // Composer names always have a vendor prefix; unscoped npm names never do.
        return !str_contains($package, '/');
    }
}
