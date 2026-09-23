<?php

namespace App\Domains\Billing;

use App\Models\BillingOrder;
use App\Models\BillingTransaction;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BillingOrderService
{
    public function __construct(
        private VietQrService $vietQr,
        private StripeCheckout $stripe,
        private TelegramNotifier $telegram,
        private WaitlistSignupService $waitlist,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function checkout(string $email, string $plan, string $method, ?string $hotelName = null, string $locale = 'vi'): array
    {
        $email = Str::lower(trim($email));
        if (! HotelPlan::isPaid($plan)) {
            throw ValidationException::withMessages(['plan' => 'Chọn gói 3 TV hoặc nhiều TV.']);
        }
        if ($method === 'stripe' && ! config('services.stripe.enabled')) {
            throw ValidationException::withMessages(['method' => 'Tạm thời chỉ nhận chuyển khoản Việt Nam.']);
        }
        if (! in_array($method, ['bank', 'stripe'], true)) {
            throw ValidationException::withMessages(['method' => 'Chọn chuyển khoản Việt Nam.']);
        }

        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            $this->waitlist->register($email, $hotelName, HotelPlan::FREE);
            $user = User::query()->where('email', $email)->firstOrFail();
        }

        $hotel = $user->hotels()->first();

        $code = 'SHUB'.Str::upper(Str::random(10));
        $order = BillingOrder::query()->create([
            'order_code' => $code,
            'hotel_id' => $hotel?->id,
            'user_id' => $user->id,
            'email' => $email,
            'hotel_name' => $hotelName ?: $hotel?->name,
            'plan' => $plan,
            'method' => $method,
            'amount_vnd' => HotelPlan::amountVnd($plan),
            'amount_usd_cents' => HotelPlan::amountUsdCents($plan),
            'status' => BillingOrder::PENDING,
            'locale' => $locale,
            'transfer_content' => $method === 'bank' ? $code : null,
            'qr_image_url' => $method === 'bank' ? $this->vietQr->imageUrl($code, HotelPlan::amountVnd($plan)) : null,
            'expires_at' => $method === 'bank' ? now()->addMinutes(12) : now()->addHour(),
        ]);

        if ($method === 'stripe') {
            $session = $this->stripe->createSession($order);
            $order->forceFill([
                'stripe_session_id' => $session['id'],
                'stripe_url' => $session['url'] !== '' ? $session['url'] : null,
            ])->save();
        }

        return $this->payload($order->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(BillingOrder $order): array
    {
        $this->expireIfNeeded($order);
        $this->syncStripeIfPaid($order);
        $order->refresh();

        return [
            'order_code' => $order->order_code,
            'status' => $order->status,
            'plan' => $order->plan,
            'method' => $order->method,
            'amount_vnd' => $order->amount_vnd,
            'amount_usd_cents' => $order->amount_usd_cents,
            'transfer_content' => $order->transfer_content,
            'qr_image_url' => $order->qr_image_url,
            'stripe_url' => $order->method === 'stripe' && $order->status === BillingOrder::PENDING
                ? $this->stripeUrl($order)
                : null,
            'bank' => $order->method === 'bank' ? [
                'bank_id' => (string) config('services.vietqr.bank_id'),
                'bank_name' => $this->bankName((string) config('services.vietqr.bank_id')),
                'account_no' => (string) config('services.vietqr.account_no'),
                'account_name' => (string) config('services.vietqr.account_name'),
            ] : null,
            'expires_at' => $order->expires_at?->toIso8601String(),
            'paid_at' => $order->paid_at?->toIso8601String(),
            'period_ends_at' => $order->period_ends_at?->toIso8601String(),
        ];
    }

    public function markPaid(BillingOrder $order, string $provider, string $txnId, ?string $description = null): void
    {
        if ($order->status === BillingOrder::PAID) {
            return;
        }

        DB::transaction(function () use ($order, $provider, $txnId, $description) {
            /** @var BillingOrder $locked */
            $locked = BillingOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === BillingOrder::PAID) {
                return;
            }

            $hotel = $this->hotelFor($locked);
            $from = $hotel->subscription_expires_at?->isFuture()
                ? $hotel->subscription_expires_at->copy()
                : now();
            $ends = $from->copy()->addMonth();

            $hotel->forceFill([
                'plan' => $locked->plan,
                'device_limit' => HotelPlan::deviceLimit($locked->plan),
                'subscription_expires_at' => $ends,
                'is_active' => true,
            ])->save();

            $locked->forceFill([
                'hotel_id' => $hotel->id,
                'status' => BillingOrder::PAID,
                'paid_at' => now(),
                'period_starts_at' => $from,
                'period_ends_at' => $ends,
            ])->save();

            BillingTransaction::query()->firstOrCreate(
                ['provider' => $provider, 'provider_txn_id' => $txnId],
                [
                    'billing_order_id' => $locked->id,
                    'amount' => $provider === 'stripe' ? $locked->amount_usd_cents : $locked->amount_vnd,
                    'currency' => $provider === 'stripe' ? 'USD' : 'VND',
                    'description' => $description,
                    'matched_at' => now(),
                ],
            );
        });

        $this->telegram->notifyPaid($order->fresh(['hotel']));
    }

    public function expireIfNeeded(BillingOrder $order, bool $notify = true): void
    {
        if ($order->status !== BillingOrder::PENDING) {
            return;
        }
        if (! $order->expires_at || $order->expires_at->isFuture()) {
            return;
        }

        $order->forceFill(['status' => BillingOrder::EXPIRED])->save();
        if ($notify) {
            $this->telegram->notifyExpired($order);
        }
    }

    private function syncStripeIfPaid(BillingOrder $order): void
    {
        if ($order->method !== 'stripe' || $order->status !== BillingOrder::PENDING || ! $order->stripe_session_id) {
            return;
        }
        $secret = (string) config('services.stripe.secret');
        if ($secret === '') {
            return;
        }
        $res = Http::withToken($secret)->timeout(15)->get('https://api.stripe.com/v1/checkout/sessions/'.$order->stripe_session_id);
        if ($res->successful() && ($res->json('payment_status') === 'paid')) {
            $this->markPaid($order, 'stripe', $order->stripe_session_id, 'stripe session');
        }
    }

    private function hotelFor(BillingOrder $order): Hotel
    {
        if ($order->hotel_id) {
            return Hotel::query()->lockForUpdate()->findOrFail($order->hotel_id);
        }

        $user = User::query()->where('email', $order->email)->first();
        $hotel = $user?->hotels()->first();
        if ($hotel) {
            return Hotel::query()->lockForUpdate()->findOrFail($hotel->id);
        }

        throw ValidationException::withMessages(['email' => 'Chưa có khách sạn cho email này.']);
    }

    private function stripeUrl(BillingOrder $order): ?string
    {
        if (is_string($order->stripe_url) && $order->stripe_url !== '') {
            return $order->stripe_url;
        }
        if (! $order->stripe_session_id) {
            return null;
        }

        return 'https://checkout.stripe.com/c/pay/'.$order->stripe_session_id;
    }

    private function bankName(string $bankId): string
    {
        return match (strtoupper($bankId)) {
            'TPB', 'TPBANK' => 'TPBank',
            default => $bankId,
        };
    }
}
