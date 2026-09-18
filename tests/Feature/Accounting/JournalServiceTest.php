<?php

namespace Tests\Feature\Accounting;

use App\Models\AccountingJournal;
use App\Models\AccountingJournalLine;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Project;
use App\Services\Accounting\JournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class JournalServiceTest extends TestCase
{
    use RefreshDatabase;

    private JournalService $service;

    private Company $companyA;

    private Company $companyB;

    private ChartOfAccount $accountAsset;

    private ChartOfAccount $accountRevenue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new JournalService;

        $this->companyA = Company::factory()->create();
        $this->companyB = Company::factory()->create();

        $this->accountAsset = ChartOfAccount::create([
            'company_id' => $this->companyA->id,
            'account_code' => '1000',
            'name' => 'Cash',
            'type' => 'asset',
        ]);

        $this->accountRevenue = ChartOfAccount::create([
            'company_id' => $this->companyA->id,
            'account_code' => '4000',
            'name' => 'Sales',
            'type' => 'revenue',
        ]);
    }

    public function test_post_successful_when_journal_is_balanced(): void
    {
        $project = Project::create([
            'company_id' => $this->companyA->id,
            'name' => 'Test Project',
            'stage' => 'planned',
        ]);

        $header = [
            'company_id' => $this->companyA->id,
            'journal_number' => 'JV-001',
            'transaction_date' => '2026-09-18',
            'reference' => 'REF-123',
            'description' => 'Test journal',
        ];

        $lines = [
            [
                'account_id' => $this->accountAsset->id,
                'debit' => 1000.00,
                'credit' => 0.00,
                'project_id' => $project->id,
            ],
            [
                'account_id' => $this->accountRevenue->id,
                'debit' => 0.00,
                'credit' => 1000.00,
                'project_id' => null,
            ],
        ];

        $journal = $this->service->post($header, $lines);

        $this->assertInstanceOf(AccountingJournal::class, $journal);
        $this->assertEquals('JV-001', $journal->journal_number);

        $this->assertCount(2, $journal->lines);
        $this->assertEquals($this->companyA->id, $journal->lines->first()->company_id);

        $this->assertDatabaseHas('accounting_journals', [
            'id' => $journal->id,
            'company_id' => $this->companyA->id,
            'journal_number' => 'JV-001',
        ]);

        $this->assertDatabaseHas('accounting_journal_lines', [
            'journal_id' => $journal->id,
            'account_id' => $this->accountAsset->id,
            'project_id' => $project->id,
            'debit' => 1000.00,
            'credit' => 0.00,
        ]);

        $this->assertDatabaseHas('accounting_journal_lines', [
            'journal_id' => $journal->id,
            'account_id' => $this->accountRevenue->id,
            'project_id' => null,
            'debit' => 0.00,
            'credit' => 1000.00,
        ]);
    }

    public function test_post_throws_exception_when_not_balanced(): void
    {
        $header = [
            'company_id' => $this->companyA->id,
            'journal_number' => 'JV-002',
            'transaction_date' => '2026-09-18',
        ];

        $lines = [
            [
                'account_id' => $this->accountAsset->id,
                'debit' => 1000.00,
                'credit' => 0.00,
            ],
            [
                'account_id' => $this->accountRevenue->id,
                'debit' => 0.00,
                'credit' => 500.00, // Not balanced
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Journal is not balanced');

        try {
            $this->service->post($header, $lines);
        } finally {
            $this->assertDatabaseMissing('accounting_journals', [
                'journal_number' => 'JV-002',
            ]);
            $this->assertEquals(0, AccountingJournalLine::count());
        }
    }

    public function test_cashbook_expense_template_builds_debit_expense_credit_cash(): void
    {
        $expense = ChartOfAccount::create([
            'company_id' => $this->companyA->id,
            'account_code' => '5000',
            'name' => 'Operational Expense',
            'type' => 'expense',
        ]);

        $cash = ChartOfAccount::create([
            'company_id' => $this->companyA->id,
            'account_code' => '1001',
            'name' => 'Cash On Hand',
            'type' => 'asset',
        ]);

        $lines = $this->service->cashbookExpenseLines(
            expenseAccountId: $expense->id,
            cashAccountId: $cash->id,
            amount: 250000,
            description: 'Beli ATK'
        );

        $this->assertCount(2, $lines);
        $this->assertSame($expense->id, $lines[0]['account_id']);
        $this->assertSame(250000.0, $lines[0]['debit']);
        $this->assertSame(0.0, $lines[0]['credit']);

        $this->assertSame($cash->id, $lines[1]['account_id']);
        $this->assertSame(0.0, $lines[1]['debit']);
        $this->assertSame(250000.0, $lines[1]['credit']);
    }

    public function test_tenant_isolation(): void
    {
        $accountB = ChartOfAccount::create([
            'company_id' => $this->companyB->id,
            'account_code' => '1000',
            'name' => 'Cash B',
            'type' => 'asset',
        ]);

        $header = [
            'company_id' => $this->companyB->id,
            'journal_number' => 'JV-B01',
            'transaction_date' => '2026-09-18',
        ];

        $lines = [
            [
                'account_id' => $accountB->id,
                'debit' => 100.00,
                'credit' => 0.00,
            ],
            [
                'account_id' => $accountB->id,
                'debit' => 0.00,
                'credit' => 100.00,
            ],
        ];

        $journal = $this->service->post($header, $lines);

        // Check if query scoped by company_id can access it
        $this->assertTrue(AccountingJournal::where('company_id', $this->companyB->id)->exists());
        $this->assertFalse(AccountingJournal::where('company_id', $this->companyA->id)->where('id', $journal->id)->exists());

        $this->assertTrue(AccountingJournalLine::where('company_id', $this->companyB->id)->exists());
        $this->assertFalse(AccountingJournalLine::where('company_id', $this->companyA->id)->where('journal_id', $journal->id)->exists());
    }
}
