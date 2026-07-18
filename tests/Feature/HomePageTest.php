<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomePageTest extends TestCase
{
    public function test_home_page_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Sistema Web de Mesa de Partes Virtual')
            ->assertSee('IESTP Pedro P. Díaz');
    }

    public function test_check_route_reports_an_operational_status(): void
    {
        $response = $this->get('/comprobacion');

        $response
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.application', true)
            ->assertJsonMissingPath('environment')
            ->assertJsonMissingPath('database.host');
    }
}
