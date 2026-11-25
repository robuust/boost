# SEO Validation Checklist (Craft CMS)

Select checks for the changed URL or metadata behavior. Release and monitoring sections apply only when those stages are requested; this checklist does not authorize deployment, scheduling, or test-suite execution.

## Pre-merge Checks

- [ ] Canonical tag is present only on canonical requests.
- [ ] Page title, description, and Open Graph fields are consistent.
- [ ] `hreflang` links point to valid enabled-site URLs.
- [ ] `x-default` is present once (where multilingual routing requires it).
- [ ] Non-production outputs `noindex, nofollow`.
- [ ] New redirects are single-hop and permanent for migrated URLs.

## Pre-release Checks

- [ ] Inspect representative affected URLs from each relevant locale/site.
- [ ] Validate redirect behavior with `curl -I` and `curl -I -L`.
- [ ] Confirm no unexpected canonical on filtered/query-driven pages.
- [ ] Verify important legacy URLs resolve to current canonical destinations.
- [ ] Check that sitemap/canonical/hreflang signals are mutually consistent.

## Regression Checks After URL Changes

- [ ] Spot-check top traffic landing pages and latest migrated URLs.
- [ ] Spot-check host normalization (`http -> https`, `www` policy).
- [ ] Confirm no new multi-hop redirect chains were introduced.
- [ ] Confirm stale URLs do not return `200` when they should redirect.
- [ ] Confirm localized URLs do not collapse into wrong locale targets.

## Monitoring Checklist

- [ ] Track 404 and 5xx spikes after deploy.
- [ ] Watch redirect-heavy endpoints for unusual volume.
- [ ] Review search console coverage/canonical warnings.
- [ ] Review indexing changes for non-production leakage.
- [ ] When recurring audits are requested, agree on their scope and schedule; remove obsolete rewrite rules only within authorized cleanup work.
