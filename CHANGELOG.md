# Changelog

## Unreleased

### Added

- Laravel Boost extension for Laravel and Craft 6: a core guideline with the test authorization rule, an Inertia guideline, a Craft 6 (beta) guideline and the Laravel and Inertia skills, selected per stack.
- `vendor/bin/robuust-agents sync` for Craft 5, writing the same layout as Boost: a generated `AGENTS.md` block, `CLAUDE.md`, `.ai/rules/index.md`, skills for Codex and Claude Code, and MCP config.
- `sync` on Boost projects runs `boost:update` behind a guard that stops when a selected package is not installed; `sync --check` fails CI when generated files are out of date.
- Craft CMS 5 and 6 docs search from the versioned `craft-llms` index, without falling back to another major, and Tailwind CSS 4 docs search. Code terms such as `@apply` or `with()` rank first, a matching heading opens the excerpt, and the Tailwind version check reads `yarn.lock` or `package-lock.json` when `node_modules` is missing.
- `vendor/bin/robuust-agents mcp` serving the docs search to Craft 5 projects, and `index-rules` to regenerate `.ai/rules/index.md`.
- Published as a public package under the MIT license, installable over HTTPS without credentials.
