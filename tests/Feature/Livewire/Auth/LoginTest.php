<?php

namespace Tests\Feature\Livewire\Auth;

use App\Livewire\Auth\Login;
use App\Models\Company;
use App\Models\User;
use App\Providers\DataSourceServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Login menulis konteks company lewat driver yang dipakai app (Eloquent).
        config(['datasource.driver' => 'eloquent']);
        (new DataSourceServiceProvider($this->app))->register();
    }

    public function test_renders_successfully()
    {
        Livewire::test(Login::class)
            ->assertStatus(200);
    }

    public function test_labels_are_indonesian_and_uses_guest_layout()
    {
        $componentHtml = Livewire::test(Login::class)->html();

        foreach (['Alamat email', 'Kata sandi', 'Ingat saya', '>Masuk<'] as $label) {
            $this->assertStringContainsString($label, $componentHtml, "Label Indonesia hilang: {$label}");
        }

        // Heading layout guest hanya dirender lewat request HTTP penuh.
        $pageHtml = $this->get(route('login'))->assertOk()->getContent();
        $this->assertStringContainsString('Masuk ke akun', $pageHtml);

        foreach ([$componentHtml, $pageHtml] as $html) {
            $this->assertStringNotContainsString('Sign in', $html);
            $this->assertStringNotContainsString('Remember me', $html);
            $this->assertStringNotContainsString('Email address', $html);
            $this->assertStringNotContainsString('Password</', $html);
        }
    }

    public function test_invalid_submit_marks_fields_with_aria_and_error_ids(): void
    {
        $component = Livewire::test(Login::class)
            ->set('email', 'bukan-email')
            ->set('password', '')
            ->call('login');

        $html = $component->html();
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('aria-describedby="email-error"', $html);
        $this->assertStringContainsString('id="email-error"', $html);
        $this->assertStringContainsString('id="password-error"', $html);

        // Fokus pindah ke field pertama yang salah (email), bukan hanya warna.
        $this->assertStringContainsString(
            "x-init=\"\$el.getAttribute('aria-invalid') === 'true' && document.querySelector('[aria-invalid=true]') === \$el && \$el.focus()\"",
            $html,
        );
    }

    public function test_user_can_login()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        // User dengan company diarahkan ke dashboard (company aktif ter-set).
        Company::factory()->create(['owner_user_id' => $user->id]);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password123')
            ->call('login')
            ->assertRedirect(route('app.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_without_company_is_redirected_to_onboarding()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password123')
            ->call('login')
            ->assertRedirect(route('onboarding'));

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

    public function test_login_is_rate_limited_after_repeated_failures()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        foreach (range(1, 5) as $i) {
            Livewire::test(Login::class)
                ->set('email', $user->email)
                ->set('password', 'wrongpassword')
                ->call('login')
                ->assertHasErrors(['email']);
        }

        // Percobaan ke-6 dengan kredensial BENAR tetap ditolak karena kunci.
        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password123')
            ->call('login')
            ->assertHasErrors(['email'])
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_successful_login_clears_the_failure_count_for_that_key()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        // User dengan company diarahkan ke dashboard setelah login sukses.
        Company::factory()->create(['owner_user_id' => $user->id]);

        // Gagal beberapa kali di bawah ambang, lalu sukses: kunci dibuka.
        foreach (range(1, 3) as $i) {
            Livewire::test(Login::class)
                ->set('email', $user->email)
                ->set('password', 'wrongpassword')
                ->call('login')
                ->assertHasErrors(['email']);
        }

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password123')
            ->call('login')
            ->assertRedirect(route('app.dashboard'));

        $this->assertAuthenticatedAs($user);
    }
}
