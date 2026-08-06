<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ \App\Domains\Settings\Support\Branding::name() }}</title>

        @if (\App\Domains\Settings\Support\Branding::faviconUrl())
            <link rel="icon" href="{{ \App\Domains\Settings\Support\Branding::faviconUrl() }}" />
        @else
            <link rel="icon" href="{{ asset('favicon.svg') }}" />
        @endif

        <meta property="og:title" content="{{ \App\Domains\Settings\Support\Branding::name() }}" />
        <meta property="og:description" content="{{ \App\Domains\Settings\Support\Branding::subName() }}" />
        <meta property="og:type" content="website" />
        @if (\App\Domains\Settings\Support\Branding::ogImageUrl())
            <meta property="og:image" content="{{ \App\Domains\Settings\Support\Branding::ogImageUrl() }}" />
            <meta name="twitter:card" content="summary_large_image" />
        @endif

        @if (\App\Domains\Settings\Support\Branding::logoUrl())
            <link rel="preload" as="image" href="{{ \App\Domains\Settings\Support\Branding::logoUrl() }}" />
        @endif
        @if (\App\Domains\Settings\Support\Branding::logotypeUrl())
            <link rel="preload" as="image" href="{{ \App\Domains\Settings\Support\Branding::logotypeUrl() }}" />
        @endif

        <!-- Styles / Scripts -->
        @viteReactRefresh
        @vite('client/src/main.tsx')

        <style>
            :root {
                --brand: {{ \App\Domains\Settings\Support\Branding::primaryColor() }};
                --brand-secondary: {{ \App\Domains\Settings\Support\Branding::secondaryColor() }};
            }
        </style>

        <script>
            window.__BRANDING__ = @json(\App\Domains\Settings\Support\Branding::data());
        </script>
    </head>
    <body>
        <div id="app"></div>
    </body>
</html>
