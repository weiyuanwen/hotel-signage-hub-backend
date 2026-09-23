<?php

namespace App\Domains\Billing;

use App\Models\BillingOrder;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripeCheckout
{
    /**
     * @return array{id: string, url: string}
     */
    public function createSession(BillingOrder $order): array
    {
        $secret = (string) config('services.stripe.secret');
        if ($secret === '') {
            throw new RuntimeException('Stripe is not configured.');
        }

        $cms = rtrim((string) config('services.billing.cms_url', 'https://signagehub.online'), '/');
        $name = $order->plan === HotelPlan::PREMIUM ? 'SignageHub many screens' : 'SignageHub 3 TVs';
        $join = $order->locale === 'vi' ? '/vi/tham-gia' : '/join';
        $pricing = $order->locale === 'vi' ? '/vi/bang-gia' : '/pricing';

        $res = Http::withToken($secret)
            ->asForm()
            ->timeout(20)
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'success_url' => $cms.$join.'?paid='.$order->order_code,
                'cancel_url' => $cms.$pricing.'?canceled=1',
                'client_reference_id' => $order->order_code,
                'customer_email' => $order->email,
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => 'usd',
                'line_items[0][price_data][unit_amount]' => $order->amount_usd_cents,
                'line_items[0][price_data][product_data][name]' => $name,
                'metadata[order_code]' => $order->order_code,
            ]);

        if (! $res->successful()) {
            throw new RuntimeException('Stripe checkout failed: '.$res->body());
        }

        return [
            'id' => (string) $res->json('id'),
            'url' => (string) $res->json('url'),
        ];
    }
}
