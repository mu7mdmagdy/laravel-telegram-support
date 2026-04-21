# Laravel Telegram Support

A Laravel package providing three ready-to-use Blade components for Telegram-based customer support:

| Component | Description |
|---|---|
| `<x-telegram-support />` | Admin inbox SPA — list chats from Telegram & web widget |
| `<x-telegram-widget />`  | Floating customer chat widget for any page |
| `<x-telegram-connect />` | Telegram account-linking button for forms |

---

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13
- A Telegram Bot Token ([create one](https://t.me/BotFather))

---

## Installation

### 1. Add the package

**From Packagist (when published):**

```bash
composer require mu7mdmagdy/laravel-telegram-support
```

**From a local path (development):**

Add to your `composer.json`:

```json
"repositories": [
    {
        "type": "path",
        "url": "./packages/mu7mdmagdy/laravel-telegram-support"
    }
],
"minimum-stability": "dev",
"prefer-stable": true
```

Then:

```bash
composer require mu7mdmagdy/laravel-telegram-support:@dev
```

### 2. Publish assets (required)

```bash
php artisan vendor:publish --tag=telegram-assets
```

This copies pre-built JS/CSS to `public/vendor/telegram-support/`.

### 3. Publish the config

```bash
php artisan vendor:publish --tag=telegram-config
```

### 4. Run migrations

```bash
php artisan migrate
```

The package creates these tables:
- `telegram_chats` — conversation sessions (Telegram + web widget)
- `telegram_messages` — all messages (in/out)
- `telegram_settings` — key-value settings store
- `telegram_connect_tokens` — one-time linking tokens

---

## Configuration

Edit `config/telegram.php` after publishing:

```php
return [
    // Your Telegram bot token from @BotFather
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),

    // Optional: override the auto-discovered bot @username
    'bot_username' => env('TELEGRAM_BOT_USERNAME', ''),

    // Default chat_id to send messages to (optional)
    'default_chat_id' => env('TELEGRAM_DEFAULT_CHAT_ID'),

    // Parse mode for outgoing messages: 'HTML', 'Markdown', or null
    'parse_mode' => env('TELEGRAM_PARSE_MODE', 'HTML'),

    // Telegram API base URL
    'api_url' => env('TELEGRAM_API_URL', 'https://api.telegram.org'),

    // HTTP timeout in seconds
    'timeout' => env('TELEGRAM_TIMEOUT', 10),

    // Hidden input name injected by <x-telegram-connect> after successful connection
    'chat_id_column' => 'telegram_chat_id',

    // Column on your user model used for display name in the widget
    'name_column' => 'name',
];
```

**.env example:**

```env
TELEGRAM_BOT_TOKEN=7796489946:AAH8zgmjTDMh7NBdLJ0VePgwVTtaV7HwMrQ
TELEGRAM_BOT_USERNAME=YourSupportBot
TELEGRAM_DEFAULT_CHAT_ID=123456789
```

---

## Components

### `<x-telegram-support>`

Admin support inbox. Shows all chats (from Telegram and the web widget) with real-time polling.

```blade
{{-- Full page --}}
<x-telegram-support />

{{-- Embedded panel (600 px tall) --}}
<x-telegram-support height="600px" />
```

**Props:**

| Prop | Default | Description |
|---|---|---|
| `height` | `100vh` | CSS height of the inbox container |
| `min-height` | `500px` | CSS min-height |

**Typical usage — create a protected admin route:**

```php
// routes/web.php
Route::get('/admin/support', fn() => view('admin.support'))->middleware('auth');
```

```blade
{{-- resources/views/admin/support.blade.php --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>Support Inbox</title>
</head>
<body>
    <x-telegram-support />
</body>
</html>
```

---

### `<x-telegram-widget>`

Floating chat bubble at the bottom-right. Visitors can start a support conversation without logging in.

```blade
<x-telegram-widget />
<x-telegram-widget title="Need help?" color="#1e88e5" />
```

**Props:**

| Prop | Default | Description |
|---|---|---|
| `title` | *(translated)* | Widget header title |
| `color` | `#2196F3` | Accent / button colour |

Add it to any Blade layout:

```blade
{{-- resources/views/layouts/app.blade.php --}}
...
<body>
    @yield('content')
    <x-telegram-widget />
</body>
```

---

### `<x-telegram-connect>`

Embeds a "Connect Telegram" button inside any HTML `<form>`. When the user successfully links their Telegram, the component injects a hidden input into the form — you receive the `telegram_chat_id` in your controller.

```blade
<form method="POST" action="/register">
    @csrf
    <input type="text" name="name" />
    <input type="email" name="email" />
    <input type="password" name="password" />

    {{-- Required: block form until connected --}}
    <x-telegram-connect :required="true" />

    <button type="submit">Register</button>
</form>
```

**Props:**

| Prop | Default | Description |
|---|---|---|
| `label` | *(translated)* | Button label text |
| `required` | `false` | Block form submission until connected |

**In your controller:**

```php
public function register(Request $request)
{
    $request->validate([
        'telegram_chat_id' => 'required|string',
        // ...
    ]);

    $user = User::create([
        'name'             => $request->name,
        'email'            => $request->email,
        'password'         => bcrypt($request->password),
        'telegram_chat_id' => $request->telegram_chat_id,
    ]);
}
```

The hidden input's `name` attribute comes from `config('telegram.chat_id_column')` (default: `telegram_chat_id`). Change it in the config to match your column name.

---

## HasTelegramNotifications Trait

Add to any Eloquent model to send Telegram messages to that user directly:

```php
use MoMagdy\TelegramSupport\Traits\HasTelegramNotifications;

class User extends Authenticatable
{
    use HasTelegramNotifications;
}
```

```php
$user->sendTelegramMessage('Your order has shipped! 🚀');

if ($user->hasTelegramLinked()) {
    $user->sendTelegramMessage('Hello from support!');
}
```

The trait reads the chat ID from the column specified in `config('telegram.chat_id_column')`.

---

## API Routes

The package registers these routes automatically (no manual `Route::` calls needed):

| Method | URI | Description |
|---|---|---|
| POST | `/api/telegram/send` | Send a message |
| GET  | `/api/telegram/me` | Get bot info |
| GET  | `/api/telegram/updates` | Raw updates |
| POST | `/api/telegram/sync` | Pull & store new messages |
| GET  | `/api/telegram/chats` | List all chats |
| GET  | `/api/telegram/chats/{id}/messages` | Messages in a chat |
| POST | `/api/telegram/chats/{id}/send` | Reply to a chat |
| POST | `/api/telegram/chats/{id}/read` | Mark chat as read |
| POST | `/api/telegram/connect/generate` | Generate connect token |
| GET  | `/api/telegram/connect/status` | Poll connect status |
| POST | `/api/widget/session` | Start a widget session |
| GET  | `/api/widget/{id}/messages` | Get widget messages |
| POST | `/api/widget/{id}/send` | Send a widget message |

---

## Artisan Commands

```bash
# Test the Telegram integration
php artisan telegram:test

# Override the target chat
php artisan telegram:test --chat_id=123456789
```

---

## Multi-language (i18n)

The package ships English and Arabic translations.

**Publish lang files to customise:**

```bash
php artisan vendor:publish --tag=telegram-lang
```

Files will be published to `lang/vendor/telegram-support/{locale}/telegram.php`.

**HTML lang attribute drives the JS language too:**

```blade
<html lang="{{ app()->getLocale() }}">
```

Supported locales: `en`, `ar`. For other locales, publish the lang files and add your translations.

---

## Publishing Reference

| Tag | What gets published |
|---|---|
| `telegram-config` | `config/telegram.php` |
| `telegram-migrations` | `database/migrations/` |
| `telegram-lang` | `lang/vendor/telegram-support/` |
| `telegram-assets` | `public/vendor/telegram-support/` |

---

## License

MIT
