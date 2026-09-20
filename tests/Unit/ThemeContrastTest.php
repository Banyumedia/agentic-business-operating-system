<?php

namespace Tests\Unit;

use App\Services\ThemeRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ThemeContrastTest extends TestCase
{
    public function test_registry_exposes_five_themes_with_all_36_tokens(): void
    {
        $themes = ThemeRegistry::themes();

        $this->assertSame(['a', 'b', 'c', 'd', 'e'], array_keys($themes));

        foreach ($themes as $theme) {
            $this->assertCount(36, $theme['tokens']);
        }
    }

    public function test_css_declares_all_36_erp_tokens_for_every_theme(): void
    {
        $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');
        $this->assertIsString($css);

        $blocks = ['@theme' => '@theme'];
        foreach (array_keys(ThemeRegistry::themes()) as $theme) {
            $blocks[$theme] = '[data-theme="'.$theme.'"]';
        }

        foreach ($blocks as $label => $selector) {
            $pattern = '/'.preg_quote($selector, '/').'\\s*\\{(?<body>.*?)\\}/s';
            $this->assertSame(1, preg_match($pattern, $css, $match), "Missing CSS block [$label].");
            $this->assertSame(36, preg_match_all('/--erp-[a-z-]+\\s*:/', $match['body']), "Theme [$label] must declare 36 ERP tokens.");
        }
    }

    #[DataProvider('requiredContrastPairs')]
    public function test_required_text_pair_passes_wcag_aa(string $theme, string $foreground, string $background): void
    {
        $tokens = ThemeRegistry::theme($theme)['tokens'];

        $this->assertTrue(
            ThemeRegistry::passesAa($tokens[$foreground], $tokens[$background]),
            "$theme: $foreground on $background must be at least 4.5:1",
        );
    }

    public static function requiredContrastPairs(): iterable
    {
        // 11 pasangan wajib §7.2 UX_UI_SPEC + pasangan ekstra yang benar-benar
        // dipakai komponen nyata: teks redum/pendukung di atas kartu elevated
        // (ringkasan onboarding, kartu alur settings, dl dummy-module), teks
        // status di atas latarnya yang lembut (badge/bita di widget, list,
        // pipeline), dan tautan di dalam kartu secondary (data-export).
        $pairs = [
            ['--erp-text-primary', '--erp-bg-base'],
            ['--erp-text-primary', '--erp-bg-secondary'],
            ['--erp-text-primary', '--erp-bg-elevated'],
            ['--erp-text-secondary', '--erp-bg-base'],
            ['--erp-text-muted', '--erp-bg-base'],
            ['--erp-text-inverse', '--erp-accent'],
            ['--erp-text-link', '--erp-bg-base'],
            ['--erp-sidebar-text', '--erp-sidebar-bg'],
            ['--erp-text-inverse', '--erp-success'],
            ['--erp-text-inverse', '--erp-warning'],
            ['--erp-text-inverse', '--erp-danger'],
            ['--erp-text-muted', '--erp-bg-secondary'],
            ['--erp-text-muted', '--erp-bg-elevated'],
            ['--erp-text-muted', '--erp-bg-inset'],
            ['--erp-text-secondary', '--erp-bg-secondary'],
            ['--erp-text-secondary', '--erp-bg-elevated'],
            ['--erp-text-link', '--erp-bg-secondary'],
            ['--erp-danger', '--erp-danger-soft'],
            ['--erp-success', '--erp-success-soft'],
            ['--erp-warning', '--erp-warning-soft'],
            ['--erp-info', '--erp-info-soft'],
            ['--erp-accent', '--erp-accent-soft'],
        ];

        foreach (array_keys(ThemeRegistry::themes()) as $theme) {
            foreach ($pairs as [$foreground, $background]) {
                yield "$theme:$foreground:$background" => [$theme, $foreground, $background];
            }
        }
    }

    #[DataProvider('focusRingThemes')]
    public function test_focus_ring_meets_non_text_contrast(string $theme): void
    {
        $tokens = ThemeRegistry::theme($theme)['tokens'];

        // WCAG 1.4.11 (non-teks): ring/border fokus wajib >= 3:1 di atas latar halaman.
        foreach (['--erp-focus', '--erp-border-focus'] as $ring) {
            $this->assertGreaterThanOrEqual(
                3.0,
                ThemeRegistry::contrastRatio($tokens[$ring], $tokens['--erp-bg-base']),
                "$theme: $ring must be at least 3:1 against --erp-bg-base",
            );
        }
    }

    public static function focusRingThemes(): iterable
    {
        foreach (array_keys(ThemeRegistry::themes()) as $theme) {
            yield $theme => [$theme];
        }
    }

    public function test_css_token_values_match_the_registry_for_every_theme(): void
    {
        // Registry adalah sumber kebenaran test; CSS harus memakai nilai yang
        // sama supaya perbaikan kontras tidak hanya hidup di satu sisi.
        $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');
        $this->assertIsString($css);

        foreach (ThemeRegistry::themes() as $key => $theme) {
            $selector = $key === 'a' ? '@theme' : '[data-theme="'.$key.'"]';
            $this->assertSame(1, preg_match('/'.preg_quote($selector, '/').'\s*\{(?<body>.*?)\}/s', $css, $match), "Missing CSS block [$selector].");

            foreach ($theme['tokens'] as $token => $value) {
                if (! preg_match('/^#[0-9a-f]{6}$/i', (string) $value)) {
                    continue;
                }

                $found = preg_match('/'.preg_quote($token, '/').'\s*:\s*(?<value>#[0-9a-fA-F]{6})\s*;/', $match['body'], $tokenMatch) === 1;
                $this->assertTrue($found, "CSS block [$selector] missing color token [$token].");
                $this->assertSame(
                    strtolower($value),
                    strtolower($tokenMatch['value']),
                    "CSS block [$selector] token [$token] diverges from ThemeRegistry ({$tokenMatch['value']} vs $value).",
                );
            }
        }
    }
}
