<?php

namespace Tests\Unit\WhatsApp;

use App\Services\WhatsApp\NluIntentRouter;
use PHPUnit\Framework\TestCase;

class NluIntentRouterTest extends TestCase
{
    private NluIntentRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new NluIntentRouter;
    }

    public function test_classifies_approval_intent(): void
    {
        $res1 = $this->router->route('ACC tiket #42');
        $this->assertSame('approval', $res1->intent);
        $this->assertSame('approve', $res1->parameters['action']);
        $this->assertSame(42, $res1->parameters['ticket_id']);

        $res2 = $this->router->route('tolak diskon tiket 108');
        $this->assertSame('approval', $res2->intent);
        $this->assertSame('reject', $res2->parameters['action']);
        $this->assertSame(108, $res2->parameters['ticket_id']);
    }

    public function test_classifies_reminder_intent(): void
    {
        $res = $this->router->route('Ingatkan besok jam 08:30 cek stok obat');
        $this->assertSame('reminder', $res->intent);
        $this->assertSame('08:30', $res->parameters['remind_at']);
        $this->assertStringContainsString('cek stok obat', $res->parameters['task']);
    }

    public function test_classifies_setup_intent(): void
    {
        $res1 = $this->router->route('Tolong ubah diskon kasir jadi 15%');
        $this->assertSame('setup', $res1->intent);
        $this->assertSame('max_discount_percent', $res1->parameters['key']);
        $this->assertSame(15, $res1->parameters['value']);

        $res2 = $this->router->route('Ganti sebutan kontak jadi Pasien');
        $this->assertSame('setup', $res2->intent);
        $this->assertSame('terminology', $res2->parameters['key']);
        $this->assertSame('kontak', $res2->parameters['term_key']);
        $this->assertSame('Pasien', $res2->parameters['term_value']);
    }

    public function test_classifies_report_intent(): void
    {
        $res1 = $this->router->route('Berapa total omzet hari ini?');
        $this->assertSame('report', $res1->intent);
        $this->assertSame('revenue', $res1->parameters['metric']);

        $res2 = $this->router->route('Cek sisa stok barang');
        $this->assertSame('report', $res2->intent);
        $this->assertSame('inventory', $res2->parameters['metric']);
    }

    public function test_falls_back_for_general_chat(): void
    {
        $res = $this->router->route('Halo selamat pagi, bagaimana cuaca hari ini?');
        $this->assertSame('fallback', $res->intent);
    }
}
