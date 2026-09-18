<?php

namespace App\Services\Accounting;

use App\Models\AccountingJournal;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class JournalService
{
    /**
     * @param  array{company_id: int, journal_number: string, transaction_date: string, reference?: string|null, description?: string|null}  $header
     * @param  array<int, array{account_id: int, debit: float, credit: float, project_id?: int|null, description?: string|null}>  $lines
     */
    public function post(array $header, array $lines): AccountingJournal
    {
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($lines as $line) {
            $totalDebit += (float) ($line['debit'] ?? 0);
            $totalCredit += (float) ($line['credit'] ?? 0);
        }

        if (abs($totalDebit - $totalCredit) >= 0.01) {
            throw new InvalidArgumentException('Journal is not balanced. Debit: '.$totalDebit.', Credit: '.$totalCredit);
        }

        return DB::transaction(function () use ($header, $lines) {
            $journal = AccountingJournal::create($header);

            foreach ($lines as $line) {
                $journal->lines()->create([
                    'company_id' => $journal->company_id,
                    'account_id' => $line['account_id'],
                    'project_id' => $line['project_id'] ?? null,
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'description' => $line['description'] ?? null,
                ]);
            }

            return $journal;
        });
    }

    /**
     * Template jurnal cashbook untuk biaya operasional:
     * Debit akun beban, Kredit akun kas.
     *
     * @return array<int, array{account_id: int, debit: float, credit: float, project_id?: int|null, description?: string|null}>
     */
    public function cashbookExpenseLines(int $expenseAccountId, int $cashAccountId, float $amount, ?string $description = null, ?int $projectId = null): array
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Cashbook expense amount must be greater than zero.');
        }

        return [
            [
                'account_id' => $expenseAccountId,
                'debit' => $amount,
                'credit' => 0.0,
                'project_id' => $projectId,
                'description' => $description,
            ],
            [
                'account_id' => $cashAccountId,
                'debit' => 0.0,
                'credit' => $amount,
                'project_id' => $projectId,
                'description' => $description,
            ],
        ];
    }
}
