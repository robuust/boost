<?php

namespace Robuust\Boost\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Robuust\Boost\Console\Application;
use Robuust\Boost\Stack;
use Robuust\Boost\Sync\Filesystem;
use Robuust\Boost\Sync\ProcessRunner;

abstract class TestCase extends BaseTestCase
{
    /** @var list<string> */
    private array $temporary = [];

    protected function tearDown(): void
    {
        foreach ($this->temporary as $path) {
            Filesystem::remove($path);
        }

        Stack::useRoot(null);
        putenv('ROBUUST_DOCS_BASE_URL');

        parent::tearDown();
    }

    public static function packageRoot(): string
    {
        return \dirname(__DIR__);
    }

    public static function fixture(string $name): string
    {
        return __DIR__.'/fixtures/'.$name;
    }

    /**
     * Copy a fixture project into a temporary directory.
     */
    protected function project(string $fixture): string
    {
        $root = sys_get_temp_dir().'/robuust-boost-'.bin2hex(random_bytes(6));
        $this->copy(self::fixture($fixture), $root);
        $this->temporary[] = $root;

        return (string) realpath($root);
    }

    /**
     * A snapshot of every file below a directory.
     *
     * @return array<string, string>
     */
    protected function files(string $root): array
    {
        $files = [];
        foreach (Filesystem::files($root) as $file) {
            $files[$file] = (string) file_get_contents($root.'/'.$file);
        }

        return $files;
    }

    protected function write(string $path, string $content): void
    {
        Filesystem::ensureDirectory(\dirname($path));
        file_put_contents($path, $content);
    }

    /**
     * Run the CLI and capture its output.
     *
     * @param list<string> $arguments
     *
     * @return array{exit: int, stdout: string, stderr: string}
     */
    protected function cli(array $arguments, ?ProcessRunner $runner = null): array
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);

        $exit = (new Application(self::packageRoot(), $runner ?? new ProcessRunner(), $stdout, $stderr))->run($arguments);

        rewind($stdout);
        rewind($stderr);

        return [
            'exit' => $exit,
            'stdout' => (string) stream_get_contents($stdout),
            'stderr' => (string) stream_get_contents($stderr),
        ];
    }

    private function copy(string $source, string $target): void
    {
        Filesystem::ensureDirectory($target);

        foreach (Filesystem::files($source) as $file) {
            $this->write($target.'/'.$file, (string) file_get_contents($source.'/'.$file));
            if (is_executable($source.'/'.$file)) {
                chmod($target.'/'.$file, 0o755);
            }
        }
    }
}
