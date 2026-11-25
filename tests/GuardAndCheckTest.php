<?php

namespace Robuust\Boost\Tests;

use Robuust\Boost\Sync\BoostSync;
use Robuust\Boost\Sync\Filesystem;

/**
 * @internal
 */
final class GuardAndCheckTest extends TestCase
{
    public function testGuardWritesNothingWhenAnNpmPackageIsMissing(): void
    {
        $root = $this->project('craft5');
        $this->write($root.'/robuust-agents.json', '{"agents": ["codex", "claude_code"], "packages": ["@robuust-digital/vue-components"]}');
        self::assertSame(0, $this->cli(['sync', '--root='.$root])['exit']);
        $synced = $this->files($root);

        $package = $root.'/node_modules/@robuust-digital/vue-components';
        $saved = $this->files($package);
        Filesystem::remove($package);
        $before = $this->files($root);

        $result = $this->cli(['sync', '--root='.$root]);

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('@robuust-digital/vue-components is selected in robuust-agents.json but missing from node_modules/', $result['stderr']);
        self::assertStringContainsString('Run `yarn install`', $result['stderr']);
        self::assertSame($before, $this->files($root), 'the guard writes nothing');
        self::assertFileExists($root.'/.agents/skills/vue-components-mcp/SKILL.md', 'the selected skill survives');

        foreach ($saved as $file => $content) {
            $this->write($package.'/'.$file, $content);
        }
        $restored = $this->cli(['sync', '--root='.$root]);

        self::assertSame(0, $restored['exit'], $restored['stderr']);
        self::assertSame($synced, $this->files($root));
    }

    public function testGuardWritesNothingWhenAComposerPackageIsMissing(): void
    {
        $root = $this->project('laravel');
        $runner = new RecordingRunner();

        $result = $this->cli(['sync', '--root='.$root], $runner);

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('robuust/boost is selected in boost.json but missing from vendor/. Run `composer install`', $result['stderr']);
        self::assertSame([], $runner->commands, 'boost:update does not run');
    }

    public function testBoostSyncRunsBoostUpdateAfterTheGuard(): void
    {
        $root = $this->project('laravel');
        $this->write($root.'/vendor/robuust/boost/composer.json', '{}');
        $runner = new RecordingRunner();

        $result = $this->cli(['sync', '--root='.$root], $runner);

        self::assertSame(0, $result['exit']);
        self::assertCount(1, $runner->commands);
        self::assertSame([PHP_BINARY, 'artisan', 'boost:update', '--no-interaction'], $runner->commands[0]['command']);
        self::assertSame($root, $runner->commands[0]['cwd']);
        self::assertSame(['APP_ENV' => 'local'], $runner->commands[0]['env']);
    }

    public function testBoostSyncRemovesTheCraft5Block(): void
    {
        $root = $this->project('craft6');
        $this->write($root.'/boost.json', '{"agents": ["claude_code", "codex"], "packages": ["robuust/boost"]}');
        $this->write($root.'/vendor/robuust/boost/composer.json', '{}');
        $this->write($root.'/AGENTS.md', "# Project\n\nHand-written.\n\n<robuust-agents-guidelines>\nold\n</robuust-agents-guidelines>\n\nFooter.\n");
        $seen = null;
        $runner = new RecordingRunner(static function (string $cwd) use (&$seen): void {
            $seen = file_get_contents($cwd.'/AGENTS.md');
        });

        self::assertSame(0, $this->cli(['sync', '--root='.$root], $runner)['exit']);

        self::assertSame("# Project\n\nHand-written.\n\nFooter.\n", $seen, 'the block is gone before boost:update runs');
    }

    public function testCraft5CheckWritesNothingAndFailsOnADiff(): void
    {
        $root = $this->project('craft5');
        $before = $this->files($root);

        $result = $this->cli(['sync', '--check', '--root='.$root]);

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('Generated agent files are out of date', $result['stderr']);
        self::assertStringContainsString('AGENTS.md', $result['stderr']);
        self::assertStringContainsString('robuust-agents.json', $result['stderr']);
        self::assertSame($before, $this->files($root));

        self::assertSame(0, $this->cli(['sync', '--root='.$root])['exit']);
        $clean = $this->cli(['sync', '--check', '--root='.$root]);
        self::assertSame(0, $clean['exit'], $clean['stderr']);

        $this->write($root.'/.agents/skills/fe-craft/SKILL.md', 'edited by hand');
        $edited = $this->cli(['sync', '--check', '--root='.$root]);
        self::assertSame(1, $edited['exit']);
        self::assertStringContainsString('.agents/skills/fe-craft/SKILL.md', $edited['stderr']);
        self::assertSame('edited by hand', file_get_contents($root.'/.agents/skills/fe-craft/SKILL.md'));
    }

    public function testBoostCheckReportsChangesAndRestoresFiles(): void
    {
        $root = $this->project('laravel');
        $this->write($root.'/vendor/robuust/boost/composer.json', '{}');
        $this->write($root.'/AGENTS.md', "old\n");
        $before = $this->files($root);

        $runner = new RecordingRunner(static function (string $cwd): void {
            file_put_contents($cwd.'/AGENTS.md', "new\n");
            mkdir($cwd.'/.claude/skills/new-skill', 0o755, true);
            file_put_contents($cwd.'/.claude/skills/new-skill/SKILL.md', 'new');
        });

        $result = $this->cli(['sync', '--check', '--root='.$root], $runner);

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('AGENTS.md', $result['stderr']);
        self::assertStringContainsString('.claude/skills/new-skill/SKILL.md', $result['stderr']);
        self::assertSame($before, $this->files($root), 'check restores every generated file');
    }

    public function testBoostCheckRestoresModesAndDirectories(): void
    {
        $root = $this->project('laravel');
        $this->write($root.'/vendor/robuust/boost/composer.json', '{}');
        $this->write($root.'/.claude/skills/tool/SKILL.md', "skill\n");
        $this->write($root.'/.claude/skills/tool/scripts/run.sh', "#!/bin/sh\n");
        chmod($root.'/.claude/skills/tool/scripts/run.sh', 0o755);
        mkdir($root.'/.claude/skills/empty/nested', 0o755, true);
        chmod($root.'/.claude/skills/empty', 0o700);
        $before = $this->tree($root);

        $runner = new RecordingRunner(static function (string $cwd): void {
            // Rewrite the skills the way a copy does: delete, then write with default permissions.
            Filesystem::remove($cwd.'/.claude/skills');
            mkdir($cwd.'/.claude/skills/tool/scripts', 0o777, true);
            file_put_contents($cwd.'/.claude/skills/tool/SKILL.md', "skill\n");
            file_put_contents($cwd.'/.claude/skills/tool/scripts/run.sh', "#!/bin/sh\n");
            chmod($cwd.'/.claude/skills/tool/scripts/run.sh', 0o644);
            mkdir($cwd.'/.codex/new', 0o777, true);
            file_put_contents($cwd.'/.codex/config.toml', "[mcp_servers]\n");
        });

        $result = $this->cli(['sync', '--check', '--root='.$root], $runner);

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('.claude/skills/tool/scripts/run.sh', $result['stderr'], 'a lost executable bit is a change');
        self::assertStringContainsString('.codex/config.toml', $result['stderr']);
        self::assertStringNotContainsString('.claude/skills/empty', $result['stderr'], 'directories alone are no change');
        self::assertStringNotContainsString('SKILL.md', $result['stderr']);
        self::assertSame($before, $this->tree($root), 'check restores content, modes and directories');
    }

    public function testBoostCheckLeavesUnchangedPathsAlone(): void
    {
        $root = $this->project('laravel');
        $this->write($root.'/vendor/robuust/boost/composer.json', '{}');
        $this->write($root.'/.claude/skills/tool/SKILL.md', "skill\n");
        $inode = fileinode($root.'/.claude/skills/tool/SKILL.md');

        $result = $this->cli(['sync', '--check', '--root='.$root], new RecordingRunner(static function (string $cwd): void {
            file_put_contents($cwd.'/AGENTS.md', "new\n");
        }));

        self::assertSame(1, $result['exit']);
        self::assertFileDoesNotExist($root.'/AGENTS.md');
        clearstatcache();
        self::assertSame($inode, fileinode($root.'/.claude/skills/tool/SKILL.md'), 'an unchanged file is not rewritten');
    }

    public function testBoostCheckRefusesAgentsItCannotMap(): void
    {
        $root = $this->project('laravel');
        $this->write($root.'/vendor/robuust/boost/composer.json', '{}');
        $this->write($root.'/boost.json', '{"agents": ["claude_code", "future_agent"], "packages": ["robuust/boost"]}');
        $before = $this->tree($root);
        $runner = new RecordingRunner();

        $result = $this->cli(['sync', '--check', '--root='.$root], $runner);

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('cannot check the agent(s) future_agent in boost.json', $result['stderr']);
        self::assertSame([], $runner->commands, 'boost:update does not run');
        self::assertSame($before, $this->tree($root));
    }

    public function testBoostCheckCoversEveryAgentBoostShips(): void
    {
        $agents = [];
        foreach (glob(self::packageRoot().'/vendor/laravel/boost/src/Install/Agents/*.php') ?: [] as $file) {
            if (preg_match("/function name\\(\\): string\\s*\\{\\s*return '([a-z_]+)';/", (string) file_get_contents($file), $match)) {
                $agents[] = $match[1];
            }
        }
        sort($agents);
        $known = array_keys(BoostSync::AGENT_PATHS);
        sort($known);

        self::assertNotEmpty($agents);
        self::assertSame($agents, $known);
    }

    public function testBoostCheckListsEveryPathAnAgentCanChoose(): void
    {
        foreach (glob(self::packageRoot().'/vendor/laravel/boost/src/Install/Agents/*.php') ?: [] as $file) {
            $source = (string) file_get_contents($file);
            if (!preg_match("/function name\\(\\): string\\s*\\{\\s*return '([a-z_]+)';/", $source, $name)) {
                continue;
            }

            // Every literal in the path methods, including the candidates of a file_exists() choice.
            preg_match_all('/function (?:mcpConfigPath|guidelinesPath|skillsPath)\(\): \??string\s*\{(.*?)\n    \}/s', $source, $bodies);
            preg_match_all("/'([^']+)'/", implode("\n", $bodies[1]), $literals);
            $paths = array_values(array_filter($literals[1], static fn (string $literal): bool => !str_starts_with($literal, 'boost.')));

            self::assertNotEmpty($paths, $name[1]);
            foreach ($paths as $path) {
                self::assertContains($path, BoostSync::AGENT_PATHS[$name[1]], \sprintf('%s can write %s', $name[1], $path));
            }
        }
    }

    public function testBoostCheckCoversBothOpenCodeConfigFiles(): void
    {
        $root = $this->project('laravel');
        $this->write($root.'/vendor/robuust/boost/composer.json', '{}');
        $this->write($root.'/boost.json', '{"agents": ["opencode"], "packages": ["robuust/boost"]}');
        $before = $this->tree($root);

        $result = $this->cli(['sync', '--check', '--root='.$root], new RecordingRunner(static function (string $cwd): void {
            file_put_contents($cwd.'/opencode.json', "{\"mcp\": {}}\n");
        }));

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('opencode.json', $result['stderr']);
        self::assertFileDoesNotExist($root.'/opencode.json');
        self::assertSame($before, $this->tree($root));
    }

    public function testBoostCheckRestoresTheRulesIndex(): void
    {
        $root = $this->project('laravel');
        $this->write($root.'/vendor/robuust/boost/composer.json', '{}');
        $this->write($root.'/.ai/rules/index.md', "# Rules\n\n- hand-written.md\n");
        $before = $this->tree($root);

        $result = $this->cli(['sync', '--check', '--root='.$root], new RecordingRunner(static function (string $cwd): void {
            file_put_contents($cwd.'/.ai/rules/index.md', "# Rules\n\n- boost/laravel.md\n");
        }));

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('.ai/rules/index.md', $result['stderr']);
        self::assertSame("# Rules\n\n- hand-written.md\n", file_get_contents($root.'/.ai/rules/index.md'));
        self::assertSame($before, $this->tree($root));
    }

    public function testBoostCheckPassesWhenBoostChangesNothing(): void
    {
        $root = $this->project('laravel');
        $this->write($root.'/vendor/robuust/boost/composer.json', '{}');

        $result = $this->cli(['sync', '--check', '--root='.$root], new RecordingRunner());

        self::assertSame(0, $result['exit'], $result['stderr']);
    }

    public function testUnsupportedProjectFails(): void
    {
        $root = $this->project('craft6');
        unlink($root.'/artisan');

        $result = $this->cli(['sync', '--root='.$root]);

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('Unsupported project', $result['stderr']);
    }

    /**
     * Every file, symlink and directory below a root, with its type, permission bits and content.
     *
     * @return array<string, string>
     */
    private function tree(string $root): array
    {
        $tree = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        clearstatcache();

        foreach ($iterator as $path => $info) {
            \assert($info instanceof \SplFileInfo);
            $relative = substr((string) $path, \strlen($root) + 1);
            $tree[$relative] = match (true) {
                $info->isLink() => 'link '.$info->getLinkTarget(),
                $info->isDir() => \sprintf('dir %o', $info->getPerms() & 0o7777),
                default => \sprintf('file %o %s', $info->getPerms() & 0o7777, md5((string) file_get_contents((string) $path))),
            };
        }

        ksort($tree, SORT_STRING);

        return $tree;
    }
}
