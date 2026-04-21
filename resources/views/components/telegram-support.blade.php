@once
<meta name="csrf-token" content="{{ csrf_token() }}">
<link  rel="stylesheet" href="{{ asset('vendor/telegram-support/app.css') }}">
<script type="module" src="{{ asset('vendor/telegram-support/app.js') }}"></script>
@endonce

<div
    id="telegram-support-app"
    style="height: {{ $height }}; min-height: {{ $minHeight }};"
></div>
