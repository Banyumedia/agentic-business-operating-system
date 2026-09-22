<?php

namespace App\Services\Hermes;

/**
 * Node hanya menjalankan bridge WhatsApp, belum punya alamat dashboard API.
 *
 * Dibedakan dari kegagalan jaringan karena jalan keluarnya berbeda: ini
 * konfigurasi yang belum diisi, bukan node yang sakit.
 */
class NodeHasNoControlPlane extends ControlPlaneException {}
