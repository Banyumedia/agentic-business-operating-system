<?php

namespace App\Http\Controllers\App;

use App\Contracts\CompanyContext;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\CompanyGroup\GroupReportService;
use Illuminate\Http\Request;

class GroupReportController extends Controller
{
    public function show(Request $request, CompanyContext $context, GroupReportService $service)
    {
        $company = Company::query()->whereKey($context->current())->firstOrFail();

        if ($company->owner_user_id !== $request->user()->id) {
            abort(403, 'Hanya owner yang dapat melihat laporan gabungan cabang.');
        }

        if (! $company->feature('addon.branches')) {
            abort(403, 'Fitur cabang tidak aktif.');
        }

        $aggregate = $service->getAggregate($company);

        return view('app.group-report', [
            'company' => $company,
            'aggregate' => $aggregate,
        ]);
    }
}
