---
name: pest-testing
description: >-
    Tests applications using the Pest 4 PHP framework. Activates when writing tests, creating unit or feature
    tests, adding assertions, testing Livewire components, browser testing, debugging test failures,
    working with datasets or mocking; or when the user mentions test, spec, TDD, expects, assertion,
    coverage, or needs to verify functionality works.
---

# Pest Testing 4

## When to Apply

Activate this skill when:

- Creating new tests (unit, feature, or browser)
- Modifying existing tests
- Debugging test failures
- Working with browser testing or smoke testing
- Writing architecture tests or visual regression tests

## Documentation

Use `search-docs` for detailed Pest 4 patterns and documentation.

## Basic Usage

### Creating Tests

All tests must be written using Pest. Use `php artisan make:test --pest {name}`.

### Test Organization

- Unit/Feature tests: `tests/Feature` and `tests/Unit` directories.
- Browser tests: `tests/Browser/` directory.
- Do NOT remove tests without approval - these are core application code.

### Basic Test Structure

<code-snippet name="Basic Pest Test Example" lang="php">

use App\Data\OwnerForms\OwnerFormData;

describe(OwnerFormData::class, function (): void {
it('is true', function () {
expect(true)->toBeTrue();
});
});

</code-snippet>

### Style Rules

- Require exactly one `describe()` per test file.
- Always use the class under test as the `describe()` name (e.g. `SomeClass::class`).
- Avoid `$this` in tests: prefer Pest/Laravel function helpers (e.g. `actingAs()`, `get()`, `postJson()`) with appropriate imports.
- Prefer factories for test data setup. When persistence is not required, use non-persisted factories (`make()` / `makeOne()`) to keep tests fast and isolated.
- For readability, define datasets in a dedicated variable outside `describe()` (e.g. `$datasets = [...]`) and pass them with `->with($datasets)`.

### Running Tests

- Run minimal tests with filter before finalizing: `php artisan test --compact --filter=testName`.
- Run all tests: `php artisan test --compact`.
- Run file: `php artisan test --compact tests/Feature/ExampleTest.php`.

## Assertions

Use specific assertions (`assertSuccessful()`, `assertNotFound()`) instead of `assertStatus()`:

<code-snippet name="Pest Response Assertion" lang="php">

use App\Http\Controllers\Api\V1\Front\OwnerFormController;

describe(OwnerFormController::class, function (): void {
it('returns all', function (): void {
postJson('/api/v1/front/owner-forms', [])->assertSuccessful();
});
});

</code-snippet>

| Use                  | Instead of          |
| -------------------- | ------------------- |
| `assertSuccessful()` | `assertStatus(200)` |
| `assertNotFound()`   | `assertStatus(404)` |
| `assertForbidden()`  | `assertStatus(403)` |

### Unit test assertions: prefer invariants over snapshots

- Assert structure and invariants (ordering rules, deterministic fallback rules).
- Avoid asserting volatile labels from translations/timezones/locales/config lists unless made deterministic inside the test.
- When exact values matter, stub the source (e.g. set `config()` explicitly, `Translator::addLines()`).

### Explicit prohibition: avoid model metadata snapshots

Do **not** write unit tests that snapshot Eloquent model internals like:

- `fillable`
- `casts`
- `hidden`
- `appends`
- `getTable()`

Examples to avoid:

- `expect($model->getFillable())->toBe([...])`
- `expect($model->casts())->toMatchArray([...])`
- `expect(array_keys($model->toArray()))->toBe([...])`

**Why this is prohibited:**

- Low signal: these tests mostly re-assert framework wiring, not business behavior.
- High churn: harmless refactors (adding a field/cast/appended attribute) break tests with no real regression.
- Duplicate source of truth: the model already declares this metadata; testing it line-by-line creates maintenance noise.
- Poor bug detection: these tests rarely catch production issues compared to behavior tests.

**What to test instead:**

- Relationship behavior with real persisted records (including pivots where applicable).
- Scope behavior (correct include/exclude and ordering).
- Accessor/mutator side effects and domain rules.
- Serialization contracts only for stable/public keys (`toHaveKeys([...])`), not exact key snapshots.

**Allowed exception (rare):**

- Assert metadata only when it is a strict public contract with external impact (e.g. API version contract, security-sensitive hidden fields policy). Document the reason in the test name or a short note.

### DO NOT snapshot enum options / labels

Avoid brittle “snapshot” unit tests that assert exact arrays for enum options / labels, for example:

- `expect(ProfilePriorityEnum::options())->toBe([...])`
- `expect(JuridicalIdentifierTypeEnum::options())->toBe([...])`

**Rationale**: these tests are high-churn and low-signal. They fail whenever a new enum case is added, forcing meaningless test updates without providing behavioral coverage.

Prefer assertions on **shape**, **types**, and **invariants**, such as:

- `Enum::options()` returns a non-empty array.
- Every key is a string and every label is a string.
- Labels are non-empty strings.
- Assert only truly contractually-required keys/values (public API payload, persisted values, strict UI contract). Otherwise, do not assert the full set.

## Mocking

Import mock function before use: `use function Pest\Laravel\mock;`

## Datasets

Use datasets for repetitive tests (validation rules, etc.):

<code-snippet name="Pest Dataset Example" lang="php">

use App\Data\OwnerForms\OwnerFormData;

describe(OwnerFormData::class, function (): void {
it('has emails', function (string $email): void {
        expect($email)->not->toBeEmpty();
})->with([
'james' => 'james@laravel.com',
'taylor' => 'taylor@laravel.com',
]);
});

</code-snippet>

## Pest 4 Features

| Feature              | Purpose                                 |
| -------------------- | --------------------------------------- |
| Browser Testing      | Full integration tests in real browsers |
| Smoke Testing        | Validate multiple pages quickly         |
| Visual Regression    | Compare screenshots for visual changes  |
| Test Sharding        | Parallel CI runs                        |
| Architecture Testing | Enforce code conventions                |

### Browser Test Example

Browser tests run in real browsers for full integration testing:

- Browser tests live in `tests/Browser/`.
- Use Laravel features like `Event::fake()`, `assertAuthenticated()`, and model factories.
- Use `RefreshDatabase` for clean state per test.
- Interact with page: click, type, scroll, select, submit, drag-and-drop, touch gestures.
- Test on multiple browsers (Chrome, Firefox, Safari) if requested.
- Test on different devices/viewports (iPhone 14 Pro, tablets) if requested.
- Switch color schemes (light/dark mode) when appropriate.
- Take screenshots or pause tests for debugging.

<code-snippet name="Pest Browser Test Example" lang="php">

it('may reset the password', function () {
Notification::fake();

    actingAs(User::factory()->create());

    $page = visit('/sign-in');

    $page->assertSee('Sign In')
        ->assertNoJavascriptErrors()
        ->click('Forgot Password?')
        ->fill('email', 'nuno@laravel.com')
        ->click('Send Reset Link')
        ->assertSee('We have emailed your password reset link!');

    Notification::assertSent(ResetPassword::class);

});

</code-snippet>

### Smoke Testing

Quickly validate multiple pages have no JavaScript errors:

<code-snippet name="Pest Smoke Testing Example" lang="php">

$pages = visit(['/', '/about', '/contact']);

$pages->assertNoJavascriptErrors()->assertNoConsoleLogs();

</code-snippet>

### Visual Regression Testing

Capture and compare screenshots to detect visual changes.

### Test Sharding

Split tests across parallel processes for faster CI runs.

### Architecture Testing

Pest 4 includes architecture testing (from Pest 3):

<code-snippet name="Architecture Test Example" lang="php">

arch('controllers')
->expect('App\Http\Controllers')
->toExtendNothing()
->toHaveSuffix('Controller');

</code-snippet>

## Common Pitfalls

- Not importing `use function Pest\Laravel\mock;` before using mock
- Using `assertStatus(200)` instead of `assertSuccessful()`
- Forgetting datasets for repetitive validation tests
- Deleting tests without approval
- Forgetting `assertNoJavascriptErrors()` in browser tests

## 🧪 Test Types (Unit vs Feature)

### ✅ Unit Tests

**Purpose:** Test isolated business/domain logic without crossing framework boundaries.

👉 Typically test:

- Actions / Services / Domain logic
- Value Objects / DTOs / Enums
- Helpers / Utilities
- API clients using fakes/mocks (HTTP, queues, storage, etc.)

👉 Characteristics:

- Do **NOT** go through HTTP routes, Artisan commands, Filament pages, or browser flows.
- May use database (`RefreshDatabase`) when testing domain behavior.
- Prefer fakes/mocks for external integrations.

**Examples:**

```
tests/Unit/Actions/*
tests/Unit/Services/*
tests/Unit/Domain/*
tests/Unit/Auth/*
```

**Rule of thumb:**

> If testing business logic directly without entering the app through HTTP/CLI/UI, it is a Unit test.

---

### ✅ Feature Tests

**Purpose:** Test application integration through framework boundaries.

👉 Typically test:

- HTTP controllers / APIs
- Artisan commands
- Filament resources/pages/forms
- Authentication flows
- Mail/notifications rendering
- Full request lifecycle behavior

👉 Characteristics:

- Enters the system via:
    - HTTP (`get()`, `postJson()`, etc.)
    - Console (`artisan()`)
    - UI/browser flows

- Validates wiring, middleware, policies, container config, routing, etc.

**Examples:**

```
tests/Feature/Api/*
tests/Feature/Console/*
tests/Feature/Filament/*
tests/Feature/Auth/*
```

**Rule of thumb:**

> If the test crosses an application boundary (HTTP, CLI, UI, framework integration), it is a Feature test.
