---
name: inertia-best-practices
description: Apply Robuust's conventions to Laravel + Vue Inertia implementations and reviews. Ask the developer about uncovered implementation choices and record the decision in this skill.
---

# Inertia best practices

Trace the interaction from the rendered control through its route and controller back to the component that consumes the response. Read the applicable project rules (often in `.ai/rules/`) and check the installed Laravel, Vue and Robuust component adapters when their behavior affects the choice. Use the project's version-matched Inertia guidance for API syntax.

## When the skill does not cover an implementation

Before implementing an Inertia choice, look for a rule here or in the guidance this skill links for that interaction. If a rule covers the case and its conditions hold, apply it. A missing API signature can be looked up. A missing choice about how the team wants to use Inertia requires a developer decision, even when the framework has a default or similar code exists elsewhere.

Ask the developer in the current chat using the available question tool (in Codex, `request_user_input_async` in Default mode or `request_user_input` in Plan mode). When no question tool is available, ask in chat. Describe the uncovered case, give the viable options and their observable consequences, and wait for the answer before implementing that part. Continue independent work. If the developer has already explicitly chosen the approach for this case, use that answer directly. For example, the existing GET search rule covers ordinary submitted searches. A multi-action form whose destinations and validation behavior the skill leaves open requires a decision.

Once the developer chooses, apply the decision and [submit the skill update](references/record-a-decision.md) to `robuust/boost` as a pull request in the same task.

## Ordinary endpoint forms

- Start with `<Form :action="update(user.id)">` and named fields for ordinary create, edit, delete and submitted GET search forms. Use the generated Wayfinder action when the project has it. Let its method travel with the action. Make the submit control a real submit button, including when it is rendered by a UI component.
- Read `errors`, `processing`, `isDirty` and `submit` from the form slot where needed. Use `disable-while-processing` when the whole form should be inert. Use `processing` for a visible spinner, including in the footer. Close on success. On validation failure keep entered values, the open form and its own errors. Use `reset-on-success` to clear a completed entry or `set-defaults-on-success` when saved values should become the clean baseline of an editor that stays open.
- Prefer `<Drawer :as="Form">` or `<Modal :as="Form">` when the installed component forwards the required form props, events and scoped slots. Check the actual field and footer button association across teleportation. If a drawer close event needs dirty state outside the slot, an explicit inner Form with a ref and a footer button associated by form ID can be simpler. Use the existing dialog structure that exposes the required state.
- Bind named controls to the Laravel Form Request's expected shape, including nested fields, arrays and checkbox values. Disabled inputs are omitted from native form data. Check whether a read-only value still has to travel and what a custom control actually submits. Use a transform for a real payload difference, such as editable rows outside the DOM or the clicked button's destination. Let the request or resource supply canonical types and defaults when it owns them.
- Use `useForm` when programmatic control matters, such as reordering a draft or initializing a reused editor for a different record. One submission has one owner for the request, errors and processing. In a complex editor, `<Form>` can still own submission while a separate draft provides editable data and dirty tracking. Use native defaults, reset and dirty state before introducing snapshots, watchers or remounts. A key is appropriate when changing record identity should start a fresh form.
- For multiple actions, establish what distinguishes them on the server. A named submit button can express “save” or “save and continue” on one endpoint. If the endpoints differ, check how the installed Form resolves the action; a DOM button's `formaction` alone does not establish which URL Inertia will visit.

For search/history, reset on record switches, validation across multiple forms and component association, use [Inertia patterns](../inertia-patterns/SKILL.md).

## Match the response to the caller

| What the caller needs | Laravel and Vue contract |
| --- | --- |
| An Inertia page | Return `Inertia::render()` with the page's props. Use a Link for ordinary Inertia navigation and the router for programmatic visits. Keep document navigation for destinations outside the Inertia app. |
| Save and display the resulting state | Validate with the project's Form Request, persist, then return `to_route(...)` or `back()` to the intended GET page. Let Laravel/Inertia carry validation errors through the redirect. Consume the refreshed props and form lifecycle. Add a second reload only for a separate update the response cannot supply. |
| One-time feedback or a created/job ID | Follow the existing producer and consumer. Native `Inertia::flash()` is read from `page.flash` on supporting adapters. Some projects expose Laravel session flash via `page.props.flash` to a layout toast. Reuse the established channel and show the message once. A queued job's completion is separate from the submission's `processing`. |
| A standalone JSON result, file or stream | Use the endpoint's established HTTP client and response format. A search form can navigate to a results page while its autocomplete consumes JSON. Preserve backend-specific error handling for those requests. |

A computed preview may deliberately return the current page component and just the requested result. Preserve a direct page response after a write only when its route, canonical URL, validation and refresh behavior are deliberate. See the [review examples](references/review-decisions.md#response-contracts) for both cases.

## Prefetch detail links

When adding prefetching to a small, bounded list of GET detail links, use `prefetch="hover"` on the Inertia `Link` to limit speculative requests to links the user points at. Set `cache-for` to the agreed window for reusing a prefetched response.

## Give each prop a scope and a lifetime

1. **Who consumes it?** Return screen-specific data from the page controller. Put genuinely cross-page data (for example, authenticated user or navigation) in `HandleInertiaRequests`. Preserve parent shares and existing auth/resource fields. An enum used on a single screen can be a page prop even when its value is stable. For page-specific persistent layout state, follow the project's `setLayoutProps` convention where available.
2. **When can it change?** Keep user, tenant, permissions and route-sensitive flags current across visits. Return both applicable and cleared values when changing context. Consider a page-level `Inertia::once()` or middleware `shareOnce()` for reusable stable data when the cache lifetime and invalidation fit the audience, locale and scope.
3. **When is it needed?** Supply essential content in the initial response. Use deferred props for content that should load automatically after first render, with a suitable fallback. Use an optional prop plus a targeted `only` request for data first needed when a user opens a dialog. Put expensive reads inside the callback so omitted props skip the work.
4. **What else changes with it?** On same-component partial visits, include every dependent prop, such as rows, totals, available options or pagination. Reset previously merged or infinite-scroll results when a new filter starts a different collection. Verify that the chosen Form, Link or router API forwards the visit option in the installed release.

Render canonical props reactively. Use a computed value for a display transformation and local state for an editable draft. Follow the project's component contract: some projects pass content explicitly through props, while others let a page-specific drawer read its own page data via `usePage()`. Both approaches can consume page-scoped data.

Put a visit-specific effect in its callback and a user-edit-only effect on the input event. A scoped watcher remains appropriate when a change in server context must invalidate local state, for example clearing sensitive drafts on a project switch.

For the decisions behind these defaults, including accepted exceptions and examples from the projects, consult [review decisions](references/review-decisions.md).
