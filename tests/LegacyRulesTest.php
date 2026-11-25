<?php

namespace Robuust\Boost\Tests;

use Robuust\Boost\Rules\LegacyRules;
use Robuust\Boost\Rules\RulesIndex;

/**
 * @internal
 */
final class LegacyRulesTest extends TestCase
{
    private const LEGACY_INDEX = "# Project rules\n\nPath-scoped project conventions. Local rules override shared skills.\n\n"
        ."| Glob | Rule | Note |\n| --- | --- | --- |\n"
        ."| `src/css/**` | [Use prefixed form APIs](./src-css.md) | Expose form styling through `form-*` classes. |\n"
        ."| `src/css/*.css` | [Keep tokens in theme](./src-css.md) | Define design tokens in the theme file. |\n"
        ."| `templates/_partials/**` | [One partial per block](./templates-partials.md) | Render each block through its own partial. |\n"
        ."| `../outside/**` | [Escape](../outside.md) | Never written. |\n";

    public function testCraft5SyncMovesLegacyGlobsIntoFrontmatter(): void
    {
        $root = $this->legacyProject();

        self::assertSame(0, $this->cli(['sync', '--root='.$root])['exit']);

        self::assertSame(
            "---\npaths:\n  - 'src/css/**'\n  - 'src/css/*.css'\n---\n\n# Src css\n\n"
            ."## Use prefixed form APIs\n\nExpose form styling through `form-*` classes.\n\n"
            ."## Keep tokens in theme\n\nDefine design tokens in the theme file.\n",
            file_get_contents($root.'/.ai/rules/src-css.md'),
        );
        self::assertSame(
            "---\npaths:\n  - 'templates/_partials/**'\n---\n\n# Templates partials\n\n"
            ."## One partial per block\n\nRender each block through its own partial.\n",
            file_get_contents($root.'/.ai/rules/templates-partials.md'),
            'a rule file the index lists but that is missing is recreated from the index',
        );
        self::assertFileDoesNotExist($root.'/.ai/outside.md', 'files outside .ai/rules are never written');

        $index = (string) file_get_contents($root.'/'.RulesIndex::INDEX);
        self::assertStringContainsString('| src/css/**, src/css/*.css | .ai/rules/src-css.md |', $index);
        self::assertStringContainsString('| templates/_partials/** | .ai/rules/templates-partials.md |', $index);
        self::assertStringContainsString('| templates/** | .ai/rules/templates.md |', $index, 'Boost-format rules stay indexed');

        $files = $this->files($root);
        self::assertSame(0, $this->cli(['sync', '--check', '--root='.$root])['exit']);
        self::assertSame(0, $this->cli(['sync', '--root='.$root])['exit']);
        self::assertSame($files, $this->files($root), 'the migration runs once');
    }

    public function testCraft5CheckReportsTheMigrationAndWritesNothing(): void
    {
        $root = $this->legacyProject();
        self::assertSame(0, $this->cli(['sync', '--root='.$root])['exit']);
        $this->write($root.'/'.RulesIndex::INDEX, self::LEGACY_INDEX);
        $this->write($root.'/.ai/rules/src-css.md', "## Use prefixed form APIs\n\nExpose form styling through `form-*` classes.\n");
        $before = $this->files($root);

        $result = $this->cli(['sync', '--check', '--root='.$root]);

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('.ai/rules/src-css.md', $result['stderr']);
        self::assertStringContainsString(RulesIndex::INDEX, $result['stderr']);
        self::assertSame($before, $this->files($root));
    }

    public function testIndexRulesMigratesLegacyRules(): void
    {
        $root = $this->legacyProject();

        $result = $this->cli(['index-rules', '--root='.$root]);

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString('Migrated legacy rules: .ai/rules/src-css.md, .ai/rules/templates-partials.md.', $result['stdout']);
        self::assertStringNotContainsString('src-css.md', $result['stderr']);
        self::assertStringContainsString('| src/css/**, src/css/*.css | .ai/rules/src-css.md |', (string) file_get_contents($root.'/'.RulesIndex::INDEX));
    }

    public function testFilesWithFrontmatterAndBoostIndexesAreLeftAlone(): void
    {
        $root = $this->legacyProject();
        $this->write($root.'/.ai/rules/src-css.md', "---\npaths:\n  - 'src/css/**'\n---\n\n# Css\n");

        self::assertArrayNotHasKey('.ai/rules/src-css.md', LegacyRules::migration($root));

        $this->write($root.'/'.RulesIndex::INDEX, RulesIndex::render($root));
        self::assertSame([], LegacyRules::migration($root), 'a Boost-format index is not legacy');
    }

    public function testBoostSyncMigratesBeforeBoostUpdate(): void
    {
        $root = $this->project('laravel');
        $this->write($root.'/vendor/robuust/boost/composer.json', '{}');
        $this->write($root.'/'.RulesIndex::INDEX, self::LEGACY_INDEX);
        $this->write($root.'/.ai/rules/src-css.md', "## Use prefixed form APIs\n\nExpose form styling through `form-*` classes.\n");
        $before = $this->files($root);
        $seen = null;
        $runner = new RecordingRunner(static function (string $cwd) use (&$seen): void {
            $seen = file_get_contents($cwd.'/.ai/rules/src-css.md');
        });

        $check = $this->cli(['sync', '--check', '--root='.$root], $runner);

        self::assertSame(1, $check['exit']);
        self::assertStringContainsString('.ai/rules/src-css.md', $check['stderr']);
        self::assertSame($before, $this->files($root), 'check restores the rule files');
        self::assertIsString($seen);
        self::assertStringStartsWith("---\npaths:\n  - 'src/css/**'\n", $seen, 'boost:update sees the migrated file');

        self::assertSame(0, $this->cli(['sync', '--root='.$root], new RecordingRunner())['exit']);
        self::assertStringStartsWith("---\npaths:\n", (string) file_get_contents($root.'/.ai/rules/src-css.md'));
    }

    private function legacyProject(): string
    {
        $root = $this->project('craft5');
        $this->write($root.'/'.RulesIndex::INDEX, self::LEGACY_INDEX);
        $this->write($root.'/.ai/rules/src-css.md', "## Use prefixed form APIs\n\nExpose form styling through `form-*` classes.\n\n## Keep tokens in theme\n\nDefine design tokens in the theme file.\n");

        return $root;
    }
}
