<!DOCTYPE html>
@php($public = str_starts_with($page['component'] ?? '', 'public/'))
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => $public || ($appearance ?? 'system') == 'dark', 'public-site' => $public])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script nonce="{{ Vite::cspNonce() }}">
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }

            /* The public site is always dark (M7.1). */
            html.public-site {
                background-color: #0b0b0c;
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            {{-- Only without SSR: crawlers do not run the page's JavaScript, so link previews come from here. --}}
            <title>{{ $share['title'] ?? config('app.name', 'Laravel') }}</title>
            @isset($share)
                @if ($share['description'])
                    <meta name="description" content="{{ $share['description'] }}">
                    <meta property="og:description" content="{{ $share['description'] }}">
                @endif
                <meta property="og:type" content="website">
                <meta property="og:site_name" content="{{ $share['site_name'] }}">
                <meta property="og:title" content="{{ $share['title'] }}">
                <meta property="og:url" content="{{ $share['url'] }}">
                <meta name="twitter:card" content="{{ $share['image'] ? 'summary_large_image' : 'summary' }}">
                <meta name="twitter:title" content="{{ $share['title'] }}">
                @if ($share['image'])
                    <meta property="og:image" content="{{ $share['image'] }}">
                    <meta name="twitter:image" content="{{ $share['image'] }}">
                    @if (str_ends_with($share['image'], '/og.jpg'))
                        <meta property="og:image:width" content="1200">
                        <meta property="og:image:height" content="630">
                    @endif
                @endif
            @endisset
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
