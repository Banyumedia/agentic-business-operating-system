<?php

namespace App\Livewire\Auth;

use App\Contracts\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.guest')]
class Login extends Component
{
    /** Maksimum percobaan gagal per email+IP sebelum terkunci sementara. */
    private const MAX_ATTEMPTS = 5;

    /** Lama kunci dalam detik. */
    private const DECAY_SECONDS = 300;

    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function login(Request $request): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => 'Terlalu banyak percobaan masuk. Coba lagi dalam '.ceil($seconds / 60).' menit.',
            ]);
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        // Sukses: hitungan gagal dibuka ulang supaya kunci tidak tersisa.
        RateLimiter::clear($throttleKey);
        session()->regenerate();

        // Set konteks company aktif agar middleware /app tidak 403.
        // UR-03: current_company_id residual (sisa impersonasi yang tidak
        // di-stop bersih) tidak dipercaya mentah - diverifikasi kepemilikan;
        // tidak valid -> self-heal ke company milik user.
        $user = Auth::user();
        $companyId = $user->current_company_id;
        if ($companyId !== null && $user->companies()->whereKey($companyId)->doesntExist()) {
            $companyId = null;
            $user->forceFill(['current_company_id' => null])->save();
        }
        if (! $companyId) {
            $company = $user->companies()->first();
            if ($company) {
                $companyId = $company->id;
                $user->current_company_id = $companyId;
                $user->save();
            }
        }
        if ($companyId) {
            app(CompanyContext::class)->setCurrent((string) $companyId);
        }

        // User tanpa company (baru daftar, belum onboarding) diarahkan ke
        // onboarding, bukan dashboard yang bisa 403.
        if (! $companyId) {
            $this->redirect(route('onboarding'));

            return;
        }

        $this->redirectIntended(route('app.dashboard'));
    }

    private function throttleKey(Request $request): string
    {
        return mb_strtolower(trim($this->email)).'|'.$request->ip();
    }

    public function render()
    {
        return view('livewire.auth.login')
            ->layoutData([
                'title' => 'Login',
                'heading' => 'Masuk ke akun Anda',
            ]);
    }
}
