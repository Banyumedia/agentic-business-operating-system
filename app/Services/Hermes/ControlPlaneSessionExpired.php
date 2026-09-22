<?php

namespace App\Services\Hermes;

/**
 * Sesi onboarding/QR sudah kedaluwarsa (HTTP 410).
 *
 * Keadaan wajar, bukan kerusakan: QR WhatsApp memang berumur pendek. Layar yang
 * memperlakukannya sebagai galat akan menyuruh operator memperbaiki sesuatu yang
 * tidak rusak, padahal jalan keluarnya "mulai ulang pairing".
 */
class ControlPlaneSessionExpired extends ControlPlaneException {}
