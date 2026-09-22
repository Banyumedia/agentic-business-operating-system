<?php

namespace App\Services\Hermes;

use RuntimeException;

/**
 * Induk seluruh kegagalan control plane Hermes.
 *
 * Turunannya sengaja dipisah per keadaan (D-72/T-82 butir d): menyatukan semuanya
 * menjadi satu "galat tak dikenal" membuat layar berbohong. QR kedaluwarsa dan
 * lockout pairing adalah keadaan **wajar** yang punya jalan keluar sendiri, bukan
 * kerusakan yang perlu ditangani operator.
 */
class ControlPlaneException extends RuntimeException {}
