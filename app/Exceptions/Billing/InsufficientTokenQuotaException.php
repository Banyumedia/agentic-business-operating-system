<?php

namespace App\Exceptions\Billing;

use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exception thrown when token quota is exceeded.
 *
 * Used when attempting an AI action but the company's token balance
 * is insufficient or quota has been exhausted.
 *
 * Fail-closed: action is denied entirely. D-61: pengunjung web diarahkan
 * ke halaman paywall (bukan error mentah) — aksi tetap ditolak.
 */
class InsufficientTokenQuotaException extends Exception
{
    public function __construct(
        int $tokensRequired,
        int $tokensAvailable,
        ?\Throwable $previous = null
    ) {
        $message = sprintf(
            'Kuota token AI tidak mencukupi. Diperlukan: %d token. Ketersediaan: %d token.',
            $tokensRequired,
            $tokensAvailable
        );

        parent::__construct($message, 0, $previous);
    }

    /**
     * Render global (D-61): request web diarahkan ke halaman paywall.
     * Permintaan API/JSON tetap menerima error (fail-closed, tidak
     * disembunyikan sebagai sukses).
     */
    public function render(Request $request): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $this->getMessage(),
                'reason' => 'insufficient_token_quota',
            ], 402);
        }

        if (! $request->user()) {
            return redirect()->route('login');
        }

        return redirect()
            ->route('app.paywall')
            ->with('paywall_reason', 'token_quota');
    }
}
