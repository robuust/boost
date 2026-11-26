# Robuust Craft 5

- This is a Craft CMS 5 project (Twig and Yii). Run console commands with `php craft`.
- Load the matching skill before you work in an area: `fe-craft` for Twig templates, including the element queries they run, `be-craft` for modules, controllers, services, queue jobs and project config, `hotwired-patterns-craft` for Turbo and Stimulus, `tailwind-patterns-twig` for CSS, `seo-patterns-craft` for metadata and redirects, `gtm-patterns-craft` for analytics, and `infer-craft-conventions` when asked to discover or record conventions.
- Docs search runs on the `robuust` MCP server: `search-craft-docs` for a concrete Craft CMS, Yii or Craft/Twig API question and `search-tailwind-docs` for a concrete Tailwind CSS v4 question. Use neither when repository evidence is enough.
- Record a project rule only when the user asks: add it to `.ai/rules` in Boost's format, as `infer-craft-conventions` describes, then run `vendor/bin/robuust-agents index-rules`.
- `vendor/bin/robuust-agents sync` generates this block from `robuust/boost`. Do not edit it; put project-specific instructions outside the block or in `.ai/rules`.
