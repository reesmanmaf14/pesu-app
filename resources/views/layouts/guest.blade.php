<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title.' · ' : '' }}Pesu</title>

        {{-- Pesu tokens + UI (Mukta Malar is self-hosted, so no font CDN) and Alpine --}}
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="pesu-body">
        <main class="pesu-auth">
            {{-- Login and register pass :brand="false" to show the card on its own. --}}
            @if ($attributes->get('brand', true) !== false)
                <x-pesu.brand href="/" />
            @endif

            <div class="pesu-card pesu-auth-card">
                {{ $slot }}
            </div>
        </main>
    </body>
</html>
