<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Sistema Web de Mesa de Partes Virtual del IESTP Pedro P. Díaz">
        <meta name="theme-color" content="#8A0808">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Mesa de Partes">
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="icon" href="/icons/app-icon-192.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/icons/app-icon-192.svg">

        <title>@yield('title', 'Inicio') | {{ config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="public-body auth-shell">
        <a class="skip-link" href="#main-content">Saltar al contenido principal</a>
        <x-navigation.topbar />

        <main id="main-content" class="public-main" tabindex="-1">
            <div class="container">
                <x-flash-messages />
                @yield('content')
            </div>
        </main>

        <footer class="public-footer border-top">
            <div class="container py-4 text-center text-secondary small">
                <strong>Instituto de Educación Superior Tecnológico Público “Pedro P. Díaz”</strong><br>
                Sistema Web de Mesa de Partes Virtual · {{ now()->year }}
            </div>
        </footer>
        <x-pwa-install-prompt />
    </body>
</html>
