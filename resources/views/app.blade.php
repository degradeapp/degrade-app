<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ config('app.name', 'Degradê') }}</title>

    {{-- PWA: "Adicionar à tela inicial" abre em tela cheia, com ícone próprio --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#0A0A0A">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="Degradê">

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
