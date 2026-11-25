---
name: laravel-seeding-patterns
description: Create or update Laravel seeders and relational factory graphs, including seeded fixtures when test work is explicitly requested.
compatibility: Designed for Open AI Codex (or similar products)
metadata:
  author: joostwaaijer
  version: "1.0"
---

# Laravel Seeding Patterns

Use this skill when working on Laravel seeders, seeded fixtures, or factories that are used to build relational app-state data.

## Goal

Keep seed data concise, additive, and structurally aligned with the project's existing `DatabaseSeeder` style.

## Scope

- In scope: `database/seeders`, factories used for seed graphs, relationship seeding structure, seeded reference data.
- In scope: test seed data and fixtures that mirror seeded app-state flows, only when the user has explicitly requested that test work for the active objective, including in an earlier request.
- Out of scope: isolated test factories that intentionally flatten or shortcut relationships for one-off assertions, broad domain modeling changes unrelated to seed flow.

## Examples

- For a seeding shape not already clear from the target project, read the matching example in [references/workspace-examples.md](references/workspace-examples.md). These illustrate patterns, not a requirement to inspect other repositories.
- Prefer copying the closest existing project pattern before inventing a new seeding style.

## Core Pattern

- Prefer a single factory graph over post-create mutation.
- Start from the parent model factory and attach related records with chained `->has(..., 'relationName')` calls.
- Use the actual Eloquent relationship method name string, for example `'calculations'` or `'workOrders'`.
- For existing belongs-to relations, use factory relationship APIs like `->for($model, 'relationName')`.
- Avoid post-create loops when the same result can be expressed in the factory graph.
- Avoid manual foreign-key assignment in seeding when a relationship method or factory relationship API can express the same intent.

## Seeder Style

- Match the local seeder's structure before introducing helpers or abstractions.
- Keep seeded examples explicit and readable. Prefer a few clearly named seeded records over generic loops when the records represent meaningful demo/reference data.
- If a project already seeds a known shared record such as an admin user, fetch it once and reuse it through `->for(...)`.
- Keep seeders additive unless the repository explicitly uses a reset/rebuild pattern.
- Do not delete existing records as part of normal seed data setup unless the user explicitly asks for destructive behavior.
- Editing a seeder does not authorize running it against a database. Establish the target environment and permission before executing a seed command.

## Preferred Shapes

Parent with related data:

```php
Project::factory([
    'name' => 'Example project',
])->has(
    Calculation::factory()->count(3),
    'calculations'
)->has(
    WorkOrder::factory()
        ->count(1)
        ->state([
            'name' => 'Site preparation',
        ])
        ->for($adminUser, 'creator'),
    'workOrders'
)->create();
```

Existing related model reused across seeded graph:

```php
$adminUser = User::first();

WorkOrder::factory()
    ->for($adminUser, 'creator')
    ->create();
```

## Decision Rules

- If the relationship is owned by the parent being seeded, prefer `->has(...)`.
- If the relationship is an inverse belongs-to to an already existing model, prefer `->for(...)`.
- If the relation is many-to-many with pivot data, prefer `->hasAttached(...)`.
- If you need custom seeded values, prefer `->state([...])` inside the graph rather than creating first and patching after.
- If a project already splits seed flow across dedicated seeders, keep that orchestration in `DatabaseSeeder` and pass seeded dependencies through `callWith(...)` or equivalent explicit parameters.
- Only introduce a private seeder helper when it clearly reduces duplication without hiding the seeded shape.

## Anti-patterns

- Creating parent records first and then iterating over them just to attach predictable related seed data.
- Setting raw `*_id` values in arrays when `->for(...)` or `->has(...)` would express the relation directly.
- Mixing multiple different seeding styles in the same `DatabaseSeeder` without a clear reason.
- Hiding important seeded records behind overly abstract helper layers.

## Coordination

- Explicit user instructions and applicable repository `AGENTS.md` rules take precedence over this skill.
- If the repository already has explicit seeding rules, follow those over this skill.
- If the existing seeder style conflicts with this skill, prefer the local seeder style and update only what is needed for consistency.
- For Vue component fixtures, use [`vue-vitest-coverage`](../vue-vitest-coverage/SKILL.md) when Vue test work is requested; do not build a database seed graph for an isolated component spec.

## Task

$ARGUMENTS
