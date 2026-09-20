<?php

namespace App\Exceptions\Billing;

use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exception thrown when WA group quota is exceeded.
 *
 * Used when attempting to add a WhatsApp group but the company
 * has already reached its max allowed groups based on membership plan or free tier.
 *
 * Fail-closed: action is denied entirely. D-61: pengunjung web diarahkan
 * ke halaman paywall (bukan error mentah) — aksi tetap ditolak.
 */
class WaGroupQuotaExceededException extends Exception
{
    public function __construct(
        int $maxAllowedGroups,
        ?\Throwable $previous = null
    ) {
        $message = sprintf(
            'Kuota grup WhatsApp tercapai. Maksimal: %d grup.',
            $maxAllowedGroups
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
                'reason' => 'wa_group_quota_exceeded',
            ], 402);
        }

        if (! $request->user()) {
            return redirect()->route('login');
        }

        return redirect()
            ->route('app.paywall')
            ->with('paywall_reason', 'wa_group_quota');
    }
}
