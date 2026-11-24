<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_root_renders_the_landing_page_with_an_empty_catalog(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSeeText('A-Code Solutions');
        $response->assertSee('id="planes"', false);
        $response->assertSeeText('Por el momento no hay planes de pago disponibles.');
        $response->assertSeeText('Prueba gratuita sin tarjeta');
    }
}
