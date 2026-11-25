# robuust/boost

Robuust's project layer for coding agents. One Composer package gives Laravel, Craft 6 and Craft 5 projects the same agent setup for Codex and Claude Code:

- **Guidelines** in a generated block in `AGENTS.md`, including the test authorization rule. A root `CLAUDE.md` imports `@AGENTS.md`, so Claude Code reads the same file.
- **Stack skills** in `.agents/skills` (Codex) and `.claude/skills` (Claude Code; on Craft 5 a symlink to `.agents/skills`).
- **Project rules** in Boost's `.ai/rules` format, with a generated `.ai/rules/index.md`.
- **Docs search** for Craft CMS (5 and 6) and Tailwind CSS 4, as MCP tools.

Workflow skills (git, pull requests, reviews) are not part of this package; they ship as the `robuust` plugin from the `robuust-agents` marketplace.

## What each stack gets

| Stack | Route | Guidelines | Skills | Docs tools |
| --- | --- | --- | --- | --- |
| Laravel + Inertia + Vue | Laravel Boost extension | core, Inertia | `inertia-best-practices`, `inertia-patterns`, `design-slice-inertia`, `vue-vitest-coverage`, `laravel-seeding-patterns` | Boost's `search-docs` |
| Laravel without Inertia | Laravel Boost extension | core | `laravel-seeding-patterns` | Boost's `search-docs` |
| Craft 6 (beta) | Laravel Boost extension | core, Craft 6 (beta), Inertia only when the project requires `inertiajs/inertia-laravel` itself | `laravel-seeding-patterns`, the Inertia skills only with Inertia | `search-craft-docs` on Boost's MCP server |
| Craft 5 | `vendor/bin/robuust-agents sync` | core, Craft 5 | `fe-craft`, `be-craft`, `hotwired-patterns-craft`, `tailwind-patterns-twig`, `seo-patterns-craft`, `gtm-patterns-craft`, `infer-craft-conventions` | `search-craft-docs` and `search-tailwind-docs` on the `robuust` MCP server |

A Laravel project gets no Craft content. Selected packages that ship `resources/boost/skills` (for example `@robuust-digital/vue-components`) add their own skills on every stack.

## Install

The package is public and not on Packagist yet; it loads through a `vcs` repository over HTTPS:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/robuust/boost.git" }
    ],
    "require-dev": {
        "robuust/boost": "^0.1"
    }
}
```

Until the first release, require `dev-develop` instead of `^0.1`.

No credentials are needed, also not in Docker builds or CI. Without a GitHub token, Composer reads this repository through GitHub's unauthenticated API, which allows 60 requests per hour per IP address; a shared CI runner that hits that limit needs a GitHub token in `COMPOSER_AUTH`. Heroku builds run with `--no-dev`, so they never install the package.

## Rollout

### Laravel and Craft 6

1. `composer require --dev robuust/boost` (with the repository above). `laravel/boost` must be installed too.
2. Make sure the root `CLAUDE.md` imports `@AGENTS.md`. Create it with just that line if the project has none.
3. Run `php artisan boost:install` and select Codex, Claude Code and `robuust/boost`. To do it without prompts, put `"agents": ["claude_code", "codex"]` and `"robuust/boost"` under `packages` in `boost.json`, then run `php artisan boost:install --no-interaction --guidelines --skills --mcp`.
4. Replace any `@php artisan boost:update` in the Composer `post-update-cmd` with `vendor/bin/robuust-agents sync`.
5. Commit `boost.json`, `AGENTS.md`, `CLAUDE.md`, the skills directories and the MCP config.

The package sets `boost.agents.claude_code.guidelines_path` to `AGENTS.md`, so Boost writes one block, to `AGENTS.md`, even when a `CLAUDE.md` exists.

### Craft 5

1. `composer require --dev robuust/boost` (with the repository above).
2. Run `vendor/bin/robuust-agents sync`. The first run creates `robuust-agents.json`:

   ```json
   {
       "agents": ["codex", "claude_code"],
       "packages": []
   }
   ```

   `packages` lists Composer or npm packages whose `resources/boost/skills` are synced, for example `@robuust-digital/vue-components`.
3. Add `vendor/bin/robuust-agents sync` to the Composer `post-update-cmd` if you want it to run on every update.
4. Commit `robuust-agents.json` and everything the sync writes.

Craft 5 cannot run Laravel Boost, so the Craft 5 sync writes the agent files itself:

- a generated block in `AGENTS.md`, between `<robuust-agents-guidelines>` tags; text outside the block is kept. After a move to Craft 6, the first `sync` replaces this block, the generated skills and the `robuust` MCP entries with Boost's own. If `boost:update` fails or writes no `<laravel-boost-guidelines>` block, those Craft 5 files are put back;
- `CLAUDE.md` importing `@AGENTS.md`, added at the top of an existing file;
- `.ai/rules/index.md`, regenerated from the rule files in `.ai/rules`. Rules recorded by the legacy `craft_twig_rules` MCP keep their globs only in a `# Project rules` index table; the first sync moves those globs into each rule file's `paths:` frontmatter, so no rule drops out of the index;
- skills in `.agents/skills`, with a `.robuust-agents-manifest.json` so skills that are no longer shipped get removed; skills you add yourself are left alone. A hand-written skill with the same name as a shipped one stops the sync until you rename or remove it. With both agents, `.claude/skills` is a symlink to `../.agents/skills`, so the project holds one copy. Move hand-written skills from an existing `.claude/skills` to `.agents/skills` first; the sync stops until you do;
- the `robuust` MCP server (`php vendor/bin/robuust-agents mcp`) in `.mcp.json` and `.codex/config.toml`, next to any other servers. In `.codex/config.toml` the sync sets only `command` and `args`, so other keys in the `[mcp_servers.robuust]` table, such as `env` or a timeout, stay.

Removing an agent from `robuust-agents.json` removes what the sync generated for it: its skills (or the `.claude/skills` symlink) and its `robuust` MCP entry. Hand-written skills, other servers and the `@AGENTS.md` import in `CLAUDE.md` stay.

## Commands

| Command | What it does |
| --- | --- |
| `vendor/bin/robuust-agents sync` | Checks that every selected package is installed, then runs `php artisan boost:update` (Laravel, Craft 6) or writes the Craft 5 layout. |
| `vendor/bin/robuust-agents sync --check` | Runs the same sync without leaving changes and exits 1 when the generated files would change. |
| `vendor/bin/robuust-agents mcp` | Runs the docs search MCP server over stdio (Craft 5). |
| `vendor/bin/robuust-agents index-rules` | Regenerates `.ai/rules/index.md` in Boost's format, migrating a legacy `craft_twig_rules` index first. |

All commands take `--root=<path>` to work on another directory.

**The guard.** If a package selected in `boost.json` or `robuust-agents.json` is missing from `vendor/` or `node_modules/`, sync names the package and the install command, exits 1 and writes nothing. This stops a Composer update that runs before `yarn install` from dropping an npm package's skills. Install the dependencies and run the same command again. To remove a package on purpose, remove it from the selection and sync.

**`--check` on Boost projects.** The check runs `boost:update`, compares the files each agent in `boost.json` writes (by Boost's default paths for that agent) and puts back every path that changed, including permission bits and directories. It reports changed files and executable bits; directories alone do not count, because Git does not track them. The check stops before running anything if `boost.json` lists an agent this package does not know, or if `config/boost.php` sets `guidelines_path`, `skills_path` or `mcp_config_path`: Boost would then write files the check cannot restore.

**Boost and the environment.** Boost registers its commands only in a local or debug environment, so sync runs `boost:update` with `APP_ENV=local`.

## CI

Run the check after both Composer and Yarn have installed, so a stale skill after a Yarn-only change fails the build:

```yaml
- composer install
- yarn install --frozen-lockfile
- vendor/bin/robuust-agents sync --check
```

## Docs search

Craft docs come from the versioned index that `robuust/craft-llms` builds and Netlify serves (`{major}/index.json` and `{major}/pages/{path}`). Every result names the requested major, the served major, the page URL, its source path and the docs revision. When the requested major has no index, the tool says so and does not fall back to another major.

When no page title, heading or path matches the query, the Craft search falls back to `{major}/llms-full.txt` from the same base URL. It scans the page bodies for the query's code terms (or plain words when there are none) and matches the pages to the index by title, so names that only appear in page text, such as `andWhere()`, are still found. The full text is downloaded only when that fallback runs, at most once per index revision per process, and a failed download gives no results. A long-term option is for `craft-llms` to publish per-page body terms (for example code identifiers) in `index.json`, which would make the fallback unnecessary.

The default base URL is `https://craft-llms.netlify.app`. Override it with the `ROBUUST_DOCS_BASE_URL` environment variable, `docs_base_url` in `robuust-agents.json` (Craft 5) or `robuust-boost.docs_base_url` in Laravel config. The environment variable wins.

Tailwind CSS 4 docs come from the official `tailwindlabs/tailwindcss.com` source, pinned to the commit they were read at. The tool refuses projects whose Tailwind major is not 4.

The `robuust` MCP server uses the official PHP MCP SDK, speaks protocol `2025-06-18` and always returns `structuredContent` as an object.

## Changing guidelines and skills

- Guidelines live in `resources/boost/guidelines` (Laravel and Craft 6) and `resources/craft5/guidelines`. `core.md` is shared by both routes.
- Skills live in `resources/boost/skills` and `resources/craft5/skills`. Edit them here, never in a project's `.agents/skills` or `.claude/skills`; the next sync overwrites those copies.
- New conventions from `inertia-best-practices` come in as pull requests to this repository.
- Guidelines select their stack with Blade conditions on `Robuust\Boost\Stack::current()`; Boost drops a guideline that renders empty. Skills select their stack through `boost.skills.exclude`, set by the service provider.

## Development

```sh
composer install
vendor/bin/php-cs-fixer fix --dry-run --diff
vendor/bin/phpstan analyse
vendor/bin/phpunit
```

Buildkite (`buildkite.yml`) runs these on PHP 8.5 in `ghcr.io/robuust/heroku-php:heroku26`, with `mcp/sdk` 0.9 (the version Craft 5.12 and Craft 6 lock) and 0.10.
