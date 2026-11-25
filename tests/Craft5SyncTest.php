<?php

namespace Robuust\Boost\Tests;

use Robuust\Boost\Stack;
use Robuust\Boost\Sync\Changeset;
use Robuust\Boost\Sync\Craft5Config;
use Robuust\Boost\Sync\Craft5Sync;

/**
 * @internal
 */
final class Craft5SyncTest extends TestCase
{
    public function testFirstRunWritesTheBoostLayout(): void
    {
        $root = $this->project('craft5');
        $this->write($root.'/robuust-agents.json', '{"agents": ["codex", "claude_code"], "packages": ["@robuust-digital/vue-components"]}');

        $result = $this->cli(['sync', '--root='.$root]);

        self::assertSame(0, $result['exit'], $result['stderr']);

        $agents = (string) file_get_contents($root.'/AGENTS.md');
        self::assertStringStartsWith("# Fixture project\n\nHand-written rule: never touch the `web/cpresources` folder.\n\n<robuust-agents-guidelines>\n", $agents);
        self::assertStringContainsString('=== robuust/boost/core rules ===', $agents);
        self::assertStringContainsString('=== robuust/boost/craft5/craft5 rules ===', $agents);
        self::assertStringContainsString('Run tests only when the user has explicitly requested test execution', $agents);
        self::assertStringContainsString('such as Laravel Boost\'s `enforce-tests` guideline', $agents);
        self::assertStringEndsWith("</robuust-agents-guidelines>\n", $agents);
        self::assertStringNotContainsString('laravel-boost-guidelines', $agents);

        self::assertSame("@AGENTS.md\n", file_get_contents($root.'/CLAUDE.md'));

        self::assertFileExists($root.'/.agents/skills/fe-craft/SKILL.md');
        self::assertFileExists($root.'/.agents/skills/seo-patterns-craft/references/canonical-and-meta.md');
        self::assertFileExists($root.'/.agents/skills/vue-components-mcp/SKILL.md', 'skills from selected packages are synced');
        $manifest = json_decode((string) file_get_contents($root.'/.agents/skills/'.Craft5Sync::MANIFEST), true);
        self::assertIsArray($manifest);
        self::assertContains('vue-components-mcp', $manifest['skills']);
        self::assertContains('infer-craft-conventions', $manifest['skills']);

        self::assertTrue(is_link($root.'/.claude/skills'), 'Claude Code reads the Codex skills through a symlink');
        self::assertSame('../.agents/skills', readlink($root.'/.claude/skills'));
        self::assertFileExists($root.'/.claude/skills/fe-craft/SKILL.md');

        self::assertSame(
            "[mcp_servers.robuust]\ncommand = \"php\"\nargs = [\"vendor/bin/robuust-agents\", \"mcp\"]\n",
            file_get_contents($root.'/.codex/config.toml'),
        );
        $mcp = json_decode((string) file_get_contents($root.'/.mcp.json'), true);
        self::assertSame(['command' => 'php', 'args' => ['vendor/bin/robuust-agents', 'mcp']], $mcp['mcpServers']['robuust']);

        self::assertStringContainsString('| templates/** | .ai/rules/templates.md |', (string) file_get_contents($root.'/.ai/rules/index.md'));
    }

    public function testMissingConfigIsCreatedWithBothAgents(): void
    {
        $root = $this->project('craft5');

        self::assertSame(0, $this->cli(['sync', '--root='.$root])['exit']);

        self::assertSame(
            ['agents' => ['codex', 'claude_code'], 'packages' => []],
            json_decode((string) file_get_contents($root.'/'.Craft5Config::FILE), true),
        );
        self::assertDirectoryDoesNotExist($root.'/.agents/skills/vue-components-mcp', 'unselected packages are not synced');
    }

    public function testSecondRunChangesNothing(): void
    {
        $root = $this->project('craft5');
        self::assertSame(0, $this->cli(['sync', '--root='.$root])['exit']);
        $first = $this->files($root);

        $second = $this->cli(['sync', '--root='.$root]);

        self::assertSame(0, $second['exit']);
        self::assertStringContainsString('Agent files are up to date.', $second['stdout']);
        self::assertSame($first, $this->files($root));
        self::assertSame([], (new Craft5Sync(self::packageRoot()))->plan(Stack::fromPath($root), Craft5Config::load($root))->changes());
    }

    public function testHandWrittenTextAndExistingRulesArePreserved(): void
    {
        $root = $this->project('craft5');
        $this->write($root.'/CLAUDE.md', "# Claude notes\n\nPrefer short answers.\n");
        $this->write($root.'/.mcp.json', "{\n  \"mcpServers\": {\n    \"other\": {\"command\": \"node\", \"args\": []}\n  }\n}\n");
        $this->write($root.'/.codex/config.toml', "model = \"gpt-5\"\n\n[mcp_servers.robuust]\ncommand = \"old\"\n\n[mcp_servers.other]\ncommand = \"node\"\n");
        $rule = (string) file_get_contents($root.'/.ai/rules/templates.md');

        self::assertSame(0, $this->cli(['sync', '--root='.$root])['exit']);
        // Hand-written text inside AGENTS.md around a changed block survives a re-sync.
        $agents = (string) file_get_contents($root.'/AGENTS.md');
        $this->write($root.'/AGENTS.md', str_replace('=== robuust/boost/core rules ===', 'stale', $agents)."\nHand-written footer.\n");
        self::assertSame(0, $this->cli(['sync', '--root='.$root])['exit']);

        $agents = (string) file_get_contents($root.'/AGENTS.md');
        self::assertStringStartsWith("# Fixture project\n\nHand-written rule:", $agents);
        self::assertStringEndsWith("</robuust-agents-guidelines>\n\nHand-written footer.\n", $agents);
        self::assertStringNotContainsString('stale', $agents);
        self::assertSame(1, substr_count($agents, '<robuust-agents-guidelines>'));

        self::assertSame("@AGENTS.md\n\n# Claude notes\n\nPrefer short answers.\n", file_get_contents($root.'/CLAUDE.md'));
        self::assertSame($rule, file_get_contents($root.'/.ai/rules/templates.md'));
        self::assertSame("# Notes without paths\n", file_get_contents($root.'/.ai/rules/notes.md'));

        $mcp = json_decode((string) file_get_contents($root.'/.mcp.json'), true);
        self::assertSame(['command' => 'node', 'args' => []], $mcp['mcpServers']['other']);
        self::assertArrayHasKey('robuust', $mcp['mcpServers']);

        self::assertSame(
            "model = \"gpt-5\"\n\n[mcp_servers.robuust]\ncommand = \"php\"\nargs = [\"vendor/bin/robuust-agents\", \"mcp\"]\n\n[mcp_servers.other]\ncommand = \"node\"\n",
            file_get_contents($root.'/.codex/config.toml'),
        );
    }

    public function testStaleSkillIsRemovedAndHandWrittenSkillsAreKept(): void
    {
        $root = $this->project('craft5');
        $this->write($root.'/robuust-agents.json', '{"agents": ["codex", "claude_code"], "packages": ["@robuust-digital/vue-components"]}');
        self::assertSame(0, $this->cli(['sync', '--root='.$root])['exit']);
        $this->write($root.'/.agents/skills/my-own-skill/SKILL.md', "---\nname: my-own-skill\ndescription: Hand-written.\n---\n");
        $this->write($root.'/.agents/skills/fe-craft/extra.md', 'A file the package does not ship.');

        // The project deselects the package on purpose: its skill is stale now.
        $this->write($root.'/robuust-agents.json', '{"agents": ["codex", "claude_code"], "packages": []}');
        self::assertSame(0, $this->cli(['sync', '--root='.$root])['exit']);

        self::assertDirectoryDoesNotExist($root.'/.agents/skills/vue-components-mcp');
        self::assertDirectoryDoesNotExist($root.'/.claude/skills/vue-components-mcp');
        self::assertFileExists($root.'/.agents/skills/my-own-skill/SKILL.md');
        self::assertFileDoesNotExist($root.'/.agents/skills/fe-craft/extra.md');
        self::assertFileExists($root.'/.agents/skills/fe-craft/SKILL.md');
    }

    public function testOnlySelectedAgentsAreConfigured(): void
    {
        $root = $this->project('craft5');
        $this->write($root.'/robuust-agents.json', '{"agents": ["codex"], "packages": []}');

        self::assertSame(0, $this->cli(['sync', '--root='.$root])['exit']);

        self::assertFileExists($root.'/.codex/config.toml');
        self::assertDirectoryExists($root.'/.agents/skills/fe-craft');
        self::assertFileDoesNotExist($root.'/CLAUDE.md');
        self::assertFileDoesNotExist($root.'/.mcp.json');
        self::assertDirectoryDoesNotExist($root.'/.claude/skills');
    }

    public function testClaudeCodeAloneGetsItsOwnSkillsDirectory(): void
    {
        $root = $this->project('craft5');
        $this->write($root.'/robuust-agents.json', '{"agents": ["codex", "claude_code"], "packages": []}');
        self::assertSame(0, $this->cli(['sync', '--root='.$root])['exit']);

        $this->write($root.'/robuust-agents.json', '{"agents": ["claude_code"], "packages": []}');
        self::assertSame(0, $this->cli(['sync', '--root='.$root])['exit']);

        self::assertFalse(is_link($root.'/.claude/skills'));
        self::assertFileExists($root.'/.claude/skills/fe-craft/SKILL.md');
        self::assertFileExists($root.'/.claude/skills/'.Craft5Sync::MANIFEST);
        self::assertDirectoryDoesNotExist($root.'/.agents/skills/fe-craft', 'deselecting Codex retires its generated skills');
        self::assertFileDoesNotExist($root.'/.agents/skills/'.Craft5Sync::MANIFEST);
        self::assertFileDoesNotExist($root.'/.codex/config.toml', 'the file held only the robuust server');
        self::assertSame(0, $this->cli(['sync', '--check', '--root='.$root])['exit']);
    }

    public function testDeselectedClaudeCodeIsRetired(): void
    {
        $root = $this->project('craft5');
        $this->write($root.'/robuust-agents.json', '{"agents": ["codex", "claude_code"], "packages": []}');
        self::assertSame(0, $this->cli(['sync', '--root='.$root])['exit']);
        $this->write($root.'/.mcp.json', '{"mcpServers": {"robuust": {"command": "php", "args": ["vendor/bin/robuust-agents", "mcp"]}, "other": {"command": "other"}}}');

        $this->write($root.'/robuust-agents.json', '{"agents": ["codex"], "packages": []}');
        self::assertSame(0, $this->cli(['sync', '--root='.$root])['exit']);

        self::assertFalse(is_link($root.'/.claude/skills'), 'Claude Code no longer sees the generated skills');
        self::assertFileDoesNotExist($root.'/.claude/skills');
        self::assertSame(['other' => ['command' => 'other']], json_decode((string) file_get_contents($root.'/.mcp.json'), true)['mcpServers']);
        self::assertSame("@AGENTS.md\n", file_get_contents($root.'/CLAUDE.md'), 'the import stays');
        self::assertFileExists($root.'/.agents/skills/fe-craft/SKILL.md');
        self::assertSame(0, $this->cli(['sync', '--check', '--root='.$root])['exit']);
    }

    public function testHandWrittenSkillWithAShippedNameBlocksTheSync(): void
    {
        $root = $this->project('craft5');
        $this->write($root.'/.agents/skills/fe-craft/SKILL.md', "---\nname: fe-craft\ndescription: Hand-written.\n---\n");
        $this->write($root.'/.agents/skills/fe-craft/references/own.md', 'Hand-written reference.');
        $before = $this->files($root);

        $result = $this->cli(['sync', '--root='.$root]);

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('.agents/skills holds skills that robuust-agents did not write but would overwrite: fe-craft', $result['stderr']);
        self::assertSame($before, $this->files($root), 'nothing was written');
    }

    public function testCodexTableKeepsItsHeaderAndOtherKeys(): void
    {
        $root = $this->project('craft5');
        $this->write($root.'/robuust-agents.json', '{"agents": ["codex"], "packages": []}');
        $this->write($root.'/.codex/config.toml', "model = \"x\"\n\n[mcp_servers.\"robuust\"] # docs\ncommand = \"old\"\nargs = [\n  \"old\",\n]\nstartup_timeout_sec = 30\n\n[mcp_servers.robuust.env]\nKEY = \"value\"\n\n[mcp_servers.other]\ncommand = \"other\"\n");

        self::assertSame(0, $this->cli(['sync', '--root='.$root])['exit']);

        self::assertSame(
            "model = \"x\"\n\n[mcp_servers.\"robuust\"] # docs\ncommand = \"php\"\nargs = [\"vendor/bin/robuust-agents\", \"mcp\"]\nstartup_timeout_sec = 30\n\n[mcp_servers.robuust.env]\nKEY = \"value\"\n\n[mcp_servers.other]\ncommand = \"other\"\n",
            file_get_contents($root.'/.codex/config.toml'),
        );
        self::assertSame(0, $this->cli(['sync', '--check', '--root='.$root])['exit']);
    }

    public function testImportInsideACodeFenceIsNotAnImport(): void
    {
        $root = $this->project('craft5');
        $this->write($root.'/CLAUDE.md', "Example:\n\n```md\n@AGENTS.md\n```\n");

        self::assertSame(0, $this->cli(['sync', '--root='.$root])['exit']);

        self::assertSame("@AGENTS.md\n\nExample:\n\n```md\n@AGENTS.md\n```\n", file_get_contents($root.'/CLAUDE.md'));
    }

    public function testShippedDirectoryThatBecomesAFileIsReplaced(): void
    {
        $root = $this->project('craft5');
        $this->write($root.'/.agents/skills/guide/ref/index.md', 'old');

        $changes = new Changeset($root);
        $changes->delete('.agents/skills/guide/ref/index.md');
        $changes->put('.agents/skills/guide/ref', 'new');
        $changes->apply();

        self::assertSame('new', file_get_contents($root.'/.agents/skills/guide/ref'));
    }

    public function testGeneratedClaudeSkillsDirectoryBecomesASymlink(): void
    {
        $root = $this->project('craft5');
        $this->write($root.'/robuust-agents.json', '{"agents": ["claude_code"], "packages": []}');
        self::assertSame(0, $this->cli(['sync', '--root='.$root])['exit']);
        self::assertFalse(is_link($root.'/.claude/skills'));

        $this->write($root.'/robuust-agents.json', '{"agents": ["codex", "claude_code"], "packages": []}');
        $check = $this->cli(['sync', '--check', '--root='.$root]);
        self::assertSame(1, $check['exit']);
        self::assertStringContainsString('.claude/skills', $check['stderr']);

        self::assertSame(0, $this->cli(['sync', '--root='.$root])['exit']);
        self::assertSame('../.agents/skills', readlink($root.'/.claude/skills'));
        self::assertSame(0, $this->cli(['sync', '--check', '--root='.$root])['exit']);
    }

    public function testHandWrittenClaudeSkillBlocksTheSymlink(): void
    {
        $root = $this->project('craft5');
        $this->write($root.'/.claude/skills/my-own-skill/SKILL.md', "---\nname: my-own-skill\ndescription: Hand-written.\n---\n");
        $before = $this->files($root);

        $result = $this->cli(['sync', '--root='.$root]);

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('.claude/skills holds skills that robuust-agents did not write: my-own-skill. Move them to .agents/skills', $result['stderr']);
        self::assertSame($before, $this->files($root), 'nothing was written');
    }

    public function testUnsupportedAgentFails(): void
    {
        $root = $this->project('craft5');
        $this->write($root.'/robuust-agents.json', '{"agents": ["cursor"], "packages": []}');

        $result = $this->cli(['sync', '--root='.$root]);

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('unsupported agents: cursor', $result['stderr']);
    }
}
