<?php

namespace App\Http\Controllers\Api\TenantBot;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\FeatureResolver;
use Illuminate\Http\Request;

class CapabilitiesController extends Controller
{
    public function index(Request $request)
    {
        $payload = $request->validate([
            'company_id' => 'required|integer',
        ]);

        $company = Company::findOrFail($payload['company_id']);

        $capabilities = [];
        foreach (FeatureResolver::CAPABILITIES as $key) {
            $capabilities[$key] = $company->feature($key);
        }

        return response()->json([
            'capabilities' => $capabilities,
            'sensitive_capabilities' => FeatureResolver::SENSITIVE_CAPABILITIES,
            'privacy_accepted' => $company->privacy_accepted_at !== null,
        ]);
    }
}
