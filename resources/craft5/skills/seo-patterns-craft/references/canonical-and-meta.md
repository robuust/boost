# Canonical and Metadata Patterns (Craft CMS)

## Canonical Guard Pattern

Render canonical only when the current request represents the canonical entry path.

```twig
{% set entryPath = entry.uri == '__home__' ? '' : entry.uri %}
{% set isCanonicalRequest = entryPath == craft.app.request.pathInfo %}

{% if isCanonicalRequest %}
  <link rel="canonical" href="{{ entry.url }}">
{% endif %}
```

Why:
- Prevent custom routes, filtered states, and alternate render paths from publishing misleading canonical signals.

## Metadata Fallback Order

Use a strict and predictable fallback order:

1. `seoMetaTitle`
2. `entry.title`

For description:

1. `seoMetaDescription`
2. concise content-derived fallback
3. final minimal fallback (entry title)

Example:

```twig
{% set metaTitle = entry.seoMetaTitle ?: entry.title %}
{% set metaDescription = entry.seoMetaDescription ?: entry.title %}

<title>{{ metaTitle }}</title>
<meta name="description" content="{{ metaDescription }}">
<meta property="og:title" content="{{ metaTitle }}">
<meta property="og:description" content="{{ metaDescription }}">
```

## Open Graph Alignment

For a given page state, keep these in sync:

- `<title>`
- `meta[name="description"]`
- `meta[property="og:title"]`
- `meta[property="og:description"]`
- `meta[property="og:url"]`

Do not set `og:url` to a URL that disagrees with canonical intent.

## Hreflang and x-default

Rules:

1. Emit alternates only for enabled site variants with valid URLs.
2. Emit exactly one `x-default`.
3. Keep language tags valid (`en`, `nl`, `de`, `en-GB`, etc.).
4. Do not emit alternates for temporary filtered URLs.

## Robots / Indexing by Environment

Default pattern:

- Production: no robots override unless intentionally needed.
- Non-production: always output:

```html
<meta name="robots" content="noindex, nofollow">
```

Use one shared production flag and keep logic centralized in base layout/meta template.
