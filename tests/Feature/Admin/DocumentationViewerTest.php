<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentationViewerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'admin_docs@example.com',
            'is_platform_admin' => true,
        ]);

        $this->regularUser = User::factory()->create([
            'email' => 'user_docs@example.com',
            'is_platform_admin' => false,
        ]);
    }

    public function test_super_admin_can_view_documentation(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.docs'));

        $response->assertOk();
        $response->assertSee('Arsitektur Dua Nomor WhatsApp');
        $response->assertSee('Perintah Bahasa Manusia (NLU)');
    }

    public function test_non_admin_cannot_view_documentation(): void
    {
        $response = $this->actingAs($this->regularUser)->get(route('admin.docs'));

        $response->assertStatus(403);
    }
}
