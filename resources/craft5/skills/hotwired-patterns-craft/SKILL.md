---
name: hotwired-patterns-craft
description: Use when choosing or implementing Turbo Frames, Turbo Streams, and Stimulus contracts in Craft/Twig projects.
---

# Hotwired for Craft/Twig

Before acting on a path, open `.ai/rules/index.md`, read every rule file whose globs match the paths in scope, and apply those rules within their scope, subject to explicit user instructions. For repository-wide work, apply every `**` rule. Reuse rules already read for unchanged paths; check new paths as scope grows.

- Choose the smallest contract that owns the update: full navigation, a Turbo Frame, a Turbo Stream, or client-only Stimulus.
- Use Frames for scoped navigations or replacements; use Streams when one server response updates multiple targets.
- Use Stimulus for browser-only behavior or interactions that cannot be expressed through a server-rendered Turbo response.
- Keep frame and stream target IDs deterministic and keep server responses aligned with their requesting DOM contract.
- Keep persistent state changes in Craft/Yii services or controllers, not in client-side coordination code.

Use [`be-craft`](../be-craft/SKILL.md) for server endpoints and [`fe-craft`](../fe-craft/SKILL.md) for Twig composition. Pair with [`gtm-patterns-craft`](../gtm-patterns-craft/SKILL.md) when navigation affects tracking, or [`seo-patterns-craft`](../seo-patterns-craft/SKILL.md) when it affects URLs or metadata.
