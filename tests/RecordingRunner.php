<?php

namespace Robuust\Boost\Tests;

use Robuust\Boost\Sync\ProcessRunner;

final class RecordingRunner extends ProcessRunner
{
    /** @var list<array{command: list<string>, cwd: string, env: array<string, string>}> */
    public array $commands = [];

    /**
     * @param (\Closure(string): void)|null $effect
     * @param int                           $exit   the exit code `boost:update` reports
     */
    public function __construct(
        private readonly ?\Closure $effect = null,
        private readonly int $exit = 0,
    ) {
    }

    public function run(array $command, string $cwd, array $env = []): int
    {
        $this->commands[] = ['command' => $command, 'cwd' => $cwd, 'env' => $env];

        if (null !== $this->effect) {
            ($this->effect)($cwd);
        }

        return $this->exit;
    }
}
