<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Token
    |--------------------------------------------------------------------------
    | Obtained from @BotFather on Telegram.
    | Format: 123456789:ABCDefgh-IJKLmnop_QRSTuvwxyz
    */
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Username (without @)
    |--------------------------------------------------------------------------
    | Used to build the deep link shown in the x-telegram-connect component:
    |   https://t.me/{bot_username}
    |
    | Set TELEGRAM_BOT_USERNAME in your .env file.
    */
    'bot_username' => env('TELEGRAM_BOT_USERNAME', ''),

    /*
    |--------------------------------------------------------------------------
    | Default Chat ID
    |--------------------------------------------------------------------------
    | The default recipient. Can be a user ID, group ID, or @channelusername.
    | Individual user IDs require the user to have started a conversation
    | with your bot first.
    */
    'default_chat_id' => env('TELEGRAM_DEFAULT_CHAT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Telegram API Base URL
    |--------------------------------------------------------------------------
    */
    'api_url' => env('TELEGRAM_API_URL', 'https://api.telegram.org'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout (seconds)
    |--------------------------------------------------------------------------
    */
    'timeout' => env('TELEGRAM_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Parse Mode
    |--------------------------------------------------------------------------
    | Options: 'HTML', 'Markdown', 'MarkdownV2', or null for plain text
    */
    'parse_mode' => env('TELEGRAM_PARSE_MODE', 'HTML'),

    /*
    |--------------------------------------------------------------------------
    | Connect: Hidden input name
    |--------------------------------------------------------------------------
    | When the user completes the Telegram connect flow, the x-telegram-connect
    | component injects a hidden input into the parent form with this name.
    | The chat_id is then submitted with the form — handle it however you like
    | in your form controller (save to session, update users table, etc.).
    |
    | Example:  $chatId = $request->input('telegram_chat_id');
    */
    'chat_id_column' => 'telegram_chat_id',

    /*
    |--------------------------------------------------------------------------
    | Support: Auth Guard
    |--------------------------------------------------------------------------
    | The auth guard used to identify the authenticated user in the support inbox.
    | This is important for displaying the correct user name in the x-telegram-widget component
     */
    'auth_guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Support: Column used as the display name
    |--------------------------------------------------------------------------
    | Used by the x-telegram-widget component to show the authenticated user's
    | name instead of the generic "Visitor" label in the admin inbox.
    */
    'name_column' => 'name',

];
