<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * UR-01: provisioning identitas produksi yang idempoten.
 *
 * Membuat TEPAT SATU platform admin dan TEPAT SATU owner pilot.
 * Aman dijalankan berulang: identitas yang sudah ada tidak dibuat dua kali
 * dan tidak diubah. Password TIDAK PERNAH dicetak/di-log.
 */
class BosProvision extends Command
{
    protected $signature = 'bos:provision
        {--admin-email= : Email platform admin (existing user dipakai apa adanya)}
        {--owner-email= : Email owner pilot}
        {--company-name= : Nama company pilot}
        {--company-slug= : Slug company pilot (unik)}
        {--password= : Password untuk KEDUA akun (HANYA lokal/dev; produksi pakai prompt interaktif)}';

    protected $description = 'Provision satu platform admin + satu owner pilot (idempoten, tanpa mencetak secret)';

    public function handle(): int
    {
        $adminEmail = $this->option('admin-email');
        $ownerEmail = $this->option('owner-email');
        $companyName = $this->option('company-name');
        $companySlug = $this->option('company-slug');

        foreach (['admin-email' => $adminEmail, 'owner-email' => $ownerEmail, 'company-name' => $companyName, 'company-slug' => $companySlug] as $label => $value) {
            if (! is_string($value) || $value === '') {
                $this->error("Opsi --{$label} wajib diisi.");

                return self::FAILURE;
            }
        }

        $password = $this->option('password');
        if (! is_string($password) || $password === '') {
            if (! $this->isInteractiveProduction()) {
                $this->error('--password wajib diisi pada environment non-interaktif.');

                return self::FAILURE;
            }
            $password = (string) $this->secret('Password untuk kedua akun (input tersembunyi, tidak dicetak)');
            if ($password === '') {
                $this->error('Password kosong ditolak.');

                return self::FAILURE;
            }
        }

        // Admin
        $admin = User::where('email', $adminEmail)->first();
        if ($admin === null) {
            $admin = User::create([
                'name' => 'Platform Admin',
                'email' => $adminEmail,
                'password' => $password,
            ]);
            $this->info('Platform admin dibuat.');
        } else {
            $this->line('Platform admin sudah ada - tidak diubah.');
        }

        if (! $admin->is_platform_admin) {
            $admin->forceFill(['is_platform_admin' => true])->save();
            $this->info('Flag platform admin diaktifkan.');
        }

        // Owner
        $owner = User::where('email', $ownerEmail)->first();
        if ($owner === null) {
            $owner = User::create([
                'name' => 'Owner Pilot',
                'email' => $ownerEmail,
                'password' => $password,
            ]);
            $this->info('Owner pilot dibuat.');
        } else {
            $this->line('Owner pilot sudah ada - tidak diubah.');
        }

        // Company pilot: slug adalah kunci unik.
        $existing = Company::where('slug', $companySlug)->first();
        if ($existing !== null) {
            if ((int) $existing->owner_user_id !== (int) $owner->id) {
                $this->error("Slug company '{$companySlug}' sudah dipakai owner lain - ditolak.");

                return self::FAILURE;
            }
            $this->line('Company pilot sudah ada - tidak diubah.');
        } else {
            Company::create([
                'name' => $companyName,
                'slug' => $companySlug,
                'business_preset' => 'custom',
                'owner_user_id' => $owner->id,
            ]);
            $this->info('Company pilot dibuat.');
        }

        // Verifikasi non-secret.
        $this->table(['Role', 'Email', 'Id'], [
            ['platform admin', $admin->email, $admin->id],
            ['owner pilot', $owner->email, $owner->id],
        ]);

        return self::SUCCESS;
    }

    private function isInteractiveProduction(): bool
    {
        // Interaktif = stdin TTY. Produksi tanpa TTY wajib --password.
        return $this->input->isInteractive();
    }
}
