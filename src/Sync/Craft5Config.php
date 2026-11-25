<?php

namespace Robuust\Boost\Sync;

use Robuust\Boost\Support\Json;

/**
 * The committed `robuust-agents.json` of a Craft 5 project.
 */
final class Craft5Config
{
    public const FILE = 'robuust-agents.json';

    public const AGENTS = ['codex', 'claude_code'];

    /**
     * @param list<string> $agents
     * @param list<string> $packages
     */
    public function __construct(
        public readonly array $agents,
        public readonly array $packages,
        public readonly ?string $docsBaseUrl = null,
        public readonly bool $exists = true,
    ) {
    }

    /**
     * Read the config, or the defaults when the file does not exist yet.
     */
    public static function load(string $root): self
    {
        $data = Json::readFile($root.'/'.self::FILE);

        if (null === $data) {
            return new self(self::AGENTS, [], null, false);
        }

        $agents = self::stringList($data, 'agents');
        $unknown = array_diff($agents, self::AGENTS);
        if ([] !== $unknown) {
            throw new \RuntimeException(\sprintf('%s lists unsupported agents: %s. Supported: %s.', self::FILE, implode(', ', $unknown), implode(', ', self::AGENTS)));
        }

        $docsBaseUrl = $data['docs_base_url'] ?? null;

        return new self(
            $agents,
            self::stringList($data, 'packages'),
            \is_string($docsBaseUrl) && '' !== $docsBaseUrl ? $docsBaseUrl : null,
        );
    }

    /**
     * The default file content, as `vendor/bin/robuust-agents sync` creates it.
     */
    public function toJson(): string
    {
        return Json::encode(['agents' => $this->agents, 'packages' => $this->packages]);
    }

    public function uses(string $agent): bool
    {
        return \in_array($agent, $this->agents, true);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    private static function stringList(array $data, string $key): array
    {
        $value = $data[$key] ?? [];

        if (!\is_array($value) || array_filter($value, static fn (mixed $item): bool => !\is_string($item)) !== []) {
            throw new \RuntimeException(\sprintf('%s: "%s" must be a list of strings.', self::FILE, $key));
        }

        return array_values($value);
    }
}
