## Boost override preferences (single source of truth)

This repository uses **Laravel Boost** defaults as a base, but we apply a small set of **project-specific overrides**.

The purpose of this document is to make it easy to re-apply only these overrides after a Boost update overwrites:

- `.cursor/rules/laravel-boost.mdc`
- `.cursor/skills/pest-testing/SKILL.md`

This document is intentionally short and **only lists the explicit overrides** we want. Do not revert files to a prior historical state; only apply the overrides below on top of whatever Boost ships today (versions, package lists, etc. must remain as Boost updated them).

## Why this exists

Boost updates can rewrite default rule/skill files. When that happens, we want a deterministic way to restore the team’s preferences without:

- undoing Boost’s updated package versions / guidance
- reintroducing outdated conventions
- reapplying unrelated local customizations

## What to override

### 0) Skills activation is mandatory for every task

**Target**: `.cursor/rules/laravel-boost.mdc` and `AGENTS.md`

Add/keep explicit guidance that:

- Before starting any task or sub-step, always review `.cursor/skills`.
- Explicitly verify whether one or more skills apply to the current task.
- If the work is split into steps, repeat this skills check before each step.

### 1) File placement & contextual namespaces

**Target**: `.cursor/rules/laravel-boost.mdc`

Ensure the rule includes a section equivalent to:

- When creating Traits, Interfaces (Contracts), DTOs (Laravel Data), Rules, Support classes, and similar, always create them under `app/` (e.g. `app/Concerns`, `app/Contracts`, `app/Data`, `app/Rules`, `app/Support`).
- Always use contextual namespaces that reflect the domain/feature area.
- Before creating a new folder/namespace segment, inspect existing structure first to match conventions.

### 2) Frontend commands: prefer `pnpm run ...`, but usually no build needed

**Target**: `.cursor/rules/laravel-boost.mdc`

- Prefer `pnpm run ...` commands.
- Mention that the user typically keeps `composer run dev` running (which runs `pnpm run dev`), so we usually do **not** need to ask them to build.
- Only ask them to start/restart `composer run dev` (or `pnpm run dev`) if UI changes are not reflected.
- If the repo uses Volta to pin versions, mention to prefer the pinned toolchain.

### 3) Naming: class names must end with their type

**Target**: `.cursor/rules/laravel-boost.mdc`

Add/keep a convention that classes should end with a suffix matching their role:

- Listeners end with `Listener` (e.g. `SomethingListener`)
- Events end with `Event` (e.g. `SomethingEvent`)
- Data objects (Spatie Laravel Data) end with `Data` (e.g. `SomethingData`)
- Enums end with `Enum` (e.g. `SomethingEnum`)
- Actions end with `Action` (e.g. `SomethingAction`)

### 3) Enums: UPPERCASE_WITH_UNDERSCORES

**Target**: `.cursor/rules/laravel-boost.mdc`

Replace any Enum key naming guidance (e.g. TitleCase) with:

- Enum keys should be `UPPERCASE_WITH_UNDERSCORES` (e.g. `FAVORITE_PERSON`, `BEST_LAKE`, `MONTHLY`).

### 4) Events: do NOT manually bind listeners

**Target**: `.cursor/rules/laravel-boost.mdc`

Add/keep a strong warning:

- **Do NOT manually bind events to listeners** (no `EventServiceProvider` reintroduction, no manual registration).
- The codebase relies on auto-discovery + type-hint based inference via `handle(SomeEvent $event): void`.
- Manual binding + auto-discovery can cause listeners to run twice.

### 5) Static analysis: Larastan / PHPStan safety (type sense max)

**Target**: `.cursor/rules/laravel-boost.mdc`

Add/keep explicit guidance that:

- The project uses Larastan with **maximum strictness** (type sense max). All generated PHP code must be **PHPStan/Larastan-safe**.
- Always add precise types for:
    - property / parameter types
    - return types
    - collections / arrays (use PHPDoc array shapes / generics where helpful)
    - nullable and union types when needed
- Avoid “loose” typing patterns that will fail static analysis (e.g. mixed/implicit/guessed shapes, untyped arrays, missing return types, or ambiguous callbacks).

### 5) Pest testing style: `describe()` + avoid `$this`

**Target**: `.cursor/skills/pest-testing/SKILL.md`

- Require exactly **one** `describe()` per test file.
- Always use the class under test as the `describe()` name (e.g. `SomeClass::class`).
- Add/keep “Avoid `$this` in tests”: prefer Pest/Laravel function helpers (e.g. `actingAs()`, `get()`, `postJson()`) with appropriate imports.

### 6) Unit test assertions: prefer invariants over snapshots

**Target**: `.cursor/skills/pest-testing/SKILL.md`

Include “Good targets for unit assertions” guidance:

- Assert structure and invariants (ordering rules, deterministic fallback rules).
- Avoid asserting volatile labels from translations/timezones/locales/config lists unless made deterministic inside the test.
- When exact values matter, stub the source (e.g. set `config()` explicitly, `Translator::addLines()`).

### 7) Pest testing: DO NOT snapshot enum options / labels

**Target**: `.cursor/skills/pest-testing/SKILL.md`

Add an explicit “Avoid brittle enum options snapshot tests” section. It must clearly prohibit tests like:

- Asserting `Enum::options()` equals an exact array of keys/labels (e.g. `expect(ProfilePriorityEnum::options())->toBe([...])`).
- Asserting the _full_ set of enum cases/options/labels unless the **exact set** is a hard product requirement.

Rationale (must be stated explicitly): these tests are **high-churn, low-signal**. They fail whenever a new enum value is added, forcing meaningless test updates and providing no behavioral coverage.

Preferred alternatives (include concrete guidance):

- Assert **shape and types** (e.g. options is a non-empty array; every option label is a string).
- Assert **invariants** (e.g. keys are strings; labels are non-empty; ordering rule holds; specific critical options exist only when they are contractually required).
- Only assert exact labels/values when there is a real contract (public API payload, persisted values, strict UI contract) and the contract is intentionally stable.

### 8) Pest: enforce 100% type coverage (including callback returns)

**Target**: `.cursor/rules/laravel-boost.mdc`

Add/keep a strong “type coverage” requirement for tests:

- We use Pest to validate **100% type coverage**. Everything must be typed.
- This includes “vague” callbacks: always type even the **return type** of the loosest callback.

### 9) Final verification: prefer `composer lint` (not Pint)

**Target**: `.cursor/rules/laravel-boost.mdc`

Replace any “run Pint before finalizing” guidance with:

- Prefer running `composer lint` as the final verification command.

### 10) Filament: avoid manual eager loading in resources

**Target**: `.cursor/rules/laravel-boost.mdc`

Add/keep a Filament-specific note:

- In Filament resources (tables/forms/infolists), **do not** add manual eager loading (`->with(...)`, `->withCount(...)`) by default.
- Rationale: Filament can infer eager loading from relationship columns/filters, and the app globally prevents lazy loading via `Model::preventLazyLoading()` in `app/Providers/AppServiceProvider.php`, so redundant eager loading becomes noise.
- Only add explicit eager loading in Filament when you can demonstrate a real need (e.g. relation accessed in a custom `state()`/`formatStateUsing()` callback that Filament can’t infer).

### 11) Eloquent: prefer `whereRelation()` over `whereHas()` for simple relationship constraints

**Target**: `.cursor/rules/laravel-boost.mdc`

Add/keep a preference that for simple “relation column equals value” constraints, we prefer `whereRelation()` over `whereHas()`:

- Prefer `Model::query()->whereRelation('comments', 'is_approved', '=', false)` over `whereHas('comments', fn ($q) => $q->where('is_approved', false))`.
- This keeps filters more readable and reduces nested closures.

### 12) Enums: pass backed enums directly to Eloquent `where*` methods (no `->value`)

**Target**: `.cursor/rules/laravel-boost.mdc`

Add/keep a strong preference:

- When comparing against a backed enum in Eloquent queries (including `where`, `whereIn`, `whereRelation`), pass the enum case directly (Laravel will handle it).
- Avoid `->value` unless you are explicitly working with raw scalar values outside Eloquent (e.g. serialization, array keys, non-Eloquent APIs).

## How to re-apply after a Boost update

1. Re-check the current Boost-generated defaults in:
    - `.cursor/rules/laravel-boost.mdc`
    - `.cursor/skills/pest-testing/SKILL.md`
2. Apply only the overrides listed in this document, keeping:
    - Boost’s updated version numbers and package lists
    - Boost’s other guidance that we did not explicitly override here
3. Confirm the final files still reflect only these overrides (no extra “local preferences” creep).
