<?php

use App\Http\Controllers\Api\MasterBot\MasterBotController;
use App\Http\Controllers\Api\NalarPesanWebhookController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Middleware\AuthenticateMasterBot;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/payment/midtrans', [PaymentWebhookController::class, 'handle']);
Route::post('/webhooks/nalar-pesan', [NalarPesanWebhookController::class, 'handle']);

Route::middleware([AuthenticateMasterBot::class])->group(function () {
    Route::post('/bot/master/tickets', [MasterBotController::class, 'createTicket']);
    Route::get('/bot/master/balance', [MasterBotController::class, 'checkBalance']);
    Route::post('/bot/master/topup', [MasterBotController::class, 'createTopupInvoice']);
});

use App\Http\Controllers\Api\TenantBot\CapabilitiesController;
use App\Http\Controllers\Api\TenantBot\TenantBotController;
use App\Http\Middleware\AuthenticateTenantBot;

Route::middleware([AuthenticateTenantBot::class])->group(function () {
    Route::get('/bot/tenant/capabilities', [CapabilitiesController::class, 'index']);
    Route::put('/bot/tenant/settings', [TenantBotController::class, 'updateSettings']);
    Route::post('/bot/tenant/contacts', [TenantBotController::class, 'createContact']);
    Route::post('/bot/tenant/deals', [TenantBotController::class, 'createDeal']);
    Route::post('/bot/tenant/destructive-action', [TenantBotController::class, 'destructiveAction']);
});
