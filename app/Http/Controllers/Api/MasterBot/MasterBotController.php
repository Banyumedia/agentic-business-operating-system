<?php

namespace App\Http\Controllers\Api\MasterBot;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\Request;

class MasterBotController extends Controller
{
    private function resolveCompany(Request $request)
    {
        $waNumber = $request->input('wa_number');

        if (! $waNumber) {
            abort(400, 'wa_number is required');
        }

        $user = User::where('wa_number', $waNumber)->first();

        if (! $user || ! $user->current_company_id) {
            abort(403, 'User does not belong to any company');
        }

        return Company::findOrFail($user->current_company_id);
    }

    public function createTicket(Request $request)
    {
        $payload = $request->validate([
            'wa_number' => 'required|string',
            'subject' => 'required|string',
            'description' => 'required|string',
        ]);

        $company = $this->resolveCompany($request);
        $user = User::where('wa_number', $payload['wa_number'])->first();

        $ticket = SupportTicket::create([
            'company_id' => $company->id,
            'reported_by_user_id' => $user->id,
            'ticket_number' => 'TKT-'.time().'-'.rand(1000, 9999),
            'subject' => $payload['subject'],
            'description' => $payload['description'],
        ]);

        return response()->json([
            'status' => 'success',
            'ticket_id' => $ticket->ticket_number,
        ]);
    }

    public function checkBalance(Request $request)
    {
        $request->validate([
            'wa_number' => 'required|string',
        ]);

        $company = $this->resolveCompany($request);
        $membership = $company->memberships()->first();

        if (! $membership) {
            return response()->json(['error' => 'No active membership found'], 404);
        }

        return response()->json([
            'balance' => $membership->current_token_balance,
            'status' => $membership->status,
        ]);
    }

    public function createTopupInvoice(Request $request)
    {
        $payload = $request->validate([
            'wa_number' => 'required|string',
            'amount' => 'required|numeric',
        ]);

        $company = $this->resolveCompany($request);
        $membership = $company->memberships()->first();

        if (! $membership) {
            return response()->json(['error' => 'No active membership found'], 404);
        }

        $invoice = $company->invoices()->create([
            'company_membership_id' => $membership->id,
            'type' => 'topup',
            'order_id' => 'TOPUP-'.time().'-'.rand(1000, 9999),
            'amount' => $payload['amount'],
            'payment_status' => 'pending',
        ]);

        return response()->json([
            'status' => 'success',
            'invoice_id' => $invoice->order_id,
            'payment_url' => 'https://fake-payment-gateway.com/pay/'.$invoice->order_id,
        ]);
    }
}
