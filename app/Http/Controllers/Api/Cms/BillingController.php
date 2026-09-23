<?php

namespace App\Http\Controllers\Api\Cms;

use App\Domains\Billing\BillingOrderService;
use App\Domains\Billing\HotelPlan;
use App\Http\Controllers\Controller;
use App\Models\BillingOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BillingController extends Controller
{
    public function checkout(Request $request, BillingOrderService $billing): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'hotel_name' => ['nullable', 'string', 'max:255'],
            'plan' => ['required', 'string', Rule::in([HotelPlan::STANDARD, HotelPlan::PREMIUM])],
            'method' => ['required', 'string', Rule::in(config('services.stripe.enabled') ? ['bank', 'stripe'] : ['bank'])],
            'locale' => ['nullable', 'string', 'max:8'],
        ]);

        return response()->json($billing->checkout(
            $data['email'],
            $data['plan'],
            $data['method'],
            $data['hotel_name'] ?? null,
            $data['locale'] ?? 'vi',
        ), 201);
    }

    public function show(string $code, BillingOrderService $billing): JsonResponse
    {
        $order = BillingOrder::query()->where('order_code', strtoupper($code))->firstOrFail();

        return response()->json($billing->payload($order));
    }

    public function stripeWebhook(Request $request, BillingOrderService $billing): JsonResponse
    {
        $secret = (string) config('services.stripe.webhook_secret');
        if ($secret !== '' && ! app()->environment('testing')) {
            $this->verifyStripeSignature($request, $secret);
        }

        $type = (string) $request->input('type');
        $object = $request->input('data.object', []);
        if ($type !== 'checkout.session.completed' || ($object['payment_status'] ?? '') !== 'paid') {
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        $code = (string) ($object['client_reference_id'] ?? data_get($object, 'metadata.order_code') ?? '');
        $order = BillingOrder::query()->where('order_code', strtoupper($code))->first();
        if (! $order) {
            return response()->json(['ok' => false], 404);
        }

        $billing->markPaid($order, 'stripe', (string) ($object['id'] ?? ('cs_'.$order->order_code)), 'stripe checkout');

        return response()->json(['ok' => true]);
    }

    private function verifyStripeSignature(Request $request, string $secret): void
    {
        $header = (string) $request->header('Stripe-Signature', '');
        $payload = $request->getContent();
        $timestamp = null;
        $signature = null;
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't') {
                $timestamp = $value;
            }
            if ($key === 'v1') {
                $signature = $value;
            }
        }
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        if (! $timestamp || ! $signature || ! hash_equals($expected, $signature)) {
            abort(400, 'Invalid Stripe signature.');
        }
    }
}
