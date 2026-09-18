<?php

namespace App\Http\Controllers\Api\TenantBot;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantBotController extends Controller
{
    private function checkCapability(Company $company, string $capability): ?JsonResponse
    {
        if (! $company->feature($capability)) {
            return response()->json(['error' => 'Capability '.$capability.' is disabled or requires privacy consent.'], 403);
        }

        return null;
    }

    public function updateSettings(Request $request)
    {
        $payload = $request->validate([
            'company_id' => 'required|integer',
            'features' => 'nullable|array',
            'terminology' => 'nullable|array',
        ]);

        $user = $request->attributes->get('bot_caller_user');
        $company = Company::findOrFail($payload['company_id']);

        if ($company->owner_user_id !== $user->id) {
            return response()->json(['error' => 'Only company owner can update settings'], 403);
        }

        $settings = $company->settings()->where('module_name', 'core')->first();
        if (! $settings) {
            $settings = $company->settings()->create([
                'module_name' => 'core',
                'settings_json' => [],
            ]);
        }

        $json = $settings->settings_json ?? [];

        if (isset($payload['features'])) {
            $json['features'] = array_merge($json['features'] ?? [], $payload['features']);
        }

        if (isset($payload['terminology'])) {
            $json['terminology'] = array_merge($json['terminology'] ?? [], $payload['terminology']);
        }

        $settings->settings_json = $json;
        $settings->save();

        return response()->json(['status' => 'success']);
    }

    public function createContact(Request $request)
    {
        $payload = $request->validate([
            'company_id' => 'required|integer',
            'name' => 'required|string',
            'phone' => 'nullable|string',
        ]);

        $company = Company::findOrFail($payload['company_id']);
        if ($errorResponse = $this->checkCapability($company, 'contacts')) {
            return $errorResponse;
        }

        $contact = Contact::create([
            'company_id' => $payload['company_id'],
            'name' => $payload['name'],
            'wa_number' => $payload['phone'] ?? null,
            'type' => 'customer',
        ]);

        return response()->json(['status' => 'success', 'id' => $contact->id]);
    }

    public function createDeal(Request $request)
    {
        $payload = $request->validate([
            'company_id' => 'required|integer',
            'contact_id' => 'required|integer',
            'title' => 'required|string',
            'amount' => 'required|numeric',
            'stage' => 'required|string',
        ]);

        $company = Company::findOrFail($payload['company_id']);
        if ($errorResponse = $this->checkCapability($company, 'deals')) {
            return $errorResponse;
        }

        $deal = Deal::create([
            'company_id' => $payload['company_id'],
            'contact_id' => $payload['contact_id'],
            'title' => $payload['title'],
            'amount' => $payload['amount'],
            'stage' => $payload['stage'],
        ]);

        return response()->json(['status' => 'success', 'id' => $deal->id]);
    }

    public function destructiveAction(Request $request)
    {
        $payload = $request->validate([
            'company_id' => 'required|integer',
            'action' => 'required|string',
            'description' => 'required|string',
        ]);

        $user = $request->attributes->get('bot_caller_user');

        $ticket = SupportTicket::create([
            'company_id' => $payload['company_id'],
            'reported_by_user_id' => $user->id,
            'ticket_number' => 'APP-'.time().'-'.rand(1000, 9999),
            'subject' => 'Approval: '.$payload['action'],
            'description' => $payload['description'],
            'status' => 'open',
        ]);

        return response()->json([
            'status' => 'pending_approval',
            'message' => 'Action requires approval',
            'ticket_number' => $ticket->ticket_number,
        ]);
    }
}
