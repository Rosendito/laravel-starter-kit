---
name: filament-laravel-notifications
description: Build and update Laravel Notification classes that must be compatible with Filament database notifications. Use when implementing `toDatabase()` payloads with `Filament\\Notifications\\Notification::make()->...->getDatabaseMessage()`, wiring channels (`mail`, `database`), and testing notification classes used in Filament panels.
---

# Filament Laravel Notifications

## Goal
Implement Laravel notification classes that render correctly in Filament database notifications while keeping mail and queue behavior explicit and typed.

## Required Pattern
Use this structure in every compatible class:

```php
use App\\Models\\User;
use Filament\\Notifications\\Notification as FilamentNotification;

/**
 * @return list<string>
 */
public function via(object $notifiable): array
{
    return ['mail', 'database'];
}

/**
 * @return array<string, mixed>
 */
public function toDatabase(User $notifiable): array
{
    return FilamentNotification::make()
        ->title('Saved successfully')
        ->body('Optional details')
        ->getDatabaseMessage();
}
```

## Conventions
- Alias Filament notification class as `FilamentNotification` to avoid collisions with `Illuminate\\Notifications\\Notification`.
- Keep `toMail()` focused on email content; keep `toDatabase()` focused on Filament UI payload.
- Prefer `ShouldQueue` for async delivery in application notifications.
- Keep method signatures typed (`object` for `via`, model type for `toDatabase` when known).
- Return PHPDoc typed arrays (`array<string, mixed>`).
- If broadcast and database payloads must differ, keep `toArray()` for broadcast/general payload and `toDatabase()` for Filament-specific data.

## Filament API Notes
- `getDatabaseMessage()` returns a Filament-formatted payload (`format: filament`) for the notifications modal.
- Use `title()` and optionally `body()`, `success()`, `warning()`, `danger()`, `icon()`, `actions()` as needed.
- For direct sending outside notification classes, use:
  - `Notification::make()->...->sendToDatabase($user)`
  - Pass `isEventDispatched: true` when immediate modal refresh is needed without polling.

## Panel Setup Checklist
- Ensure `notifications` table exists (`php artisan make:notifications-table`).
- Enable database notifications in panel config (`->databaseNotifications()`).
- Optional live refresh:
  - Polling interval via `->databaseNotificationsPolling('30s')`
  - Websockets + `isEventDispatched: true` for real-time updates.

## Testing Checklist
- Test `via()` channels include `database` when expected.
- Test `toDatabase()` returns Filament payload with at least:
  - `format` = `filament`
  - expected `title` and optional `body`.
- Test `toMail()` separately from `toDatabase()`.
