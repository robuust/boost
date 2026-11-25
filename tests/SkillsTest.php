<?php

namespace Robuust\Boost\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Robuust\Boost\Support\Frontmatter;
use Robuust\Boost\Sync\Filesystem;

/**
 * Static validation of the shipped skills and guidelines.
 *
 * @internal
 */
final class SkillsTest extends TestCase
{
    #[DataProvider('skills')]
    public function testFrontmatterNamesTheFolderAndDescribesTheSkill(string $directory): void
    {
        $parsed = Frontmatter::parse((string) file_get_contents($directory.'/SKILL.md'));

        self::assertNotNull($parsed, 'SKILL.md has frontmatter');
        self::assertSame(basename($directory), $parsed['data']['name'] ?? null);
        self::assertIsString($parsed['data']['description'] ?? null);
        self::assertNotSame('', trim($parsed['data']['description']));
    }

    #[DataProvider('skills')]
    public function testLocalLinksResolve(string $directory): void
    {
        foreach (Filesystem::files($directory) as $file) {
            if (!str_ends_with($file, '.md')) {
                continue;
            }

            $content = (string) file_get_contents($directory.'/'.$file);
            preg_match_all('/\]\(([^)\s]+)\)/', $content, $matches);

            foreach ($matches[1] as $target) {
                if (preg_match('#^[a-z]+:#', $target) || str_starts_with($target, '#')) {
                    continue;
                }

                $path = \dirname($directory.'/'.$file).'/'.explode('#', $target)[0];
                self::assertFileExists($path, "{$file} links to {$target}");
            }
        }
    }

    #[DataProvider('skills')]
    public function testNoLegacyMcpOrRepositoryPaths(string $directory): void
    {
        foreach (Filesystem::files($directory) as $file) {
            $content = (string) file_get_contents($directory.'/'.$file);

            self::assertStringNotContainsString('craft_twig_rules', $content, $file);
            self::assertStringNotContainsString('agents/templates/org', $content, $file);
            self::assertStringNotContainsString('robuust/robuust-agents', $content, $file);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function skills(): iterable
    {
        foreach (['boost', 'craft5'] as $route) {
            foreach (glob(self::packageRoot().'/resources/'.$route.'/skills/*', GLOB_ONLYDIR) ?: [] as $directory) {
                yield $route.'/'.basename($directory) => [$directory];
            }
        }
    }

    public function testInertiaDecisionsGoToThisRepository(): void
    {
        $skill = self::packageRoot().'/resources/boost/skills/inertia-best-practices';

        self::assertStringContainsString('to `robuust/boost` as a pull request', (string) file_get_contents($skill.'/SKILL.md'));
        self::assertStringContainsString('`resources/boost/skills/inertia-best-practices/` in [robuust/boost]', (string) file_get_contents($skill.'/references/record-a-decision.md'));
    }

    public function testCraft5BlockStaysSmall(): void
    {
        $guidelines = (new \Robuust\Boost\Sync\Craft5Sync(self::packageRoot()))->guidelines();

        self::assertLessThan(8000, \strlen($guidelines));
    }
}
