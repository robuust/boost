---
name: fe-craft
description: Use for Craft 5 Twig templates, including their element queries, component contracts, asset integration, translations, and accessibility.
---

# Craft/Twig frontend

Before acting on a path, open `.ai/rules/index.md`, read every rule file whose globs match the paths in scope, and apply those rules within their scope, subject to explicit user instructions. For repository-wide work, apply every `**` rule. Reuse rules already read for unchanged paths; check new paths as scope grows.

- Match the project’s template composition and asset pipeline instead of imposing a new structure.
- Keep component inputs and rendered states explicit; required CMS content is a contract, not fallback-copy territory.
- Use translation APIs consistently with existing template and module code.
- Preserve semantic HTML, keyboard access, focus states, and accessible names.
- Use Turbo and Stimulus only according to the project’s established endpoint and DOM contracts.
- For an element query in a template, eager-load every relation the template and its partials render. Check how each partial reads a relation (`[0]`, `|first`, `.one()`, a loop) and add it to `.with()`, or call `.eagerly()` where the partial queries it, following the project’s existing convention.
- Set `orderBy` explicitly for a list ordered by date: entries in a structure section come back in structure order by default.
- For a concrete Craft CMS, Yii, or Craft/Twig API question, call `search-craft-docs` on the `robuust` MCP server with a targeted query.
- Use `search-tailwind-docs` only for a separate Tailwind CSS v4 syntax or recommendation question. Call both only when the task genuinely spans both surfaces, and call neither when repository evidence is sufficient.

Use related skills only for the affected concern: [`be-craft`](../be-craft/SKILL.md) for server behavior, [`hotwired-patterns-craft`](../hotwired-patterns-craft/SKILL.md) for Turbo/Stimulus contracts, [`tailwind-patterns-twig`](../tailwind-patterns-twig/SKILL.md) for styling, [`seo-patterns-craft`](../seo-patterns-craft/SKILL.md) for metadata/indexing, and [`gtm-patterns-craft`](../gtm-patterns-craft/SKILL.md) for analytics. Use [`infer-craft-conventions`](../infer-craft-conventions/SKILL.md) for requested convention discovery or recording.
