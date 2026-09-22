<?php

namespace App\Services\Hermes;

/**
 * Hermes sedang membatasi laju atau mengunci pairing (HTTP 429).
 *
 * `PairingStore` punya rate limit dan lockout sendiri
 * (`platforms/pairing/_rate_limits.json`). Mencoba ulang lebih cepat justru
 * memperpanjang lockout, jadi keadaan ini harus bisa dibedakan dari node sakit.
 */
class ControlPlaneRateLimited extends ControlPlaneException {}
