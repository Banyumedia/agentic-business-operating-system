<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

class NoLiteralTermsTest extends TestCase
{
    public function test_no_literal_terms_hardcoded_in_views()
    {
        // Check for specific literal terms that should use term()
        $literals = [
            'Pelanggan', 'Klien', 'Pasien', 'Penyewa',
            'Pegawai', 'Karyawan', 'Terapis', 'Mekanik'
        ];
        
        $dir = __DIR__ . '/../../resources/views';
        
        $violations = [];
        
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());
                
                // Only check text nodes in HTML or strings, roughly. 
                // A stricter way is to parse blade, but regex usually catches literal text.
                foreach ($literals as $literal) {
                    $regex = '/(?<![\'"])' . preg_quote($literal, '/') . '(?![\'"])/'; // naive check outside quotes mostly
                    
                    if (preg_match_all($regex, $content, $matches, PREG_OFFSET_CAPTURE)) {
                        $relativePath = str_replace(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR, '', $file->getPathname());
                        
                        foreach ($matches[0] as $match) {
                            $offset = $match[1];
                            $line = substr_count(substr($content, 0, $offset), "\n") + 1;
                            
                            $violations[] = "{$relativePath}:{$line} contains literal term '{$literal}'. Use {{ term('...') }} instead.";
                        }
                    }
                }
            }
        }
        
        // This test might be noisy if there are legit uses, but per D-31 it should be 0.
        // We will assert empty and see what fails.
        $this->assertEmpty(
            $violations, 
            "Found literal terms in blade views:\n" . implode("\n", array_unique($violations))
        );
    }
}
