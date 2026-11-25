<?php

namespace Robuust\Boost\Docs;

use Robuust\Boost\Stack;

/**
 * The docs search tools for one project, shared by the stdio MCP server and the Boost tool.
 */
final class DocsSearch
{
    public const CRAFT_DESCRIPTION = 'Search the Craft CMS documentation for the project\'s Craft major (or the given major) and return targeted excerpts. Every result names the requested and served major, page URL, source path and docs revision. It never falls back to another major. Use it for concrete Craft CMS, Yii or Craft/Twig API questions only.';

    public const TAILWIND_DESCRIPTION = 'Search the current official Tailwind CSS v4 documentation and return commit-pinned excerpts. Use it for concrete Tailwind v4 syntax or recommendation questions only.';

    public function __construct(
        private readonly Stack $stack,
        private readonly CraftDocs $craft,
        private readonly TailwindDocs $tailwind = new TailwindDocs(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function craft(string $query, int|string|null $major = null, int $maxResults = 5): array
    {
        if (null === $major || '' === $major) {
            $major = $this->stack->craftMajor();
            if (null === $major) {
                throw new \RuntimeException('Cannot tell the project\'s Craft major: craftcms/cms is not in composer.json or composer.lock. Pass `major` explicitly.');
            }
        }

        if (!is_numeric($major)) {
            throw new \InvalidArgumentException('major must be a Craft major version such as 5 or 6.');
        }

        return $this->craft->search($query, (int) $major, $maxResults);
    }

    /**
     * @return array<string, mixed>
     */
    public function tailwind(string $query, int $maxResults = 5): array
    {
        return $this->tailwind->search($this->stack, $query, $maxResults);
    }

    /**
     * JSON Schema for the Craft docs tool input.
     *
     * @return array<string, mixed>
     */
    public static function craftSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'A concrete query, such as a tag, method or setting name.'],
                'major' => ['type' => 'integer', 'description' => 'Craft major to search (5 or 6). Defaults to the project\'s installed major.'],
                'max_results' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10, 'default' => 5],
            ],
            'required' => ['query'],
        ];
    }

    /**
     * JSON Schema for the Tailwind docs tool input.
     *
     * @return array<string, mixed>
     */
    public static function tailwindSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'A concrete Tailwind CSS v4 question or utility name.'],
                'max_results' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10, 'default' => 5],
            ],
            'required' => ['query'],
        ];
    }
}
