<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Panel del Sistema Web de Mesa de Partes Virtual">
        <meta name="theme-color" content="#8A0808">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Mesa de Partes">
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="icon" href="/icons/app-icon-192.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/icons/app-icon-192.svg">

        <title>@yield('title', 'Panel') | {{ config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="authenticated-body">
        <a class="skip-link" href="#main-content">Saltar al contenido principal</a>
        <x-navigation.topbar authenticated />

        <div class="offcanvas offcanvas-start" id="mobile-sidebar" tabindex="-1" aria-labelledby="mobile-sidebar-title">
            <div class="offcanvas-header border-bottom">
                <h2 class="offcanvas-title h5" id="mobile-sidebar-title">Menú principal</h2>
                <button class="btn-close" type="button" data-bs-dismiss="offcanvas" aria-label="Cerrar menú"></button>
            </div>
            <div class="offcanvas-body p-0">
                <x-navigation.sidebar :user="auth()->user()" />
            </div>
        </div>

        <div class="container-fluid">
            <div class="row g-0">
                <aside class="sidebar-column col-lg-3 col-xl-2 d-none d-lg-block" aria-label="Menú lateral">
                    <x-navigation.sidebar :user="auth()->user()" />
                </aside>

                <main id="main-content" class="dashboard-main col-lg-9 col-xl-10" tabindex="-1">
                    <div class="container-fluid px-3 px-md-4 py-4 py-xl-5">
                        <x-flash-messages />
                        @yield('content')
                    </div>
                </main>
            </div>
        </div>
        <x-pwa-install-prompt />
    </body>
</html>
