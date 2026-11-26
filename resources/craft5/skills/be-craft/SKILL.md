---
name: be-craft
description: "Use for Craft 5 and Yii back-end work: modules, controllers, services, events, queue jobs, Element Queries in PHP, and project config."
---

# Craft/Yii backend

Before acting on a path, open `.ai/rules/index.md`, read every rule file whose globs match the paths in scope, and apply those rules within their scope, subject to explicit user instructions. For repository-wide work, apply every `**` rule. Reuse rules already read for unchanged paths; check new paths as scope grows.

- Keep application-owned changes at the project’s existing architectural altitude; do not introduce repositories, DTOs, or abstractions without evidence that the project uses them. For defects owned by Craft, Yii, or a plugin, follow the `robuust:upstream-dependency-fixes` workflow skill, when installed, before adding project-level fixes.
- Make controller, service, event, queue, and template response contracts explicit and narrow.
- Query Craft elements deliberately and eager-load relations used by a rendering path.
- Keep persistent state and authorization on the server.
- Treat project config as a Craft contract. When changing it, update its `dateModified` timestamp in the same change set.
- For a concrete Craft CMS or Yii API question, call `search-craft-docs` on the `robuust` MCP server with a targeted query. Do not fetch documentation when repository evidence is sufficient.
- Do not fetch Tailwind documentation for backend-only work.

For template changes, including the element queries a template runs, use [`fe-craft`](../fe-craft/SKILL.md); for Turbo response contracts, use [`hotwired-patterns-craft`](../hotwired-patterns-craft/SKILL.md). Use [`infer-craft-conventions`](../infer-craft-conventions/SKILL.md) when convention discovery or recording is requested, not as a prerequisite for routine backend edits.
