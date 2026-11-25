<?php

namespace Robuust\Boost\Console;

use Robuust\Boost\Docs\CraftDocs;
use Robuust\Boost\Docs\DocsSearch;
use Robuust\Boost\Mcp\McpServer;
use Robuust\Boost\Rules\LegacyRules;
use Robuust\Boost\Rules\RulesIndex;
use Robuust\Boost\Stack;
use Robuust\Boost\Sync\BoostSync;
use Robuust\Boost\Sync\Changeset;
use Robuust\Boost\Sync\Craft5Config;
use Robuust\Boost\Sync\Craft5Sync;
use Robuust\Boost\Sync\Guard;
use Robuust\Boost\Sync\ProcessRunner;

/**
 * `vendor/bin/robuust-agents`: sync, mcp and index-rules.
 */
final class Application
{
    private const USAGE = <<<'TXT'
        Usage: robuust-agents <command> [options]

        Commands:
          sync [--check]   Generate the project's agent files. On Laravel and Craft 6 this
                           runs `php artisan boost:update`; on Craft 5 it writes them itself.
                           --check writes nothing and exits 1 when the output would change.
          mcp              Run the docs search MCP server over stdio (Craft 5).
          index-rules      Regenerate .ai/rules/index.md.

        Options:
          --root=<path>    Project root (default: the current directory).
          -h, --help       Show this help.

        TXT;

    /** @var resource */
    private $stdout;

    /** @var resource */
    private $stderr;

    /**
     * @param resource|null $stdout
     * @param resource|null $stderr
     */
    public function __construct(
        private readonly string $packageRoot,
        private readonly ProcessRunner $runner = new ProcessRunner(),
        $stdout = null,
        $stderr = null,
    ) {
        $this->stdout = $stdout ?? STDOUT;
        $this->stderr = $stderr ?? STDERR;
    }

    /**
     * @param list<string> $argv arguments without the script name
     *
     * @return int the exit code
     */
    public function run(array $argv): int
    {
        $command = null;
        $check = false;
        $root = (string) getcwd();

        foreach ($argv as $argument) {
            if ('--check' === $argument) {
                $check = true;
            } elseif (str_starts_with($argument, '--root=')) {
                $root = substr($argument, \strlen('--root='));
            } elseif ('-h' === $argument || '--help' === $argument) {
                $command = 'help';
            } elseif (null === $command && !str_starts_with($argument, '-')) {
                $command = $argument;
            } else {
                return $this->fail("Unknown argument: {$argument}\n\n".self::USAGE);
            }
        }

        if (!is_dir($root)) {
            return $this->fail("Project root does not exist: {$root}");
        }

        try {
            return match ($command) {
                'sync' => $this->sync(Stack::fromPath((string) realpath($root)), $check),
                'mcp' => $this->mcp(Stack::fromPath((string) realpath($root))),
                'index-rules' => $this->indexRules((string) realpath($root)),
                null, 'help' => $this->write(self::USAGE),
                default => $this->fail("Unknown command: {$command}\n\n".self::USAGE),
            };
        } catch (\RuntimeException $exception) {
            return $this->fail($exception->getMessage());
        }
    }

    private function sync(Stack $stack, bool $check): int
    {
        return match ($stack->kind()) {
            Stack::KIND_BOOST => $this->syncBoost($stack, $check),
            Stack::KIND_CRAFT5 => $this->syncCraft5($stack, $check),
            default => $this->fail('Unsupported project. Sync supports Laravel and Craft 6 projects with laravel/boost (and an `artisan` file), and Craft 5 projects.'),
        };
    }

    private function syncBoost(Stack $stack, bool $check): int
    {
        $missing = Guard::missing($stack, BoostSync::packages($stack), BoostSync::CONFIG);
        if ([] !== $missing) {
            return $this->fail(implode("\n", $missing)."\nNothing was written.");
        }

        $sync = new BoostSync($this->runner);

        if (!$check) {
            return $sync->update($stack);
        }

        $result = $sync->check($stack);
        if (0 !== $result['exit']) {
            return $this->fail('php artisan boost:update failed; the generated files were restored.');
        }

        return $this->report($result['changes']);
    }

    private function syncCraft5(Stack $stack, bool $check): int
    {
        $config = Craft5Config::load($stack->root());

        $missing = Guard::missing($stack, $config->packages, Craft5Config::FILE);
        if ([] !== $missing) {
            return $this->fail(implode("\n", $missing)."\nNothing was written.");
        }

        $changes = (new Craft5Sync($this->packageRoot))->plan($stack, $config);

        return $check ? $this->report($changes->changes()) : $this->apply($changes);
    }

    private function apply(Changeset $changes): int
    {
        $changed = $changes->changes();
        $changes->apply();

        return $this->write([] === $changed
            ? 'Agent files are up to date.'
            : "Updated:\n  ".implode("\n  ", $changed));
    }

    /**
     * @param list<string> $changes
     */
    private function report(array $changes): int
    {
        if ([] === $changes) {
            return $this->write('Agent files are up to date.');
        }

        return $this->fail("Generated agent files are out of date:\n  ".implode("\n  ", $changes)
            ."\nRun `vendor/bin/robuust-agents sync` and commit the result.");
    }

    private function mcp(Stack $stack): int
    {
        $configured = Craft5Config::load($stack->root())->docsBaseUrl;
        $docs = new DocsSearch($stack, new CraftDocs(CraftDocs::baseUrl($configured)));

        (new McpServer($docs))->run();

        return 0;
    }

    private function indexRules(string $root): int
    {
        $legacy = LegacyRules::migration($root);

        foreach (RulesIndex::unindexed($root, $legacy) as $file) {
            fwrite($this->stderr, "Skipped {$file}: no valid `paths` frontmatter.\n");
        }

        $changes = new Changeset($root);
        foreach ($legacy as $path => $content) {
            $changes->put($path, $content);
        }
        $changes->put(RulesIndex::INDEX, RulesIndex::render($root, $legacy));
        $changes->apply();

        return $this->write(([] === $legacy ? '' : 'Migrated legacy rules: '.implode(', ', array_keys($legacy)).".\n")
            .'Regenerated '.RulesIndex::INDEX.'.');
    }

    private function write(string $message): int
    {
        fwrite($this->stdout, rtrim($message)."\n");

        return 0;
    }

    private function fail(string $message): int
    {
        fwrite($this->stderr, rtrim($message)."\n");

        return 1;
    }
}
