<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Canymes...') }}</title>

        <!-- Styles / Scripts -->
        @viteReactRefresh
        @vite('client/src/main.tsx')
    </head>
    <body>
        <div id="app"></div>
    </body>
</html>
