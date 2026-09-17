<?php

namespace App\Services;

/**
 * Registry tab Pengaturan berbasis data (analog `DynamicMenuRegistry`).
 *
 * Tab tidak pernah menjadi array statis di komponen: visibilitas per role
 * diselesaikan di sini sehingga tab yang di-reserve owner (mis. "Tim & Akses")
 * benar-benar tidak ada di DOM untuk staff (zero-bloat), bukan disabled.
 */
class SettingsTabRegistry
{
    /** @return list<array{id: string, label: string, roles: list<string>}> */
    public function all(): array
    {
        return [
            ['id' => 'profile', 'label' => 'Profil Usaha & Pajak', 'roles' => ['owner', 'staff']],
            ['id' => 'theme', 'label' => 'Tampilan & Tema', 'roles' => ['owner', 'staff']],
            ['id' => 'features', 'label' => 'Fitur Bisnis', 'roles' => ['owner', 'staff']],
            ['id' => 'assistant', 'label' => 'Karyawan AI', 'roles' => ['owner', 'staff']],
            ['id' => 'usage', 'label' => 'Penggunaan & Paket', 'roles' => ['owner', 'staff']],
            ['id' => 'team', 'label' => 'Tim & Akses', 'roles' => ['owner']],
        ];
    }

    /**
     * Tab yang boleh dilihat role tertentu. Role tak dikenal (null/lainnya)
     * tidak mendapat tab apa pun yang mensyaratkan role eksplisit.
     *
     * @return list<array{id: string, label: string, roles: list<string>}>
     */
    public function visibleTo(?string $role): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (array $tab): bool => in_array($role, $tab['roles'], true),
        ));
    }

    /** @return list<string> */
    public function ids(): array
    {
        return array_column($this->all(), 'id');
    }
}
