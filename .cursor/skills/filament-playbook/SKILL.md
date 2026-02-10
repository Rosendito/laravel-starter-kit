---
name: filament-playbook
description: >-
    Progressive notes for working with Filament v5.
---

## Enums

Filament is **exceptionally well-prepared for enums** across the UI layer. Prefer enums for any value that is a finite set (status, type, level, visibility, workflow steps, etc.).

- **Baseline requirement**: implement `HasLabel` so Filament can generate human-friendly option labels and display values nicely.
- **Optional enhancements**:
    - `HasColor` for badges / colored UI.
    - `HasIcon` for icons next to labels.

### Reference

- [Enum tricks](https://filamentphp.com/docs/5.x/advanced/enums)
- [Using the enum color with a text column in your table](https://filamentphp.com/docs/5.x/advanced/enums#using-the-enum-color-with-a-text-column-in-your-table)

### Example enum (label + optional color + optional icon)

Implement `HasLabel` as the default. Add `HasColor` and `HasIcon` when the UI benefits from it.

```php
<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

enum Status: string implements HasLabel, HasColor, HasIcon
{
    case DRAFT = 'draft';
    case REVIEWING = 'reviewing';
    case PUBLISHED = 'published';
    case REJECTED = 'rejected';

    public function getLabel(): string | Htmlable | null
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::REVIEWING => 'Reviewing',
            self::PUBLISHED => 'Published',
            self::REJECTED => 'Rejected',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::REVIEWING => 'warning',
            self::PUBLISHED => 'success',
            self::REJECTED => 'danger',
        };
    }

    public function getIcon(): string | BackedEnum | Htmlable | null
    {
        return match ($this) {
            self::DRAFT => Heroicon::Pencil,
            self::REVIEWING => Heroicon::Eye,
            self::PUBLISHED => Heroicon::Check,
            self::REJECTED => Heroicon::XMark,
        };
    }
}
```

### Eloquent casting (important)

When an attribute is cast to an enum, Filament can automatically leverage `HasLabel` (and `HasColor` / `HasIcon` where applicable).

```php
// In your Eloquent model
protected function casts(): array
{
    return [
        'status' => \App\Enums\Status::class,
    ];
}
```

### Form select with enums

```php
use Filament\Forms\Components\Select;

Select::make('status')
    ->options(\App\Enums\Status::class)
    ->required();
```

For selectors, prefer `->options(...)` and pass the enum class directly. Avoid adding custom `options()` methods to enums just to map labels. If you need cases explicitly in PHP, use `Status::cases()`.

### Table column example (TextColumn + badge)

If the model attribute is cast to the enum and the enum implements `HasColor`, Filament can color the badge automatically. This works best with `badge()`:

```php
use Filament\Tables\Columns\TextColumn;

TextColumn::make('status')
    ->badge();
```

Reference: Filament docs: [Enum tricks](https://filamentphp.com/docs/5.x/advanced/enums)

## Resource Playbook (Reusable Columns, Fields, Actions)

Use these as project-independent rules. Avoid referencing specific files as canonical sources because resources and paths may change over time.

### Mandatory Discovery (ask before generating a resource)

Before deciding the architecture, always ask:

1. Is this resource simple CRUD or role/context-driven?
2. Will fields differ by role/state/page mode (create, edit, view)?
3. Do you want centralized reusable fields/columns, or local inline schema for speed?
4. Is this resource expected to grow significantly (large team, many sections, high iteration)?

Do not default to split/composable mode without this discovery.

### When splitting is justified

Split columns/fields/sections only when there is a concrete reason:

1. Different controls per role/context (like `User`).
2. Large resource needing stronger scalability boundaries and clearer ownership.

If neither applies, keep the resource simpler and local.

### Consistent structure for form and infolist

For shared domain resources, keep the same layout order in both form and infolist:

- Form schema and infolist schema should use matching section sequence.
- Reuse the same domain sections with typed flags for optional controls when needed.
- Keep `Resource` class thin and delegate to schema builders (`...Form::configure()`, `...Infolist::configure()`, `...Table::configure()`).

This preserves UX consistency and reduces drift between create/edit/view representations.

Minimal pattern:

```php
final class CustomerResource extends Resource
{
    public static function form(Schema $schema): Schema
    {
        return CustomerForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CustomerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomersTable::configure($table);
    }
}
```

```php
final class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                CustomerDetailsSection::make(includeSensitiveFields: true),
                AddressSection::make(),
            ]);
    }
}
```

```php
final class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                CustomerDetailsSection::make(includeSensitiveEntries: true),
                AddressSection::make(),
            ]);
    }
}
```

### Reusable schema pieces

Preferred reusable directories:
- Table columns and filters: `Schemas/Columns`, `Schemas/Filters`
- Form fields and sections: `Schemas/Fields/Components`, `Schemas/Fields/Sections`
- Infolist entries and sections: `Schemas/Infolists/Entries`, `Schemas/Infolists/Sections`

Keep builders `final`, return explicit Filament component types, and expose `make()` / `configure()` factories.

### Actions policy (non-negotiable)

Actions must always be class-based.

- Never keep domain actions as inline closures inside resource/page files when they can be a reusable action class.
- Use dedicated action classes with `public static function make(...): Action`.
- Keep action behavior self-contained and typed.

Minimal pattern:

```php
final class GetInTouchAction
{
    public static function make(User $user): Action
    {
        $url = self::resolveUrl($user);

        return Action::make('getInTouch')
            ->label('Get in Touch')
            ->button()
            ->color('success')
            ->url($url, shouldOpenInNewTab: true)
            ->disabled($url === null);
    }

    private static function resolveUrl(User $user): ?string
    {
        return null;
    }
}
```

### Required PHPDoc note for composable resources

If a resource is intentionally split/composable, add a short PHPDoc note near the resource schema methods to document intent.

Suggested note:

```php
/**
 * Composable schema by design:
 * this resource varies controls by role/context and is expected to scale.
 */
```
