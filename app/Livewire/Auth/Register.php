<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.guest')]
class Register extends Component
{
    /** Maksimum percobaan registrasi gagal per IP sebelum terkunci sementara. */
    private const MAX_ATTEMPTS = 5;

    /** Lama kunci dalam detik. */
    private const DECAY_SECONDS = 300;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function register(Request $request): void
    {
        // Rate limiting berbasis IP + email (menghindari kunci bersama pada
        // NAT/shared IP kantor, dan mencegah spam pada satu alamat email).
        $emailKey = strtolower(trim((string) $this->email));
        $throttleKey = 'register|'.$request->ip().'|'.$emailKey;

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => 'Terlalu banyak percobaan registrasi. Coba lagi dalam '.ceil($seconds / 60).' menit.',
            ]);
        }

        // Validasi ketat sesuai requirement
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'email.unique' => 'Email sudah terdaftar. Silakan gunakan email lain atau masuk dengan akun Anda.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        try {
            // Normalisasi email ke huruf kecil
            $email = strtolower(trim($this->email));

            // Cek keunikan email (case-insensitive) — double-check
            if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'Email sudah terdaftar. Silakan gunakan email lain atau masuk dengan akun Anda.',
                ]);
            }

            // Buat user tanpa company (company dibuat di onboarding, D-26)
            $user = User::create([
                'name' => trim($this->name),
                'email' => $email,
                'password' => Hash::make($this->password),
            ]);

            // Event untuk observers/jobs
            event(new Registered($user));

            // Login otomatis
            Auth::login($user);

            // Session regeneration untuk cegah session fixation (D-27 requirement)
            session()->regenerate();

            // Redirect ke onboarding (user belum punya company)
            $this->redirect(route('onboarding'));
        } catch (ValidationException $e) {
            // Catat percobaan gagal
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);
            throw $e;
        } catch (\Exception $e) {
            // Catat percobaan gagal untuk error lainnya juga
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);
            throw $e;
        }
    }

    public function render()
    {
        return view('livewire.auth.register')
            ->layoutData([
                'title' => 'Daftar',
                'heading' => 'Buat akun baru',
            ]);
    }
}
