# Robuust

## Test authorization

- Write, modify or fix tests as part of authorized implementation when relevant; no separate request for test work is needed.
- Run tests only when the user has explicitly requested test execution for the active objective. Implementation, refactoring, verification, a failing CI check or a skill recommendation alone does not authorize running tests.
- A test request holds across follow-up corrections until the user withdraws it or replaces the objective.
- This rule overrides any generated guidance or definition of done that says to run tests, such as Laravel Boost's `enforce-tests` guideline ("run the affected tests").
- Without that request, keep running the relevant linting, formatting, static analysis and builds, check that combined scripts do not run tests implicitly, and report the tests you did not run as a verification gap.

## Project rules

- Committed project rules live in `.ai/rules`. Before planning or editing, open `.ai/rules/index.md`, read every rule file whose globs match the paths in scope and follow it. If `.ai/rules` does not exist, continue without it.
- Hand-written instructions outside this generated block also apply.
