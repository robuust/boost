---
name: tailwind-patterns-twig
description: Use for Tailwind and CSS architecture in Twig-based projects.
---

# Tailwind/CSS for Twig

Before acting on a path, open `.ai/rules/index.md`, read every rule file whose globs match the paths in scope, and apply those rules within their scope, subject to explicit user instructions. For repository-wide work, apply every `**` rule. Reuse rules already read for unchanged paths; check new paths as scope grows.

For a concrete Tailwind v4 syntax or recommendation question, call `search-tailwind-docs` on the `robuust` MCP server with a targeted query. Use its commit-pinned official documentation excerpts as framework evidence; do not turn documentation defaults into project conventions.

Do not fetch Craft documentation for CSS- or Tailwind-only work. Call `search-craft-docs` as well only when the task raises a separate Craft CMS, Yii, or Craft/Twig API question; call neither documentation tool when repository evidence is sufficient.

- Follow the existing Tailwind version, asset entry point, token layer, and extraction boundary.
- Keep one-off presentation local to its Twig template; extract repeated semantic patterns only where the project’s CSS architecture supports it.
- Prefer existing design tokens and canonical Tailwind utilities before introducing arbitrary values or new CSS APIs.
- Preserve responsive, hover, focus, disabled, and reduced-motion behavior where applicable.
- Do not turn a focused visual change into a CSS architecture rewrite.

Use [`fe-craft`](../fe-craft/SKILL.md) when template composition or accessibility needs work. Use [`infer-craft-conventions`](../infer-craft-conventions/SKILL.md) for requested convention discovery or recording.
