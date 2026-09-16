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
        ];

        foreach (array_keys(ThemeRegistry::themes()) as $theme) {
            foreach ($pairs as [$foreground, $background]) {
                yield "$theme:$foreground:$background" => [$theme, $foreground, $background];
            }
        }
    }
}
