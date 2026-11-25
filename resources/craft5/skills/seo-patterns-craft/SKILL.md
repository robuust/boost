---
name: seo-patterns-craft
description: Implement or audit Craft SEO metadata, canonical URLs, hreflang, robots rules, and Craft/nginx redirects.
---

# SEO Patterns for Craft CMS

Keep URL ownership and metadata coherent for the requested page states. Review and audit requests produce findings; implement changes only when requested.

## Scope and references

- For metadata, canonical, hreflang, or robots work, read [canonical-and-meta.md](references/canonical-and-meta.md).
- For redirect ownership or URL migrations, read [redirects-craft-vs-nginx.md](references/redirects-craft-vs-nginx.md).
- For verification, use only relevant sections of [seo-validation-checklist.md](references/seo-validation-checklist.md). Run a full audit only when requested.

## Core rules

- Align canonical URLs with the canonical route, and keep title, description, Open Graph, and canonical values coherent for each page state.
- Render canonical only when the request path matches the entry's canonical path; normalize the home path comparison.
- Include hreflang only for enabled, valid site variants, with one intentional x-default when multilingual routing requires it.
- Keep non-production environments out of indexation by default.
- Give each redirect concern one owner. Use Craft for content/session-aware behavior and nginx for deterministic edge rewrites; preserve single-hop destinations.

## Workflow and completion

1. Inspect the affected templates, controllers, or redirect configuration and relevant local rules. Expand discovery only when URL ownership or dependencies require it.
2. For implementation, apply the smallest change at the owning layer.
3. Inspect representative affected URLs using direct HTTP requests and page source. Check destinations, chains, loops, and locale relationships relevant to the change.
4. Report findings or changes, observed HTTP behavior, and remaining uncertainty. Successful local checks do not establish deployed behavior; perform post-deployment checks only when the task includes that stage.

## Related skills

Use [`fe-craft`](../fe-craft/SKILL.md) for template composition, [`be-craft`](../be-craft/SKILL.md) for server behavior, and [`hotwired-patterns-craft`](../hotwired-patterns-craft/SKILL.md) when Turbo affects URL state or metadata. Use [`gtm-patterns-craft`](../gtm-patterns-craft/SKILL.md) only when the request includes analytics tracking.
