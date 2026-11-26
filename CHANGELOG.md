# Changelog

## Unreleased

### Fixed

- Docs search excerpts keep code examples whole instead of cutting them off mid-block, and skip a code block that ends before the match.

## 0.1.0 (2026-11-25)

### Added

- Laravel Boost extension for Laravel and Craft 6: a core guideline with the test authorization rule, an Inertia guideline, a Craft 6 (beta) guideline and the Laravel and Inertia skills, selected per stack.
- `vendor/bin/robuust-agents sync` for Craft 5, writing the same layout as Boost: a generated `AGENTS.md` block, `CLAUDE.md`, `.ai/rules/index.md`, skills for Codex and Claude Code, and MCP config.
- `sync` on Boost projects runs `boost:update` behind a guard that stops when a selected package is not installed; `sync --check` fails CI when generated files are out of date.
- Craft CMS 5 and 6 docs search from the versioned `craft-llms` index, without falling back to another major, and Tailwind CSS 4 docs search. Code terms such as `@apply` or `with()` rank first, a matching heading opens the excerpt, and the Tailwind version check reads `yarn.lock` or `package-lock.json` when `node_modules` is missing.
- `vendor/bin/robuust-agents mcp` serving the docs search to Craft 5 projects, and `index-rules` to regenerate `.ai/rules/index.md`.
- Published as a public package under the MIT license, installable over HTTPS without credentials.
- Craft docs search falls back to the published full text, so API names that only appear in page text, such as `andWhere()`, are found.
- The move from Craft 5 to Craft 6 retires the Craft 5 block, skills and MCP entries once `boost:update` has written its replacement, and puts them back when it has not.
