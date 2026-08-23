---
name: test-driven-wordpress-change
description: Apply Red-Green-Refactor to a Tagore WordPress feature, bug fix, security rule, hook, REST/AJAX behavior, database behavior, block, template, or other observable change. Do not use for documentation-only, formatting-only, generated, or purely mechanical edits without meaningful behavior.
---

# Test-drive a WordPress change

## Contract before code

Before editing production code, record:

- the domain or security invariant;
- observable target behavior and relevant negative/edge cases;
- the smallest honest test level;
- what the test proves and does not prove;
- required WordPress/DDEV integration or manual UI evidence.

Prefer a pure PHP/JavaScript unit test for isolated logic, a component contract
test for hooks/rendering/headers, and a DDEV integration test for WordPress
bootstrap, roles, REST, database, activation, upgrades or real enqueueing.
Theme layout and accessibility may require browser/manual evidence in addition
to automated structural tests.

## Red

1. Add or adjust the smallest test that expresses the missing behavior.
2. Include a meaningful denied, invalid, manipulated or failure case for each
   affected protection boundary and assert absence of forbidden side effects.
3. Run the focused test before production changes.
4. Confirm it fails for the expected product reason, not syntax, fixture,
   bootstrap or environment failure. An immediately green test is only a
   labelled characterization test, not Red evidence.

## Green

1. Implement only enough production code to satisfy the invariant.
2. Use native WordPress APIs, existing helpers and the correct plugin/theme
   boundary. Avoid adjacent refactors.
3. Run the focused test until green, then the component suite and relevant
   provider/consumer tests.

## Refactor and regressions

Refactor only under green tests. Re-run security negatives, syntax/static
checks, `scripts/check-fast`, and the required DDEV/browser path. If an
environment check cannot run, name the gap rather than weakening the test.

## Allowed deviations

For a time-boxed spike or hard-to-isolate WordPress integration, state why a
test cannot lead, keep spike code disposable, and add characterization or the
truthful integration test before adoption. Declarative changes use JSON,
header, structure, accessibility or rendering contracts instead of synthetic
unit tests.

## Report

Report invariant, test level, Red command/failure reason, minimal Green
implementation, all executed checks/results, untested risks, coverage where
available, and every justified deviation.
