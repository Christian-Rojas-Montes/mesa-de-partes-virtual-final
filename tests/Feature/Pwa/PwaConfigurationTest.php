<?php

namespace Tests\Feature\Pwa;

use Tests\TestCase;

class PwaConfigurationTest extends TestCase
{
    public function test_public_layout_exposes_installation_metadata(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSee('/manifest.webmanifest', false)
            ->assertSee('name="theme-color" content="#8A0808"', false)
            ->assertSee('apple-mobile-web-app-capable', false)
            ->assertSee('pwa-install-button', false);
    }

    public function test_manifest_has_identity_colors_and_installation_icons(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('Mesa de Partes Virtual - IESTP Pedro P. Díaz', $manifest['name']);
        $this->assertSame('Mesa PPD', $manifest['short_name']);
        $this->assertSame('/', $manifest['id']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('#8a0808', $manifest['theme_color']);
        $this->assertContains('192x192', array_column($manifest['icons'], 'sizes'));
        $this->assertContains('512x512', array_column($manifest['icons'], 'sizes'));

        foreach (array_column($manifest['icons'], 'src') as $icon) {
            $this->assertFileExists(public_path(ltrim($icon, '/')));
        }
    }

    public function test_service_worker_only_caches_explicit_public_static_resources(): void
    {
        $serviceWorker = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("request.mode === 'navigate'", $serviceWorker);
        $this->assertStringContainsString("caches.match('/offline.html')", $serviceWorker);
        $this->assertStringContainsString("url.pathname.startsWith('/build/assets/')", $serviceWorker);
        $this->assertStringContainsString("const CACHE_VERSION = 'mpv-static-v2'", $serviceWorker);
        $this->assertStringContainsString("'/panel/'", $serviceWorker);
        $this->assertStringContainsString("'/consulta-expedientes'", $serviceWorker);
        $this->assertStringContainsString("cache: 'no-store'", $serviceWorker);
        $this->assertStringContainsString("!response.headers.has('Set-Cookie')", $serviceWorker);
        $this->assertStringContainsString("url.pathname.includes('/documentos/')", $serviceWorker);
        $this->assertStringContainsString("url.pathname.includes('/respuesta/')", $serviceWorker);
        $this->assertStringNotContainsString("request.method === 'POST'", $serviceWorker);
    }

    public function test_offline_page_explains_that_processing_requires_connection(): void
    {
        $offline = file_get_contents(public_path('offline.html'));

        $this->assertStringContainsString('Sin conexión a Internet', $offline);
        $this->assertStringContainsString('No es posible registrar solicitudes', $offline);
        $this->assertStringContainsString('Ningún documento será almacenado', $offline);
    }

    public function test_request_form_requires_online_connection_and_responsive_breakpoints_exist(): void
    {
        $requestForm = file_get_contents(resource_path('views/applicant/procedure-requests/create.blade.php'));
        $javascript = file_get_contents(resource_path('js/app.js'));
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('data-requires-online', $requestForm);
        $this->assertStringContainsString("navigator.serviceWorker.register('/sw.js'", $javascript);
        $this->assertStringContainsString("!navigator.onLine", $javascript);
        $this->assertStringContainsString('@media (max-width: 575.98px)', $css);
        $this->assertStringContainsString('@media (max-width: 991.98px)', $css);
        $this->assertStringContainsString('@media (max-width: 360px)', $css);
        $this->assertStringContainsString('@media (max-width: 390px)', $css);
        $this->assertStringContainsString('@media (max-width: 768px)', $css);
        $this->assertStringContainsString('@media (max-width: 1024px)', $css);
        $this->assertStringContainsString('.table-responsive > .table.table-hover', $css);
    }

    public function test_basic_accessibility_helpers_cover_forms_tables_focus_and_timeline(): void
    {
        $javascript = file_get_contents(resource_path('js/app.js'));
        $css = file_get_contents(resource_path('css/app.css'));
        $dynamicForm = file_get_contents(resource_path('views/applicant/procedure-requests/create.blade.php'));

        $this->assertStringContainsString("label.htmlFor = control.id", $javascript);
        $this->assertStringContainsString("control.setAttribute('aria-invalid', 'true')", $javascript);
        $this->assertStringContainsString("header.setAttribute('scope', 'col')", $javascript);
        $this->assertStringContainsString(':focus-visible', $css);
        $this->assertStringContainsString('.timeline-item:focus-visible', $css);
        $this->assertStringContainsString('visually-hidden"> (obligatorio)', $dynamicForm);
        $this->assertStringContainsString('data-requires-online', $dynamicForm);
    }
}
