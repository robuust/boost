# Redirects: Craft vs nginx

## Decision Table

| Redirect Need | Preferred Layer | Rationale |
|---------------|-----------------|-----------|
| Slug/content migration for many static paths | nginx | Fast edge handling, no app bootstrap |
| Locale/host normalization | nginx | Deterministic and centralized |
| Redirect based on element status/site content | Craft | Requires Craft context |
| Redirect after form/controller workflow | Craft | Depends on request/session flow |
| Emergency campaign URL rewrite | nginx | Quick edge rollout |

## Generic nginx Patterns

Trailing slash normalization:

```nginx
rewrite ^/(.+)/+$ /$1 permanent;
```

Host-scoped permanent rewrites:

```nginx
if ($host ~* ^(www\.)example\.com$) {
  rewrite ^/old-path/?$ /new-path permanent;
}
```

HTTPS enforcement:

```nginx
if ($http_x_forwarded_proto = 'http') {
  return 301 https://$host$uri;
}
```

## Redirect Hygiene Rules

1. Keep redirects single-hop: `old -> final`.
2. Never create loops (`A -> B -> A`).
3. Use permanent redirects (`301`) for canonical URL migrations.
4. Normalize destination URLs (scheme, host, path style).
5. Avoid defining conflicting rules across Craft and nginx for the same source URL.

## Verification Commands

Single URL check:

```bash
curl -I https://www.example.com/old-path
```

Expected:

- Status: `301` (or `308` if intentionally used)
- `Location` header points directly to final canonical URL

Follow chain check:

```bash
curl -I -L https://www.example.com/old-path
```

Expected:

- One redirect hop before final `200`
- Final URL matches canonical expectation

Host/locale checks:

```bash
curl -I https://www.example.nl/legacy
curl -I https://www.example.de/legacy
```

Expected:

- Locale-appropriate destinations
- No cross-locale accidental redirects
