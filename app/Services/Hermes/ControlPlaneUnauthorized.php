<?php

namespace App\Services\Hermes;

/**
 * Kredensial control plane salah, belum dipasang, atau memang tidak ada pada
 * alamat yang menuntutnya (HTTP 401).
 *
 * Pesannya hanya menyebut **nama referensi** rahasia, tidak pernah nilainya.
 */
class ControlPlaneUnauthorized extends ControlPlaneException {}
