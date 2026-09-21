---
name: work-in-wordpress-extension
description: Work safely in one existing Tagore WordPress plugin or theme. Use when inspecting or changing a registered component; do not use for new-component scaffolding, workspace-only changes, deployment, or an unauthorized cross-component change.
---

# Work in a WordPress extension

## Start and scope

1. Start at the repository root; read the complete root `AGENTS.md`, then the
   component's `AGENTS.md` if present.
2. Resolve the component in `config/workspace-components.tsv` and run
   `git status --short`. Preserve unrelated work.
3. State whether the target is plugin or theme and identify source path,
   runtime symlink and relevant tests. Never edit the runtime copy directly.
4. For observable behavior, load and follow sibling skill
   `../test-driven-wordpress-change/SKILL.md` before product changes.

## Design checks

- Place persistent behavior and domain data in plugins; place presentation,
  templates, patterns and theme-bound styles in themes.
- Reuse WordPress APIs and existing project boundaries. Do not create a
  parallel settings, roles, request, database, HTTP, filesystem or asset
  mechanism without an approved architecture decision.
- Keep hook registration/coordinators thin. Separate domain/service logic,
  data access and rendering. Do not load sibling internals by relative paths.
- Identify the canonical source for slugs, versions, capabilities, settings,
  schema and shared contracts before changing them.
- Check optional plugin/theme dependencies before use; deactivation or theme
  switching must not cause fatal errors.

## Security and privacy

- Authorize server-side with the narrowest capability and object scope.
- Protect writes with a nonce; define a nontrivial REST
  `permission_callback`. Sanitize/validate input and escape output for its
  exact context.
- Bind SQL values through `$wpdb->prepare()` or an approved project helper.
  Never derive SQL identifiers or paths from untrusted input.
- Keep secrets and real personal data out of source, logs, fixtures and docs.
  Make failure states diagnosable without logging sensitive payloads.

## Stop gates

Unless already explicit in the request, stop before schema/data migrations,
roles/capabilities/authentication, public shared-plugin contracts,
cross-component edits, uploads/file paths, external services, activation or
theme switching, DDEV/config changes, destructive moves, or changes without a
safe rollback. Report risk, files, tests and rollback needed for approval.

## Verification and completion

1. Run the narrow component tests, syntax/static checks and relevant negative
   security cases.
2. Run `scripts/check-fast` from the repository root.
3. For WordPress behavior, verify the registered symlink and use the local
   DDEV integration path only when authorized. Report browser/UI checks
   separately from automated checks.
4. Finish with scope, changed files, commands/results, skipped checks, risks,
   learning candidates, and final Git status/diff summary. Never commit,
   deploy or use `git add .` without explicit authorization.
