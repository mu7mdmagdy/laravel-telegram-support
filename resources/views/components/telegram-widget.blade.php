@once
<meta name="csrf-token" content="{{ csrf_token() }}">
<link  rel="stylesheet" href="{{ asset('vendor/telegram-support/widget.css') }}">
<script type="module" src="{{ asset('vendor/telegram-support/widget.js') }}"></script>
@endonce

<div
    id="telegram-widget-app"
    data-title="{{ $title }}"
    data-color="{{ $color }}"
></div>
