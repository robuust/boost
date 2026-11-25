<?php

namespace Robuust\Boost\Sync;

/**
 * Runs a child process with its output passed through.
 */
class ProcessRunner
{
    /**
     * @param list<string>          $command
     * @param array<string, string> $env     variables added to the current environment
     *
     * @return int the exit code
     */
    public function run(array $command, string $cwd, array $env = []): int
    {
        $process = proc_open(
            $command,
            [0 => STDIN, 1 => STDOUT, 2 => STDERR],
            $pipes,
            $cwd,
            array_merge(getenv(), $env),
        );

        if (!\is_resource($process)) {
            throw new \RuntimeException('Failed to start '.implode(' ', $command).'.');
        }

        return proc_close($process);
    }
}
