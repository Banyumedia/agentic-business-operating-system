<?php

namespace App\Services\WhatsApp;

class NluIntentRouter
{
    /**
     * Parsing pesan teks bahasa manusia menjadi intent bisnis terstruktur.
     */
    public function route(string $message): IntentResult
    {
        $text = trim($message);
        $lower = mb_strtolower($text);

        // 1. Approval Intent (acc, setujui, tolak diskon/tiket)
        if (preg_match('/\b(acc|setujui|approve|tolak|reject)\b/i', $lower)) {
            $action = preg_match('/\b(tolak|reject)\b/i', $lower) ? 'reject' : 'approve';
            preg_match('/\b(?:tiket|id|nomor|no)?\s*#?(\d+)\b/i', $text, $matches);
            $ticketId = isset($matches[1]) ? (int) $matches[1] : null;

            return new IntentResult('approval', [
                'action' => $action,
                'ticket_id' => $ticketId,
                'raw' => $text,
            ]);
        }

        // 2. Reminder Intent (ingatkan, pasang alarm, jadwal follow up)
        if (preg_match('/\b(ingatkan|jadwalkan|ingat|reminder)\b/i', $lower)) {
            return new IntentResult('reminder', [
                'task' => $this->extractReminderTask($text),
                'remind_at' => $this->extractTime($text),
                'raw' => $text,
            ]);
        }

        // 3. Setup/Setting Intent (ubah diskon, ganti jam buka, ganti sebutan)
        if (preg_match('/\b(ubah|ganti|set|atur|setting)\b/i', $lower) && preg_match('/\b(diskon|jam|buka|tutup|panggilan|sebutan|istilah)\b/i', $lower)) {
            return new IntentResult('setup', $this->extractSetupParams($text));
        }

        // 4. Report Intent (cek omzet, laporan kas, cek stok)
        if (preg_match('/\b(omzet|omset|kas|stok|saldo|laporan|penjualan)\b/i', $lower)) {
            return new IntentResult('report', [
                'metric' => $this->extractMetric($lower),
                'raw' => $text,
            ]);
        }

        // Fallback / General Assistant Conversation
        return new IntentResult('fallback', ['raw' => $text], 0.5);
    }

    private function extractReminderTask(string $text): string
    {
        // Hilangkan kata pemicu di awal
        $cleaned = preg_replace('/^(tolong\s+)?(ingatkan|jadwalkan|ingat)\s+(saya|kami|tim)?\s*/i', '', $text);

        return trim($cleaned);
    }

    private function extractTime(string $text): ?string
    {
        if (preg_match('/\b(besok|nanti|pagi|siang|sore|malam|\d{1,2}[:.]\d{2})\b/i', $text)) {
            if (preg_match('/(\d{1,2})[:.](\d{2})/', $text, $m)) {
                return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
            }

            return 'besok';
        }

        return null;
    }

    private function extractSetupParams(string $text): array
    {
        $lower = mb_strtolower($text);
        $params = ['raw' => $text];

        if (preg_match('/\bdiskon\b.*?(?:jadi|ke|sebesar)?\s*(\d+)\s*%/i', $text, $m)) {
            $params['key'] = 'max_discount_percent';
            $params['value'] = (int) $m[1];
        } elseif (preg_match('/\b(sebutan|panggilan|istilah)\b\s+(\w+)\s+(?:jadi|menjadi)\s+(\w+)/i', $text, $m)) {
            $params['key'] = 'terminology';
            $params['term_key'] = strtolower($m[2]);
            $params['term_value'] = $m[3];
        }

        return $params;
    }

    private function extractMetric(string $lower): string
    {
        if (str_contains($lower, 'omzet') || str_contains($lower, 'omset') || str_contains($lower, 'penjualan')) {
            return 'revenue';
        }
        if (str_contains($lower, 'stok')) {
            return 'inventory';
        }
        if (str_contains($lower, 'kas') || str_contains($lower, 'saldo')) {
            return 'cashflow';
        }

        return 'general_report';
    }
}
