<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Read by app.js for the client-side title, so the name comes from
             the server rather than a VITE_ var baked in at build time. --}}
        <meta name="app-name" content="{{ config('coi.name') }}">

        {{-- Favicon: the sidebar logo, scaled to 32x32. Inlined rather than
             served from public/ because the deploy artifact only carries
             public/build, so a file next to index.php never reaches the
             server -- the same reason the sidebar logo is loaded remotely.
             At ~2KB this costs less than the extra request would. --}}
        <link rel="icon" type="image/png" href="data:image/png;base64,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">
        <title inertia>{{ config('coi.name') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
