<?php

namespace Tests\Feature;

use App\Models\AiReminder;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_isolation_payroll_and_reminder(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $employeeA = Employee::factory()->create(['company_id' => $companyA->id]);

        Payroll::factory()->create([
            'company_id' => $companyA->id,
            'employee_id' => $employeeA->id,
        ]);

        AiReminder::factory()->create([
            'company_id' => $companyA->id,
        ]);

        $this->assertEquals(0, Payroll::where('company_id', $companyB->id)->count());
        $this->assertEquals(0, AiReminder::where('company_id', $companyB->id)->count());
        $this->assertEquals(0, Employee::where('company_id', $companyB->id)->count());

        $this->assertEquals(1, Payroll::where('company_id', $companyA->id)->count());
        $this->assertEquals(1, AiReminder::where('company_id', $companyA->id)->count());
        $this->assertEquals(1, Employee::where('company_id', $companyA->id)->count());
    }

    public function test_unique_payroll_per_employee_and_period(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $period = '2026-09';

        Payroll::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_month' => $period,
        ]);

        $this->expectException(QueryException::class);

        Payroll::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_month' => $period,
        ]);
    }
}
