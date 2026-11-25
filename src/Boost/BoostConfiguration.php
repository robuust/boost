<?php

namespace Robuust\Boost\Boost;

use Robuust\Boost\Boost\Tools\SearchCraftDocs;
use Robuust\Boost\Stack;

/**
 * The Laravel Boost config this package sets for a project (D12).
 *
 * - Claude Code writes its guidelines to `AGENTS.md`, which `CLAUDE.md` imports.
 * - Inertia skills only reach projects that require Inertia themselves.
 * - The Craft docs tool only reaches Craft projects.
 *
 * Guidelines select themselves through Blade conditions on {@see Stack::current()};
 * Boost drops a guideline that renders empty.
 */
final class BoostConfiguration
{
    /** Skills that only apply to Laravel + Inertia + Vue projects. */
    public const INERTIA_SKILLS = [
        'design-slice-inertia',
        'inertia-best-practices',
        'inertia-patterns',
        'vue-vitest-coverage',
    ];

    /**
     * Config values to set, keyed by dot notation.
     *
     * @param \Closure(string): mixed $current reads the current value of a config key
     *
     * @return array<string, mixed>
     */
    public static function for(Stack $stack, \Closure $current): array
    {
        $values = [];

        if (null === $current('boost.agents.claude_code.guidelines_path')) {
            $values['boost.agents.claude_code.guidelines_path'] = 'AGENTS.md';
        }

        $skillExcludes = self::stringList($current('boost.skills.exclude'));
        if (!$stack->usesInertia()) {
            $values['boost.skills.exclude'] = array_values(array_unique(array_merge($skillExcludes, self::INERTIA_SKILLS)));
        }

        if (null !== $stack->craftMajor()) {
            $tools = self::stringList($current('boost.mcp.tools.include'));
            if (!\in_array(SearchCraftDocs::class, $tools, true)) {
                $values['boost.mcp.tools.include'] = array_merge($tools, [SearchCraftDocs::class]);
            }
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        return \is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }
}
