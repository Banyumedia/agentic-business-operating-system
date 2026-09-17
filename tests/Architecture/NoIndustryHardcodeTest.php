<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

class NoIndustryHardcodeTest extends TestCase
{
    public function test_no_industry_names_hardcoded_in_app_and_resources()
    {
        // Industries to check against
        $industries = [
            'bengkel', 'klinik', 'salon', 'agency', 'apotek', 
            'pharmacy', 'rental', 'kontraktor', 'laundry'
        ];
        
        $regex = '/\b(' . implode('|', $industries) . ')\b/i';
        
        $directories = [
            __DIR__ . '/../../app',
            __DIR__ . '/../../resources/views',
        ];
        
        $violations = [];
        
        foreach ($directories as $dir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['php', 'blade.php'])) {
                    $content = file_get_contents($file->getPathname());
                    
                    // Exclude comments if possible (simplified approach here)
                    // For now, doing a raw regex match.
                    if (preg_match_all($regex, $content, $matches, PREG_OFFSET_CAPTURE)) {
                        $relativePath = str_replace(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR, '', $file->getPathname());
                        
                        foreach ($matches[0] as $match) {
                            $word = $match[0];
                            $offset = $match[1];
                            $line = substr_count(substr($content, 0, $offset), "\n") + 1;
                            
                            $violations[] = "{$relativePath}:{$line} contains hardcoded industry name '{$word}'";
                        }
                    }
                }
            }
        }
        
        $this->assertEmpty(
            $violations, 
            "Found hardcoded industry names in codebase:\n" . implode("\n", $violations)
        );
    }
}
