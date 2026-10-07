<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title.' · ' : '' }}Pesu</title>

        {{-- Pesu tokens + UI (Mukta Malar is self-hosted, so no font CDN) and Alpine --}}
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        {{-- Extra assets for one page (the recordings page adds the board's styles and its recorder). --}}
        @stack('head')
    </head>
    <body class="pesu-body">
        <a class="pesu-skip" href="#main">Skip to content</a>
        @include('aac.partials.icons')
        @include('layouts.navigation')

        <main id="main" class="pesu-page" tabindex="-1">
            @isset($header)
                {{ $header }}
            @endisset

            {{ $slot }}
        </main>
    </body>
</html>
