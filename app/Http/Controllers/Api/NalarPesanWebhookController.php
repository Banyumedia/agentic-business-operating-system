<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessIdentity;
use App\Models\Item;
use App\Models\Order;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NalarPesanWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $signature = $request->header('X-NalarPesan-Signature');
        $secret = (string) config('services.nalarpesan.webhook_secret', '');

        if ($secret === '') {
            return response()->json(['error' => 'Webhook secret not configured'], 403);
        }

        $expectedSignature = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expectedSignature, $signature ?? '')) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $payload = $request->validate([
            'external_ref' => 'required|string',
            'business_identity_id' => 'required|integer',
            'company_id' => 'required|integer',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'grand_total' => 'required|numeric|min:0',
            'resource_id' => 'nullable|integer',
        ]);

        $businessIdentity = BusinessIdentity::where('company_id', $payload['company_id'])
            ->where('id', $payload['business_identity_id'])
            ->first();

        if (! $businessIdentity) {
            return response()->json(['error' => 'Business identity not found'], 404);
        }

        $existingOrder = Order::where('company_id', $payload['company_id'])
            ->where('external_ref', $payload['external_ref'])
            ->first();

        if ($existingOrder) {
            return response()->json(['status' => 'success', 'message' => 'Order already processed']);
        }

        $itemIds = collect($payload['items'])->pluck('item_id')->unique()->values();
        $validItemCount = Item::where('company_id', $payload['company_id'])
            ->whereIn('id', $itemIds)
            ->count();

        if ($validItemCount !== $itemIds->count()) {
            return response()->json(['error' => 'Invalid item reference for company'], 422);
        }

        try {
            $order = DB::transaction(function () use ($payload) {
                $order = Order::create([
                    'company_id' => $payload['company_id'],
                    'business_identity_id' => $payload['business_identity_id'],
                    'order_no' => 'NP-'.strtoupper(substr(sha1($payload['external_ref']), 0, 12)),
                    'stage' => 'open',
                    'subtotal' => $payload['grand_total'],
                    'grand_total' => $payload['grand_total'],
                    'source' => 'nalar_pesan',
                    'external_ref' => $payload['external_ref'],
                    'resource_id' => $payload['resource_id'] ?? null,
                ]);

                foreach ($payload['items'] as $item) {
                    $order->lines()->create([
                        'company_id' => $payload['company_id'],
                        'item_id' => $item['item_id'],
                        'qty' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'line_total' => $item['quantity'] * $item['unit_price'],
                        'description' => 'Menu Item',
                    ]);
                }

                return $order;
            });
        } catch (QueryException $exception) {
            $existingOrder = Order::where('company_id', $payload['company_id'])
                ->where('external_ref', $payload['external_ref'])
                ->first();

            if ($existingOrder) {
                return response()->json(['status' => 'success', 'message' => 'Order already processed']);
            }

            throw $exception;
        }

        return response()->json(['status' => 'success', 'order_id' => $order->id]);
    }
}
