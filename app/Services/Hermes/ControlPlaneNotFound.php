<?php

namespace App\Services\Hermes;

/**
 * Profil atau sesi yang diminta tidak ada di node (HTTP 404).
 *
 * Pada lajur cermin (T-83) ini bukan kerusakan: ia berarti baris kita menunjuk
 * profil yang **hilang di node**, dan itu justru yang harus ditampilkan.
 */
class ControlPlaneNotFound extends ControlPlaneException {}
