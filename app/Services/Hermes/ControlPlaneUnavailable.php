<?php

namespace App\Services\Hermes;

/**
 * Node tidak terjangkau atau sedang sakit (gagal koneksi, 5xx).
 *
 * Dipisah dari 4xx karena artinya berbeda bagi pemanggil: permintaan yang sama
 * layak dicoba lagi nanti, dan pada lajur pemantauan (T-84) keadaan ini berarti
 * "node tidak terjangkau", bukan "platform mati".
 */
class ControlPlaneUnavailable extends ControlPlaneException {}
