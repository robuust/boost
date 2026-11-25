---
name: gtm-patterns-craft
description: Implement, review, or debug GTM/GA tracking in Craft/Twig, including Turbo navigation pageviews.
compatibility: Designed for Open AI Codex (or similar products)
metadata:
  author: rutgerbakker
  version: "1.0"
---

# GTM Patterns for Craft CMS

Maintain the project's tracking contract for initial loads and navigation. For review or diagnosis, report evidence and recommendations; change tracking only when implementation is requested.

## Measurement contract

- Keep one tracking orchestration strategy per project. Prefer GTM for a new integration; preserve an existing GA4-only integration unless a migration is requested.
- With GTM, keep bootstrap in `<head>` and the `noscript` iframe in `<body>`.
- For Turbo navigation, use `turbo:load` with the shared virtual pageview contract: `event: 'pageView'` and `virtualUrl: <resolved-url>`.
- Load tracking scripts only in production; keep tracking disabled in non-production environments.
- Keep production guards, consent-dependent ordering, and initialization ownership consistent; avoid duplicate bootstrap or pageview events.

## Apply to the requested area

1. Inspect the actual base template and tracking entrypoint. Establish the current integration, production condition, consent owner, and whether Turbo/morph refresh is used from project evidence.
2. For Turbo pageview changes, read [turbo-gtm-hotwired.md](references/turbo-gtm-hotwired.md) and use only the relevant GTM or GA4 example.
3. For implementation, change only the tracking behavior needed by the request.
4. Inspect the relevant initial-load and navigation behavior. Use the affected checks from [implementation-checklist.md](references/implementation-checklist.md); a small template change does not require a full analytics audit. Report any behavior that could not be inspected.

## Related skills

- Use [`fe-craft`](../fe-craft/SKILL.md) for Twig composition and applicable local rules.
- Use [`hotwired-patterns-craft`](../hotwired-patterns-craft/SKILL.md) when the task changes Turbo/Stimulus interaction architecture.
- Use [`seo-patterns-craft`](../seo-patterns-craft/SKILL.md) for requested canonical, indexing, or redirect work; analytics work alone does not require an SEO audit.
