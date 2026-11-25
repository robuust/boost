# Inertia Interaction Patterns

Use the section matching the requested interaction. These are shared defaults; retain established project contracts and verify APIs against installed versions.

## Search and filters

- For an explicit search submission, prefer a GET `<Form>` with named controls, the existing route, and its own processing state for action availability.
- Keep URL query parameters as the submitted filter contract. Add local draft values only when the interaction needs them; preserve unrelated query parameters and reset pagination where the existing search contract requires it.
- Do not add manual serialization, a separate loading ref, or a router wrapper when the form already handles the required submission. Live/debounced search may justify a handler; follow that interaction's existing history and state-preservation behavior.

## Editable drawers

- When a reused drawer switches between records or its fields need programmatic updates, prefer `useForm` as the editable draft. Initialize the relevant fields and defaults at the record/open boundary; clear previous-record errors there as required.
- Reset to the intended baseline on cancel/reopen according to the requested UX. Do not overwrite unsaved edits whenever page props change, or force a remount merely to refresh defaults.
- A simple drawer with named inputs can use `<Form>`. When the drawer does not forward the form's scoped state, put `<Form>` outside the drawer so fields and actions can consume its errors and processing directly. Do not bridge missing slot state through event-synchronized refs.
- Keep the drawer open on validation failure; close on confirmed success when that is the intended flow. Reopening and switching records must not show another record's errors or stale draft.

## Delete confirmations

- Prefer a form-owned delete submission with the existing route and HTTP method. Disable the destructive submit action using the same owner's processing state and close after success, preserving visible failure feedback.
- `Modal :as="Form"` is suitable only when the installed modal forwards the required props, events, and scoped slot state. Otherwise compose an explicit form or use `useForm`; do not introduce duplicate pending/error state to make the shorthand work.

## Robuust component composition

Resolve `Modal` and `Drawer` separately through the `vue-components-mcp` skill (shipped with `@robuust-digital/vue-components`). An `as` prop controls rendering; it does not establish that the component forwards another component's scoped slots.

The Drawer implementation inspected in `@robuust-digital/vue-components` 3.2.0 does not forward Inertia Form's scoped errors/processing state. Prefer Form wrapping Drawer when using `<Form>` with that contract. Recheck installed source or MCP documentation before applying this observation to another version; do not assume Modal and Drawer are interchangeable.

Inspect the rendered form ownership when a dialog teleports content: fields and submit actions must belong to the intended form, with no nested forms. Bind the real component's loading/error props to the form owner, keeping its event names and slot contracts exact.

## Framework references

The conventions above choose among supported APIs. Use the installed version's documentation for their exact signatures and behavior. For v3, see [forms](https://inertiajs.com/docs/v3/the-basics/forms) and [validation](https://inertiajs.com/docs/v3/the-basics/validation).
