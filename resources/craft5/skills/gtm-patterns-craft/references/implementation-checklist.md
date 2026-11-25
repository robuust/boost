# GTM Implementation Checklist (Craft + Turbo)

Use the checks that apply to the requested tracking change. Establish configuration from project evidence; ask only for missing values or ownership decisions that affect implementation.

## Setup

- Confirm GTM container ID and owner of consent configuration.
- Confirm production environment condition used in templates.
- Confirm if project uses Turbo morph refresh.

## Template Placement

- Place GTM bootstrap script in `<head>`.
- Place GTM `noscript` iframe as first practical element in `<body>`.
- Keep bootstrap placement stable across layouts.

## Turbo Behavior

- Register `turbo:load` listener once.
- Push one virtual pageview event per Turbo navigation.
- Use a stable payload contract:
  - `event`: `pageView`
  - `virtualUrl`: `event.detail.url` fallback to `window.location.href`

## Ordering & Safety

- Keep one production guard region for tracking snippets.
- Respect required order if consent or site script integration depends on it.
- Avoid duplicating initialization in partial templates/fragments.

## Verification

- Verify tracking scripts load only in production and remain disabled in non-production environments.
- Validate first load and Turbo navigation in browser devtools.
- Validate `dataLayer` receives expected payloads.
- Validate no duplicate GTM bootstrap or duplicate pageview events.
