<?php

namespace App\Services\Hermes;

/**
 * Path di luar daftar-putih, path yang dilarang permanen, atau endpoint
 * profile-scoped yang dipanggil tanpa nama profil.
 *
 * Dilempar **sebelum** ada permintaan HTTP: kalau ia dilempar setelahnya, node
 * sudah menerima panggilan yang tidak seharusnya pernah dibuat.
 */
class ControlPlanePathDenied extends ControlPlaneException {}
