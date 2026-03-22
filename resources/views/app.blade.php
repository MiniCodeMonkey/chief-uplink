<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if(auth()->check() && auth()->user()->theme_preference?->value === 'light') data-theme="light" @elseif(auth()->check() && auth()->user()->theme_preference?->value === 'dark') data-theme="dark" @endif>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Chief Uplink') }}</title>

        <!-- Geist Sans & Geist Mono fonts via CDN -->
        <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/geist@1/dist/fonts/geist-sans/style.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/geist@1/dist/fonts/geist-mono/style.min.css">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="bg-bg text-text font-sans antialiased">
        @inertia
    </body>
</html>
