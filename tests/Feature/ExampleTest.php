<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_root_redirects_to_login(): void
    {
        // routes/web.php: Route::redirect('/', '/login') — no hay página
        // pública en "/", todo entra por el login (MPW-V2 layout guest).
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }
}
