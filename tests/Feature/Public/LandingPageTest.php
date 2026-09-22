<?php

namespace Tests\Feature\Public;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Halaman publik `/` adalah pintu masuk calon pelanggan, jadi tombol ajakannya
 * tidak boleh mengarah ke nomor contoh.
 *
 * Versi pertama halaman ini menanam `6281234567890` di tiga tombol. Halamannya
 * tetap terlihat normal, jadi tidak ada yang tahu bahwa setiap klik mengarah ke
 * nomor milik orang lain.
 */
class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_button_uses_the_configured_number(): void
    {
        Config::set('app.sales_whatsapp', '0812-3456-0000');

        $response = $this->get('/');

        $response->assertStatus(200);
        // Awalan lokal dinormalkan ke format internasional yang dipakai wa.me.
        $response->assertSee('https://wa.me/6281234560000', escape: false);
    }

    public function test_negative_no_placeholder_number_is_ever_rendered(): void
    {
        Config::set('app.sales_whatsapp', null);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertDontSee('6281234567890');
        $response->assertDontSee('wa.me');
    }

    public function test_call_to_action_falls_back_to_registration_when_the_number_is_absent(): void
    {
        // Hero tanpa satu pun tombol lebih buruk daripada tombol yang berbeda
        // tujuan, jadi ajakannya tetap ada.
        Config::set('app.sales_whatsapp', null);

        $response = $this->get('/');

        $response->assertSee(route('register'), escape: false);
        $response->assertSee('Mulai Sekarang');
    }

    public function test_authenticated_visitor_is_sent_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/')->assertRedirect(route('app.dashboard'));
    }
}
