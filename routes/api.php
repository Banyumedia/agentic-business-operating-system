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
    // D-76: bot CS mencatat prospek yang belum punya company. Sengaja di grup yang
    // sama (kunci bot dijaga) tetapi TIDAK lewat resolveCompany - lihat captureLead.
    Route::post('/bot/master/leads', [MasterBotController::class, 'captureLead']);
});

use App\Http\Controllers\Api\TenantBot\AiContextController;
use App\Http\Controllers\Api\TenantBot\CapabilitiesController;
use App\Http\Controllers\Api\TenantBot\DocumentStandardController;
use App\Http\Controllers\Api\TenantBot\KnowledgeController;
use App\Http\Controllers\Api\TenantBot\TenantBotController;
use App\Http\Middleware\AuthenticateTenantBot;
use App\Http\Middleware\EnforceBotToolScoping;

Route::middleware([AuthenticateTenantBot::class, EnforceBotToolScoping::class])->group(function () {
    Route::get('/bot/tenant/context', [AiContextController::class, 'show'])->name('api.bot.tenant.context.show');
    Route::put('/bot/tenant/context/opt-in', [AiContextController::class, 'update'])->name('api.bot.tenant.context.opt-in');
    Route::get('/bot/tenant/capabilities', [CapabilitiesController::class, 'index'])->name('api.bot.tenant.capabilities');
    // Baca-saja: standar dokumen per tenant supaya satu skill bisa melayani
    // banyak tenant (T-65, D-69). Tidak ada pasangan tulisnya di sini secara
    // sengaja — menyetel standar adalah keputusan owner di web.
    Route::get('/bot/tenant/document-standards', [DocumentStandardController::class, 'show'])->name('api.bot.tenant.document-standards');
    Route::put('/bot/tenant/settings', [TenantBotController::class, 'updateSettings'])->name('api.bot.tenant.settings');
    Route::post('/bot/tenant/contacts', [TenantBotController::class, 'createContact'])->name('api.bot.tenant.contacts');
    Route::post('/bot/tenant/deals', [TenantBotController::class, 'createDeal'])->name('api.bot.tenant.deals');
    Route::post('/bot/tenant/destructive-action', [TenantBotController::class, 'destructiveAction'])->name('api.bot.tenant.destructive-action');
    // T-107: basis pengetahuan usaha. Pencarian berbatas snippet (bukan dump),
    // penulisan HANYA menambah - bot tidak bisa menimpa catatan tulisan
    // manusia lewat rute ini (lihat BusinessNoteWriter).
    Route::get('/bot/tenant/knowledge', [KnowledgeController::class, 'search'])->name('api.bot.tenant.knowledge.search');
    Route::post('/bot/tenant/knowledge', [KnowledgeController::class, 'write'])->name('api.bot.tenant.knowledge.write');
});
