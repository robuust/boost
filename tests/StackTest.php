<?php

namespace Robuust\Boost\Tests;

use Robuust\Boost\Stack;
use Robuust\Boost\Sync\Guard;

/**
 * @internal
 */
final class StackTest extends TestCase
{
    public function testDetectsTheRoute(): void
    {
        self::assertSame(Stack::KIND_CRAFT5, Stack::fromPath(self::fixture('craft5'))->kind());
        self::assertSame(Stack::KIND_BOOST, Stack::fromPath(self::fixture('laravel'))->kind());
        self::assertSame(Stack::KIND_BOOST, Stack::fromPath(self::fixture('craft6'))->kind());
    }

    public function testReadsTheCraftMajorFromLockAndAlias(): void
    {
        self::assertSame(5, Stack::fromPath(self::fixture('craft5'))->craftMajor());
        self::assertSame(6, Stack::fromPath(self::fixture('craft6'))->craftMajor());
        self::assertNull(Stack::fromPath(self::fixture('laravel'))->craftMajor());
    }

    public function testTellsComposerAndNpmPackagesApart(): void
    {
        $stack = Stack::fromPath(self::fixture('craft5'));

        self::assertTrue(Guard::isNpm($stack, '@robuust-digital/vue-components'));
        self::assertTrue(Guard::isNpm($stack, 'tailwindcss'));
        self::assertFalse(Guard::isNpm($stack, 'spatie/laravel-permission'));
        self::assertSame('yarn install', $stack->npmInstallCommand());
        self::assertSame('vendor/bin', $stack->binDir());
    }
}
