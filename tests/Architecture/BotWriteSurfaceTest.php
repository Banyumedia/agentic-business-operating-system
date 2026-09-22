<?php

namespace Tests\Architecture;

use App\Http\Middleware\AuthenticateMasterBot;
use App\Http\Middleware\AuthenticateTenantBot;
use App\Http\Middleware\EnforceBotToolScoping;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * T-63a: mengunci permukaan tulis yang bisa dicapai sebuah bot.
 *
 * D-69 menaruh skill di Hermes tetapi melarangnya menyentuh data selain lewat
 * TenantBot API. Aturan itu hanya berarti bila **permukaan kita** memang satu
 * pintu: kalau ada rute lain yang menerima autentikasi bot, sebuah skill cukup
 * memanggilnya dan seluruh `EnforceBotToolScoping` menjadi saran.
 *
 * Penjaga ini berlaku sekarang, sebelum ada satu pun profil Hermes - justru itu
 * gunanya. Menambahkannya setelah ada bot yang mencoba menembus berarti
 * menambahkannya setelah terlambat.
 */
class BotWriteSurfaceTest extends TestCase
{
    public function test_every_tenant_bot_route_is_behind_both_auth_and_tool_scoping(): void
    {
        $offenders = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/bot/tenant')) {
                continue;
            }

            $middleware = $route->gatherMiddleware();

            if (! in_array(AuthenticateTenantBot::class, $middleware, true)) {
                $offenders[] = $route->uri().' tanpa AuthenticateTenantBot';
            }

            // Autentikasi saja tidak cukup: tanpa tool scoping, bot tipe apa pun
            // bisa memanggil aksi apa pun selama tokennya sah.
            if (! in_array(EnforceBotToolScoping::class, $middleware, true)) {
                $offenders[] = $route->uri().' tanpa EnforceBotToolScoping';
            }
        }

        $this->assertSame([], $offenders, implode("\n", array_merge(
            ['Rute bot tenant berikut tidak lengkap penjaganya:'],
            $offenders,
        )));
    }

    public function test_negative_no_route_outside_the_bot_prefix_accepts_bot_authentication(): void
    {
        // Ini inti penjaganya: autentikasi bot tidak boleh bocor ke rute lain.
        // Kalau bocor, skill punya pintu kedua yang tidak melewati tool scoping.
        $leaks = [];

        foreach (Route::getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();

            $acceptsBot = in_array(AuthenticateTenantBot::class, $middleware, true)
                || in_array(AuthenticateMasterBot::class, $middleware, true);

            if (! $acceptsBot) {
                continue;
            }

            if (! str_starts_with($route->uri(), 'api/bot/')) {
                $leaks[] = $route->methods()[0].' '.$route->uri();
            }
        }

        $this->assertSame([], $leaks, implode("\n", array_merge(
            ['Autentikasi bot bocor ke rute di luar api/bot/:'],
            $leaks,
        )));
    }

    public function test_every_tenant_bot_route_is_mapped_to_a_tool_name(): void
    {
        // `EnforceBotToolScoping` punya peta rute → nama tool, dengan penyimpulan
        // sebagai jaring terakhir. Rute baru yang lupa didaftarkan akan jatuh ke
        // penyimpulan itu dan bisa mendapat izin yang lebih longgar daripada yang
        // dimaksud. Jadi peta itu wajib lengkap, bukan opsional.
        $map = (new \ReflectionClass(EnforceBotToolScoping::class))
            ->getConstant('ROUTE_TOOL_MAP');

        $unmapped = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/bot/tenant')) {
                continue;
            }

            $name = $route->getName();

            if ($name === null || ! array_key_exists($name, $map)) {
                $unmapped[] = $route->uri().' ('.($name ?? 'tanpa nama').')';
            }
        }

        $this->assertSame([], $unmapped, implode("\n", array_merge(
            ['Rute bot tenant tanpa pemetaan nama tool di ROUTE_TOOL_MAP:'],
            $unmapped,
        )));
    }

    public function test_destructive_action_is_never_reachable_by_a_read_only_profile(): void
    {
        // Profil `addon` (CS publik) baca-saja menurut config/hermes.php. Yang
        // diperiksa di sini adalah konfigurasinya sendiri, karena dari situlah
        // middleware mengambil keputusannya.
        $addon = config('hermes.profiles.addon');

        foreach (['create_transaction', 'update_settings', 'destructive_action'] as $writeTool) {
            $this->assertNotContains(
                $writeTool,
                $addon['allowed_tools'] ?? [],
                "Profil addon tidak boleh punya tool tulis: {$writeTool}",
            );
            $this->assertContains(
                $writeTool,
                $addon['disallowed_tools'] ?? [],
                "Tool tulis {$writeTool} harus tercantum eksplisit sebagai disallowed pada profil addon",
            );
        }
    }

    public function test_no_customer_facing_profile_may_hold_os_level_tools(): void
    {
        // D-69: tanpa larangan ini sebuah skill bisa menulis langsung ke basis
        // data dan melewati seluruh otorisasi kita.
        foreach (['primary', 'addon'] as $type) {
            $profile = config("hermes.profiles.{$type}");

            foreach (['terminal', 'shell', 'write_file', 'delete_file', 'git', 'process'] as $osTool) {
                $this->assertNotContains(
                    $osTool,
                    $profile['allowed_tools'] ?? [],
                    "Profil {$type} tidak boleh mengizinkan tool OS: {$osTool}",
                );
                $this->assertContains(
                    $osTool,
                    $profile['disallowed_tools'] ?? [],
                    "Profil {$type} harus melarang tool OS secara eksplisit: {$osTool}",
                );
            }
        }
    }
}
