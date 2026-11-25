<?php

namespace Robuust\Boost\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Robuust\Boost\Boost\BoostConfiguration;
use Robuust\Boost\Boost\Tools\SearchCraftDocs;
use Robuust\Boost\Stack;
use Robuust\Boost\Support\Frontmatter;

/**
 * Stack separation in the Boost route (D12).
 *
 * @internal
 */
final class BoostRouteTest extends TestCase
{
    public function testLaravelOnlyProjectGetsNoCraftContent(): void
    {
        $root = $this->project('laravel');
        Stack::useRoot($root);

        $guidelines = $this->renderGuidelines();
        self::assertSame('', $guidelines['craft6'], 'the Craft 6 guideline renders empty, so Boost drops it');
        foreach ($guidelines as $name => $content) {
            self::assertDoesNotMatchRegularExpression('/craft/i', $content, "guideline {$name}");
        }

        $craftSkills = array_map('basename', glob(self::packageRoot().'/resources/craft5/skills/*', GLOB_ONLYDIR) ?: []);
        self::assertNotEmpty($craftSkills);
        foreach ($this->boostSkills() as $name => $frontmatter) {
            self::assertNotContains($name, $craftSkills, 'Craft skills ship only in resources/craft5');
            self::assertDoesNotMatchRegularExpression('/craft/i', $frontmatter, "skill {$name} is not about Craft");
        }

        $config = BoostConfiguration::for(Stack::current(), static fn (): mixed => null);
        self::assertArrayNotHasKey('boost.mcp.tools.include', $config, 'no Craft docs tool');
    }

    public function testLaravelProjectWithoutInertiaGetsNoInertiaContent(): void
    {
        $root = $this->project('laravel');
        Stack::useRoot($root);

        self::assertSame('', $this->renderGuidelines()['inertia']);

        $config = BoostConfiguration::for(Stack::current(), static fn (string $key): mixed => 'boost.skills.exclude' === $key ? ['some-skill'] : null);
        self::assertSame(['some-skill', ...BoostConfiguration::INERTIA_SKILLS], $config['boost.skills.exclude']);
    }

    public function testLaravelInertiaProjectGetsInertiaContent(): void
    {
        $root = $this->project('laravel');
        $composer = (array) json_decode((string) file_get_contents($root.'/composer.json'), true);
        $composer['require']['inertiajs/inertia-laravel'] = '^3.7';
        $this->write($root.'/composer.json', (string) json_encode($composer));
        Stack::useRoot($root);

        self::assertStringContainsString('inertia-best-practices', $this->renderGuidelines()['inertia']);
        self::assertArrayNotHasKey('boost.skills.exclude', BoostConfiguration::for(Stack::current(), static fn (): mixed => null));
    }

    public function testCraft6ProjectWithoutDirectInertiaGetsCraftButNoInertiaContent(): void
    {
        $root = $this->project('craft6');
        Stack::useRoot($root);
        $stack = Stack::current();

        self::assertSame(6, $stack->craftMajor());
        self::assertFalse($stack->usesInertia(), 'Inertia installed only through Craft does not count');

        $guidelines = $this->renderGuidelines();
        self::assertStringContainsString('Craft 6 (beta)', $guidelines['craft6']);
        self::assertSame('', $guidelines['inertia']);

        $config = BoostConfiguration::for($stack, static fn (): mixed => null);
        self::assertSame(BoostConfiguration::INERTIA_SKILLS, $config['boost.skills.exclude']);
        self::assertSame([SearchCraftDocs::class], $config['boost.mcp.tools.include']);
    }

    public function testClaudeCodeGuidelinesGoToAgentsMdUnlessConfigured(): void
    {
        $stack = Stack::fromPath($this->project('laravel'));

        self::assertSame('AGENTS.md', BoostConfiguration::for($stack, static fn (): mixed => null)['boost.agents.claude_code.guidelines_path']);
        self::assertArrayNotHasKey(
            'boost.agents.claude_code.guidelines_path',
            BoostConfiguration::for($stack, static fn (string $key): mixed => 'boost.agents.claude_code.guidelines_path' === $key ? 'CLAUDE.md' : null),
        );
    }

    public function testCoreGuidelineCarriesTheTestAuthorizationRule(): void
    {
        $core = $this->renderGuidelines()['core'];

        self::assertStringContainsString('Run tests only when the user has explicitly requested test execution', $core);
        self::assertStringContainsString('Write, modify or fix tests as part of authorized implementation', $core);
        self::assertStringContainsString('such as Laravel Boost\'s `enforce-tests` guideline', $core);
        self::assertStringContainsString('.ai/rules/index.md', $core);
    }

    /**
     * Render each Boost guideline the way Boost does: Blade for `.blade.php`, as-is for `.md`.
     *
     * @return array<string, string> name => trimmed output
     */
    private function renderGuidelines(): array
    {
        $compiler = new BladeCompiler(new Filesystem(), sys_get_temp_dir());
        $rendered = [];

        foreach (glob(self::packageRoot().'/resources/boost/guidelines/*') ?: [] as $path) {
            $name = (string) preg_replace('/\.(blade\.php|md)$/', '', basename($path));
            $content = (string) file_get_contents($path);

            if (str_ends_with($path, '.blade.php')) {
                ob_start();
                eval('?>'.$compiler->compileString($content));
                $content = (string) ob_get_clean();
            }

            $rendered[$name] = trim($content);
        }

        self::assertSame(['core', 'craft6', 'inertia'], array_keys($rendered));

        return $rendered;
    }

    /**
     * @return array<string, string> skill => its name and description
     */
    private function boostSkills(): array
    {
        $skills = [];
        foreach (glob(self::packageRoot().'/resources/boost/skills/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $parsed = Frontmatter::parse((string) file_get_contents($directory.'/SKILL.md'));
            self::assertNotNull($parsed);
            $skills[basename($directory)] = implode("\n", array_filter($parsed['data'], 'is_string'));
        }

        return $skills;
    }
}
