<?php

namespace Tests\Feature;

use App\Http\Middleware\MaintenanceMode;
// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_redirects_to_admin_login(): void
    {
        $this->withoutMiddleware(MaintenanceMode::class);

        $response = $this->get('/');

        $response->assertRedirect(route('admin.login'));
    }

    public function test_reviews_route_requires_authentication(): void
    {
        $this->withoutMiddleware(MaintenanceMode::class);

        $response = $this->get(route('admin.reviews'));

        $response->assertRedirect(route('login'));
    }
}
