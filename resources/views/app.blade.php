<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ config('app.name', 'Degradê') }}</title>

    @if (file_exists(public_path('build/manifest.json')))
        @php
            $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
        @endphp
        @if (isset($manifest['resources/js/app.ts']))
            <link rel="stylesheet" href="{{ asset('build/' . $manifest['resources/js/app.ts']['css'][0]) }}">
            <script type="module" src="{{ asset('build/' . $manifest['resources/js/app.ts']['file']) }}"></script>
        @endif
    @else
        @vite(['resources/js/app.ts'])
    @endif
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>
