<?php

namespace Tests\Feature\Auth;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * User baru dapat mendaftar dengan nama, email, password, dan konfirmasi password.
     */
    public function test_user_can_register_successfully(): void
    {
        Livewire::test('auth.register')
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('password', 'SecurePassword123!')
            ->set('password_confirmation', 'SecurePassword123!')
            ->call('register');

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'name' => 'John Doe',
        ]);

        // User harus sudah login
        $this->assertAuthenticatedAs(User::where('email', 'john@example.com')->first());

        // User seharusnya tidak punya company setelah registrasi (company dibuat di onboarding)
        $this->assertEquals(0, auth()->user()->companies()->count());
    }

    /**
     * Registrasi harus redirect ke onboarding (user belum punya company).
     */
    public function test_register_redirects_to_onboarding(): void
    {
        $response = Livewire::test('auth.register')
            ->set('name', 'Jane Doe')
            ->set('email', 'jane@example.com')
            ->set('password', 'SecurePassword123!')
            ->set('password_confirmation', 'SecurePassword123!')
            ->call('register');

        $response->assertRedirect(route('onboarding'));
    }

    /**
     * Email harus unik (tidak boleh duplikat).
     */
    public function test_email_must_be_unique(): void
    {
        $user = User::factory()->create(['email' => 'existing@example.com']);

        $response = Livewire::test('auth.register')
            ->set('name', 'New User')
            ->set('email', 'existing@example.com')
            ->set('password', 'SecurePassword123!')
            ->set('password_confirmation', 'SecurePassword123!')
            ->call('register');

        $response->assertHasErrors('email');
    }

    /**
     * Email case-insensitive (normalisasi ke lowercase).
     */
    public function test_email_normalized_to_lowercase(): void
    {
        Livewire::test('auth.register')
            ->set('name', 'John Doe')
            ->set('email', 'JOHN@EXAMPLE.COM')
            ->set('password', 'SecurePassword123!')
            ->set('password_confirmation', 'SecurePassword123!')
            ->call('register');

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
        ]);
    }

    /**
     * Email case-insensitive untuk pengecekan duplikat.
     */
    public function test_email_uniqueness_case_insensitive(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $response = Livewire::test('auth.register')
            ->set('name', 'Different John')
            ->set('email', 'JOHN@EXAMPLE.COM')
            ->set('password', 'SecurePassword123!')
            ->set('password_confirmation', 'SecurePassword123!')
            ->call('register');

        $response->assertHasErrors('email');
    }

    /**
     * Password harus sesuai dengan confirmasi (jangan berbeda).
     */
    public function test_password_confirmation_mismatch(): void
    {
        $response = Livewire::test('auth.register')
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('password', 'SecurePassword123!')
            ->set('password_confirmation', 'DifferentPassword123!')
            ->call('register');

        $response->assertHasErrors('password');
    }

    /**
     * Password harus memenuhi validasi defaults (min 8).
     */
    public function test_password_too_short(): void
    {
        $response = Livewire::test('auth.register')
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('password', 'Short1!')
            ->set('password_confirmation', 'Short1!')
            ->call('register');

        $response->assertHasErrors('password');
    }

    /**
     * Name wajib ada dan max 255 karakter.
     */
    public function test_name_validation(): void
    {
        // Name wajib
        $response = Livewire::test('auth.register')
            ->set('name', '')
            ->set('email', 'john@example.com')
            ->set('password', 'SecurePassword123!')
            ->set('password_confirmation', 'SecurePassword123!')
            ->call('register');

        $response->assertHasErrors('name');

        // Name max 255
        $longName = str_repeat('a', 256);
        $response = Livewire::test('auth.register')
            ->set('name', $longName)
            ->set('email', 'john2@example.com')
            ->set('password', 'SecurePassword123!')
            ->set('password_confirmation', 'SecurePassword123!')
            ->call('register');

        $response->assertHasErrors('name');
    }

    /**
     * Email wajib ada, valid format, dan max 255 karakter.
     */
    public function test_email_validation(): void
    {
        // Email wajib
        $response = Livewire::test('auth.register')
            ->set('name', 'John Doe')
            ->set('email', '')
            ->set('password', 'SecurePassword123!')
            ->set('password_confirmation', 'SecurePassword123!')
            ->call('register');

        $response->assertHasErrors('email');

        // Email format tidak valid
        $response = Livewire::test('auth.register')
            ->set('name', 'John Doe')
            ->set('email', 'not-an-email')
            ->set('password', 'SecurePassword123!')
            ->set('password_confirmation', 'SecurePassword123!')
            ->call('register');

        $response->assertHasErrors('email');
    }

    /**
     * Password harus ada (tidak boleh kosong).
     */
    public function test_password_required(): void
    {
        $response = Livewire::test('auth.register')
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('password', '')
            ->set('password_confirmation', '')
            ->call('register');

        $response->assertHasErrors('password');
    }

    /**
     * Session harus di-regenerate setelah login (security: cegah session fixation).
     */
    public function test_session_regenerated_after_register(): void
    {
        $oldSessionId = session()->getId();

        Livewire::test('auth.register')
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('password', 'SecurePassword123!')
            ->set('password_confirmation', 'SecurePassword123!')
            ->call('register');

        // Session ID harus berubah (regenerate)
        $this->assertNotEquals($oldSessionId, session()->getId());
    }

    /**
     * User sudah login tidak boleh akses /register (redirect ke dashboard/onboarding).
     */
    public function test_authenticated_user_redirect_from_register(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);
        $user->current_company_id = $company->id;
        $user->save();

        $response = $this->actingAs($user)->get('/register');

        // User dengan company → redirect ke dashboard
        $response->assertRedirect(route('app.dashboard'));
    }

    /**
     * User sudah login tapi belum punya company → redirect ke onboarding (bukan /register).
     */
    public function test_authenticated_user_without_company_redirect_to_onboarding(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/register');

        // User tanpa company → redirect ke onboarding
        $response->assertRedirect(route('onboarding'));
    }

    /**
     * Register view dapat diakses dan menampilkan form.
     */
    public function test_register_view_accessible(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
        $response->assertSee('Buat akun baru');
        $response->assertSee('Nama Lengkap');
        $response->assertSee('Alamat email');
        $response->assertSee('Kata sandi');
        $response->assertSee('Konfirmasi kata sandi');
    }

    /**
     * User baru harus bisa login dengan email+password yang didaftarkan.
     */
    public function test_newly_registered_user_can_login(): void
    {
        Livewire::test('auth.register')
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('password', 'SecurePassword123!')
            ->set('password_confirmation', 'SecurePassword123!')
            ->call('register');

        auth()->logout();

        // Login dengan email+password yang baru didaftarkan
        $response = Livewire::test('auth.login')
            ->set('email', 'john@example.com')
            ->set('password', 'SecurePassword123!')
            ->call('login');

        $response->assertRedirect(route('onboarding'));
    }

    /**
     * Whitespace di name harus di-trim.
     */
    public function test_name_whitespace_trimmed(): void
    {
        Livewire::test('auth.register')
            ->set('name', '  Jane Smith  ')
            ->set('email', 'jane.smith@example.com')
            ->set('password', 'SecurePassword123!')
            ->set('password_confirmation', 'SecurePassword123!')
            ->call('register');

        $this->assertDatabaseHas('users', [
            'name' => 'Jane Smith',
            'email' => 'jane.smith@example.com',
        ]);
    }

    /**
     * User baru tidak memiliki company setelah registrasi.
     */
    public function test_registered_user_has_no_company(): void
    {
        Livewire::test('auth.register')
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('password', 'SecurePassword123!')
            ->set('password_confirmation', 'SecurePassword123!')
            ->call('register');

        $user = User::where('email', 'john@example.com')->first();
        $this->assertEquals(0, $user->companies()->count());
    }
}
