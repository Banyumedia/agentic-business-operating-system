<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/app/dashboard');
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_dashboard(): void
    {
        // Fake JsonCompanyContext doesn't care about real DB, just active_company in session
        session(['active_company' => 'salon-ayu']);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/app/dashboard?company=salon-ayu');

        // Assert we don't get redirected to /login
        $this->assertNotEquals(302, $response->getStatusCode());
    }

    public function test_post_logout_logs_user_out_and_redirects_to_login(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_get_logout_route_does_not_exist(): void
    {
        $user = User::factory()->create();

        // GET logout tidak boleh dieksekusi - logout lintas situs via link/gambar.
        // 405 = rute POST-only.
        $this->actingAs($user)->get('/logout')->assertStatus(405);
    }

    public function test_logout_requires_authentication(): void
    {
        $this->post('/logout')->assertRedirect(route('login'));
    }
}
