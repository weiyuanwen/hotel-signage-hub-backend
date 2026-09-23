<?php

namespace Tests\Feature;

use App\Domains\Billing\HotelPlan;
use App\Models\BillingOrder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PendingBankPollerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.histbank.url' => 'http://histbank.test',
            'services.vietqr.bank_id' => 'TPB',
            'services.vietqr.account_no' => '03738073001',
            'services.vietqr.account_name' => 'NGUYEN VAN DUY',
            'services.telegram.bot_token' => '',
            'services.telegram.chat_id' => '',
        ]);
        Cache::flush();
    }

    public function test_idle_does_not_call_histbank(): void
    {
        Http::fake();
        $this->artisan('billing:poll-bank')->assertSuccessful();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'histbank.test'));
    }

    public function test_initial_delay_does_not_call_histbank(): void
    {
        Http::fake();
        $this->pendingBankOrder(now());

        $this->artisan('billing:poll-bank')->assertSuccessful();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'histbank.test'));
    }

    public function test_order_status_poll_does_not_call_histbank(): void
    {
        Http::fake();
        $code = $this->pendingBankOrder(now()->subMinutes(3));

        $this->getJson('/api/cms/billing/orders/'.$code)->assertOk();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'histbank.test'));
    }

    public function test_expired_pending_orders_do_not_call_histbank(): void
    {
        Http::fake();
        $code = $this->pendingBankOrder(now()->subMinutes(20));
        BillingOrder::query()->where('order_code', $code)->update([
            'expires_at' => now()->subMinutes(8),
        ]);

        $this->artisan('billing:poll-bank')->assertSuccessful();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'histbank.test'));
        $this->assertSame(BillingOrder::EXPIRED, BillingOrder::query()->where('order_code', $code)->value('status'));
    }

    public function test_recent_poll_is_throttled(): void
    {
        Http::fake();
        $this->pendingBankOrder(now()->subMinutes(3));
        Cache::put('histbank:last_poll_at', now()->subSeconds(12)->toIso8601String());

        $this->artisan('billing:poll-bank')->assertSuccessful();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'histbank.test'));
    }

    public function test_fetches_histbank_once_for_all_pending_orders(): void
    {
        Http::fake([
            'http://histbank.test/*' => Http::response(['transactionInfos' => []], 200),
        ]);
        $this->pendingBankOrder(now()->subMinutes(3));
        $this->pendingBankOrder(now()->subMinutes(2), 'second@hotel.test');

        $this->artisan('billing:poll-bank')->assertSuccessful();
        $this->artisan('billing:poll-bank')->assertSuccessful();

        Http::assertSentCount(1);
    }

    public function test_force_bypasses_throttle(): void
    {
        Http::fake([
            'http://histbank.test/*' => Http::response(['transactionInfos' => []], 200),
        ]);
        $this->pendingBankOrder(now()->subMinutes(3));
        Cache::put('histbank:last_poll_at', now()->subSeconds(5)->toIso8601String());

        $this->artisan('billing:poll-bank', ['--force' => true])->assertSuccessful();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'histbank.test'));
    }

    public function test_backoff_after_rate_limit_skips_next_poll(): void
    {
        Http::fake([
            'http://histbank.test/*' => Http::response('rate limited', 429),
        ]);
        $this->pendingBankOrder(now()->subMinutes(3));

        $this->artisan('billing:poll-bank')->assertSuccessful();
        Cache::put('histbank:last_poll_at', now()->subMinutes(5)->toIso8601String());
        $this->artisan('billing:poll-bank')->assertSuccessful();

        Http::assertSentCount(1);
        $this->assertNotEmpty(Cache::get('histbank:backoff_until'));
    }

    public function test_injected_transactions_still_match_without_histbank(): void
    {
        Http::fake();
        $code = $this->pendingBankOrder(now()->subMinutes(3));

        $this->artisan('billing:poll-bank', [
            '--transactions' => json_encode([
                [
                    'id' => 'txn-throttle',
                    'creditDebitIndicator' => 'CRDT',
                    'amount' => 15000,
                    'description' => $code,
                ],
            ]),
        ])->assertSuccessful();

        $this->assertSame('paid', BillingOrder::query()->where('order_code', $code)->value('status'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'histbank.test'));
    }

    private function pendingBankOrder(\DateTimeInterface $createdAt, string $email = 'bank-poll@hotel.test'): string
    {
        $this->postJson('/api/cms/waitlist', [
            'email' => $email,
            'hotel_name' => 'Poll Desk',
        ]);

        $code = $this->postJson('/api/cms/billing/checkout', [
            'email' => $email,
            'plan' => HotelPlan::STANDARD,
            'method' => 'bank',
        ])->json('order_code');

        BillingOrder::query()->where('order_code', $code)->update([
            'created_at' => $createdAt,
        ]);

        return $code;
    }
}
