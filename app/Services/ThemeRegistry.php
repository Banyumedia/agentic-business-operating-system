<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ThemeRegistry
{
    /**
     * @return array<string, array{name: string, description: string, tokens: array<string, string>}>
     */
    public static function themes(): array
    {
        $commonDark = [
            '--erp-bg-hover' => '#334155',
            '--erp-bg-active' => '#475569',
            '--erp-border' => '#334155',
            '--erp-border-strong' => '#475569',
            '--erp-border-focus' => '#34d399',
            '--erp-success-soft' => '#022c22',
            '--erp-warning-soft' => '#451a03',
            '--erp-danger-soft' => '#4c0519',
            '--erp-info-soft' => '#082f49',
            '--erp-sidebar-bg' => '#020617',
            '--erp-sidebar-text' => '#cbd5e1',
            '--erp-sidebar-active' => '#1e293b',
            '--erp-topbar-bg' => '#0f172a',
            '--erp-card-shadow' => '0 1px 2px 0 rgb(0 0 0 / 0.4)',
            '--erp-radius-sm' => '0.375rem',
            '--erp-radius-md' => '0.75rem',
            '--erp-radius-lg' => '1rem',
            '--erp-font-sans' => "'Instrument Sans', ui-sans-serif, system-ui, sans-serif",
            '--erp-font-mono' => "ui-monospace, 'JetBrains Mono', 'Cascadia Code', monospace",
        ];

        return [
            'a' => self::definition('A — Slate + Emerald', 'Tenang dan netral untuk semua usaha.', [
                '--erp-bg-base' => '#0f172a', '--erp-bg-secondary' => '#1e293b', '--erp-bg-elevated' => '#334155', '--erp-bg-inset' => '#020617',
                '--erp-text-primary' => '#f8fafc', '--erp-text-secondary' => '#cbd5e1', '--erp-text-muted' => '#94a3b8', '--erp-text-inverse' => '#052e16', '--erp-text-link' => '#7dd3fc',
                '--erp-accent' => '#34d399', '--erp-accent-hover' => '#6ee7b7', '--erp-accent-soft' => '#022c22', '--erp-focus' => '#34d399',
                '--erp-success' => '#6ee7b7', '--erp-warning' => '#fbbf24', '--erp-danger' => '#fb7185', '--erp-info' => '#38bdf8',
            ] + $commonDark),
            'b' => self::definition('B — Zinc + Amber', 'Hangat dan tegas untuk operasi harian.', [
                '--erp-bg-base' => '#18181b', '--erp-bg-secondary' => '#27272a', '--erp-bg-elevated' => '#3f3f46', '--erp-bg-inset' => '#09090b',
                '--erp-bg-hover' => '#3f3f46', '--erp-bg-active' => '#52525b', '--erp-text-primary' => '#fafafa', '--erp-text-secondary' => '#d4d4d8', '--erp-text-muted' => '#a1a1aa', '--erp-text-inverse' => '#1c1917', '--erp-text-link' => '#fcd34d',
                '--erp-border' => '#3f3f46', '--erp-border-strong' => '#52525b', '--erp-border-focus' => '#fbbf24', '--erp-accent' => '#fbbf24', '--erp-accent-hover' => '#fcd34d', '--erp-accent-soft' => '#451a03', '--erp-focus' => '#fbbf24',
                '--erp-success' => '#34d399', '--erp-success-soft' => '#022c22', '--erp-warning' => '#fde68a', '--erp-warning-soft' => '#422006', '--erp-danger' => '#fb7185', '--erp-danger-soft' => '#4c0519', '--erp-info' => '#38bdf8', '--erp-info-soft' => '#082f49',
                '--erp-sidebar-bg' => '#09090b', '--erp-sidebar-text' => '#d4d4d8', '--erp-sidebar-active' => '#27272a', '--erp-topbar-bg' => '#18181b',
            ] + self::shapeTokens()),
            'c' => self::definition('C — Navy + Sky', 'Korporat dan dingin untuk layanan profesional.', [
                '--erp-bg-base' => '#0b1220', '--erp-bg-secondary' => '#111a2e', '--erp-bg-elevated' => '#1b2740', '--erp-bg-inset' => '#060b16',
                '--erp-bg-hover' => '#1b2740', '--erp-bg-active' => '#334155', '--erp-text-primary' => '#f1f5f9', '--erp-text-secondary' => '#cbd5e1', '--erp-text-muted' => '#94a3b8', '--erp-text-inverse' => '#082f49', '--erp-text-link' => '#7dd3fc',
                '--erp-border' => '#334155', '--erp-border-strong' => '#475569', '--erp-border-focus' => '#38bdf8', '--erp-accent' => '#38bdf8', '--erp-accent-hover' => '#7dd3fc', '--erp-accent-soft' => '#082f49', '--erp-focus' => '#38bdf8',
                '--erp-success' => '#34d399', '--erp-success-soft' => '#022c22', '--erp-warning' => '#fbbf24', '--erp-warning-soft' => '#451a03', '--erp-danger' => '#fb7185', '--erp-danger-soft' => '#4c0519', '--erp-info' => '#22d3ee', '--erp-info-soft' => '#083344',
                '--erp-sidebar-bg' => '#060b16', '--erp-sidebar-text' => '#cbd5e1', '--erp-sidebar-active' => '#111a2e', '--erp-topbar-bg' => '#0b1220',
            ] + self::shapeTokens()),
            'd' => self::definition('D — Stone + Terracotta', 'Organik dan ramah untuk pengalaman kreatif.', [
                '--erp-bg-base' => '#1c1917', '--erp-bg-secondary' => '#292524', '--erp-bg-elevated' => '#44403c', '--erp-bg-inset' => '#0c0a09',
                '--erp-bg-hover' => '#44403c', '--erp-bg-active' => '#57534e', '--erp-text-primary' => '#fafaf9', '--erp-text-secondary' => '#d6d3d1', '--erp-text-muted' => '#a8a29e', '--erp-text-inverse' => '#1c1917', '--erp-text-link' => '#fdba74',
                '--erp-border' => '#44403c', '--erp-border-strong' => '#57534e', '--erp-border-focus' => '#fb923c', '--erp-accent' => '#fb923c', '--erp-accent-hover' => '#fdba74', '--erp-accent-soft' => '#431407', '--erp-focus' => '#fb923c',
                '--erp-success' => '#34d399', '--erp-success-soft' => '#022c22', '--erp-warning' => '#fbbf24', '--erp-warning-soft' => '#451a03', '--erp-danger' => '#fb7185', '--erp-danger-soft' => '#4c0519', '--erp-info' => '#38bdf8', '--erp-info-soft' => '#082f49',
                '--erp-sidebar-bg' => '#0c0a09', '--erp-sidebar-text' => '#d6d3d1', '--erp-sidebar-active' => '#292524', '--erp-topbar-bg' => '#1c1917',
            ] + self::shapeTokens()),
            'e' => self::definition('E — Terang', 'Mode terang dari palet Slate + Emerald.', [
                '--erp-bg-base' => '#f8fafc', '--erp-bg-secondary' => '#ffffff', '--erp-bg-elevated' => '#f1f5f9', '--erp-bg-inset' => '#e2e8f0',
                '--erp-bg-hover' => '#e2e8f0', '--erp-bg-active' => '#cbd5e1', '--erp-text-primary' => '#0f172a', '--erp-text-secondary' => '#334155', '--erp-text-muted' => '#5a6779', '--erp-text-inverse' => '#ffffff', '--erp-text-link' => '#0369a1',
                '--erp-border' => '#cbd5e1', '--erp-border-strong' => '#94a3b8', '--erp-border-focus' => '#059669', '--erp-accent' => '#047857', '--erp-accent-hover' => '#065f46', '--erp-accent-soft' => '#d1fae5', '--erp-focus' => '#047857',
                '--erp-success' => '#047857', '--erp-success-soft' => '#d1fae5', '--erp-warning' => '#92400e', '--erp-warning-soft' => '#fef3c7', '--erp-danger' => '#be123c', '--erp-danger-soft' => '#ffe4e6', '--erp-info' => '#0369a1', '--erp-info-soft' => '#e0f2fe',
                '--erp-sidebar-bg' => '#ffffff', '--erp-sidebar-text' => '#334155', '--erp-sidebar-active' => '#e2e8f0', '--erp-topbar-bg' => '#f8fafc',
                '--erp-card-shadow' => '0 1px 2px 0 rgb(15 23 42 / 0.12)',
            ] + self::shapeTokens()),
        ];
    }

    /** @return array{name: string, description: string, tokens: array<string, string>} */
    public static function theme(string $key): array
    {
        return self::themes()[$key] ?? throw new InvalidArgumentException("Unknown theme [$key].");
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::themes());
    }

    public static function forCompany(string $company): string
    {
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $company)) {
            return 'a';
        }

        $path = "json/$company/settings.json";
        $disk = Storage::disk('company-json');

        if (! $disk->exists($path)) {
            return 'a';
        }

        $settings = json_decode($disk->get($path), true);
        $theme = is_array($settings) ? ($settings['theme'] ?? null) : null;

        return is_string($theme) && self::has($theme) ? $theme : 'a';
    }

    public static function passesAa(string $foreground, string $background): bool
    {
        return self::contrastRatio($foreground, $background) >= 4.5;
    }

    public static function contrastRatio(string $foreground, string $background): float
    {
        $lighter = max(self::luminance($foreground), self::luminance($background));
        $darker = min(self::luminance($foreground), self::luminance($background));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /** @param array<string, string> $tokens */
    private static function definition(string $name, string $description, array $tokens): array
    {
        return compact('name', 'description', 'tokens');
    }

    /** @return array<string, string> */
    private static function shapeTokens(): array
    {
        return [
            '--erp-card-shadow' => '0 1px 2px 0 rgb(0 0 0 / 0.4)',
            '--erp-radius-sm' => '0.375rem', '--erp-radius-md' => '0.75rem', '--erp-radius-lg' => '1rem',
            '--erp-font-sans' => "'Instrument Sans', ui-sans-serif, system-ui, sans-serif",
            '--erp-font-mono' => "ui-monospace, 'JetBrains Mono', 'Cascadia Code', monospace",
        ];
    }

    private static function luminance(string $hex): float
    {
        if (! preg_match('/^#[0-9a-f]{6}$/i', $hex)) {
            throw new InvalidArgumentException("Contrast colors must use six-digit hex values; [$hex] given.");
        }

        $channels = array_map(
            static fn (string $value): float => hexdec($value) / 255,
            str_split(substr($hex, 1), 2),
        );

        $linear = array_map(
            static fn (float $channel): float => $channel <= 0.04045
                ? $channel / 12.92
                : (($channel + 0.055) / 1.055) ** 2.4,
            $channels,
        );

        return (0.2126 * $linear[0]) + (0.7152 * $linear[1]) + (0.0722 * $linear[2]);
    }
}
