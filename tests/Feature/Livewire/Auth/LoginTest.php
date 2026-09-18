<?php

namespace Tests\Feature\Livewire\Auth;

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_successfully()
    {
        Livewire::test(Login::class)
            ->assertStatus(200);
    }

    public function test_user_can_login()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password123')
            ->call('login')
            ->assertRedirect(route('app.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_credentials()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'wrongpassword')
            ->call('login')
            ->assertHasErrors(['email'])
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_email_is_required()
    {
        Livewire::test(Login::class)
            ->set('password', 'password123')
            ->call('login')
            ->assertHasErrors(['email' => 'required']);
    }

    public function test_password_is_required()
    {
        Livewire::test(Login::class)
            ->set('email', 'test@example.com')
            ->call('login')
            ->assertHasErrors(['password' => 'required']);
    }
}
