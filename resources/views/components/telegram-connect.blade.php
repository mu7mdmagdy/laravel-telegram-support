@once
<meta name="csrf-token" content="{{ csrf_token() }}">
<link  rel="stylesheet" href="{{ asset('vendor/telegram-support/connect.css') }}">
<script type="module" src="{{ asset('vendor/telegram-support/connect.js') }}"></script>
@endonce

<div
    data-telegram-connect
    data-label="{{ $label }}"
    data-color="{{ $color }}"
    data-required="{{ $required ? '1' : '0' }}"
    data-input-name="{{ config('telegram.chat_id_column', 'telegram_chat_id') }}"
    data-bot-username="{{ config('telegram.bot_username', '') }}"
></div>
