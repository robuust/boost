---
name: vue-vitest-coverage
description: Write or fix Vue component specs and coverage when the user explicitly requests Vitest or frontend unit-test work.
compatibility: Designed for Open AI Codex (or similar products)
metadata:
  author: rutgerbakker
  version: "1.0"
---

# Vue Vitest Coverage

Use this skill when the user explicitly wants Vue component specs or Vitest coverage.

Keep specs focused on the requested component behavior and coverage goal. Inspect existing feature-test responsibilities before duplicating backend/domain scenarios.

Apply the project's test authorization rule from `AGENTS.md` before test work; project coverage requirements take precedence over the focused coverage defaults below.

## Trigger Conditions

Activate this skill when one or more of these are present:

- The user asks for Vitest, spec tests, unit tests, or component tests.
- The user asks to get frontend coverage green.
- The user invokes this skill together with `design-slice-inertia`.
- The user asks to fix failing Vue specs or coverage.

Apply only to test work explicitly requested for the active objective. That authorization persists across follow-up corrections unless the user withdraws it or replaces the objective. Discovering a coverage failure during production work alone is not permission to run or change tests.

## Objective

- Get the target Vue component to the requested coverage target, usually 100% statements or the repository threshold.
- Cover every named local method, meaningful callback, and computed/data transformation owned by the target component through observable behavior.
- Test only a few important scenarios through rendered behavior.
- Keep assertions intentionally sparse: enough to prove the component works and exercise code, not enough to inventory every rendered field.
- Avoid duplicating Laravel/Inertia feature-test responsibilities.

## Test Depth

Prefer these scenario types:

- The component renders.
- One key item, section, row, or child component is present.
- One meaningful prop value or piece of local data is rendered.
- A key conditional state appears or disappears.
- A user interaction calls the component-owned method/function or emits the expected event.
- A formatter/helper/computed owned by the component is exercised through visible output.

Avoid these by default:

- Asserting every visible field in the fixture.
- Asserting every item in an action menu, tab list, table row, card, or mock-data array.
- Deep `toEqual` checks for full props/config objects when one representative value or call count proves the behavior.
- Exhaustive permutations of backend data.
- Re-testing authorization, routing, validation, resources, or database-backed formatting already covered by Laravel/Inertia feature tests.
- Brittle assertions on exact whitespace, punctuation-only formatting, generated class order, or implementation details that are invisible to users.
- Snapshot tests unless the repository already relies on them for the same purpose.

## Workflow

1. Inspect the target component and existing sibling specs.
2. Identify local executable statements: template conditionals, methods, helpers, computed values, event handlers, watchers, and emits.
3. Pick the smallest set of scenarios that proves the requested behavior, exercises those statements, and meets the applicable target.
4. Mount with repository-standard helpers, plugins, stubs, and Inertia mocks.
5. Prefer asserting rendered output and emitted behavior over direct access to internals.
6. Run the most targeted Vitest command with coverage enabled for the affected spec.
7. Address failures or meaningful coverage gaps, then rerun affected specs. Stop after the requested scope passes unless new evidence warrants a broader check.
8. Before finishing, remove duplicate or inventory-style assertions that are not needed for coverage or core behavior.

## Coverage Guardrails

- Aim for the requested target on the target file; do not chase broad project coverage in this skill.
- It is acceptable to leave branch and line coverage below 100% when reaching them would require artificial or low-value tests, unless the user explicitly asks for those metrics too.
- Do not leave named component-owned methods or meaningful callbacks unexercised; cover them through observable behavior.
- Do not chase low-value V8 function coverage caused by generated render functions, inline template handlers, or compiler output when statements/lines are already green.
- If a statement is only coverable through unrealistic setup, state that tradeoff before adding artificial coverage.
- Do not add production-only code, exported internals, or test-only hooks just to make coverage easier.
- Do not add dependencies without explicit approval.
- Do not add assertions only because a value appears in the fixture. Rendering the component already covers many template interpolation statements.
- For arrays such as menu items, tabs, or cards, prefer a count plus one representative assertion. Only assert every item when the user's requested behavior depends on the exact full list.

## Spec Style

- Follow existing spec naming and folder conventions.
- Keep fixtures small and local to the spec.
- Use descriptive scenario names in English.
- Prefer one or two representative data objects over large factories.
- Stub expensive child components only when they distract from the target component.
- Mock route helpers, Wayfinder functions, or Inertia globals only at the narrow boundary needed by the component.
- Keep assertions durable: text that matters, role/label when available, emitted events, and visible state.
- Keep each scenario compact. If a test starts reading like a checklist of all props, reduce it to the one or two assertions that prove the important behavior.

## Pairing With Design Slices

When paired with `design-slice-inertia`:

- Keep the slice implementation focused first.
- Add specs only for the component/page requested by the user. Use [`design-slice-inertia`](../design-slice-inertia/SKILL.md) for the UI implementation contract.
- Do not use specs as a reason to broaden UI behavior beyond the agreed slice scope.
- Prefer a smoke-level spec plus the smallest interaction/data assertions needed for green coverage.

Use the `vue-components-mcp` skill (shipped with `@robuust-digital/vue-components`) for unresolved shared-component APIs in stubs, and [`laravel-seeding-patterns`](../laravel-seeding-patterns/SKILL.md) only when explicitly requested backend test fixtures need relational seed data. When the user defers checks, follow the `robuust:deferred-validation-development` workflow skill when it is installed.

## Output Requirements

When changing tests, report:

- target component/spec file
- the important scenarios covered
- final coverage result for statements
- targeted command that was run

Keep the explanation concise.

## Task

$ARGUMENTS
