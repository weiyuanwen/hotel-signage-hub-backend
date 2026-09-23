<?php

namespace Tests\Feature;

use App\Domains\Billing\HotelPlan;
use App\Models\BillingOrder;
use App\Models\BillingTransaction;
use App\Models\Device;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class BillingCheckoutTest extends TestCase
{
    use CreatesStaff;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.vietqr.bank_id' => 'TPB',
            'services.vietqr.account_no' => '03738073001',
            'services.vietqr.account_name' => 'NGUYEN VAN DUY',
            'services.vietqr.template' => 'compact2',
            'services.telegram.bot_token' => 'tg-test-token',
            'services.telegram.chat_id' => '12345',
            'services.stripe.secret' => 'sk_test_dummy',
            'services.stripe.key' => 'pk_test_dummy',
            'services.billing.cms_url' => 'https://signagehub.online',
        ]);
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_123',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_123#fidn_test',
                'client_secret' => 'cs_test_123_secret',
            ], 200),
        ]);
    }

    public function test_waitlist_always_provisions_one_free_tv(): void
    {
        $this->postJson('/api/cms/waitlist', [
            'email' => 'free@hotel.test',
            'hotel_name' => 'Nhà khách Sông Hàn',
            'plan' => HotelPlan::PREMIUM,
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'created')
            ->assertJsonPath('plan', HotelPlan::PREMIUM);

        $hotel = User::query()->where('email', 'free@hotel.test')->first()?->hotels()->first();
        $this->assertSame(HotelPlan::FREE, $hotel?->plan);
        $this->assertSame(1, $hotel?->device_limit);
        $this->assertNull($hotel?->subscription_expires_at);
        $this->assertTrue($hotel->subscriptionActive());
    }

    public function test_bank_checkout_creates_vietqr_order_and_transfer_content(): void
    {
        $this->postJson('/api/cms/waitlist', [
            'email' => 'bank@hotel.test',
            'hotel_name' => 'Bayside',
        ])->assertCreated();

        $res = $this->postJson('/api/cms/billing/checkout', [
            'email' => 'bank@hotel.test',
            'plan' => HotelPlan::STANDARD,
            'method' => 'bank',
            'locale' => 'vi',
        ])->assertCreated();

        $code = $res->json('order_code');
        $this->assertMatchesRegularExpression('/^SHUB[A-Z0-9]{10}$/', $code);
        $this->assertSame(15000, $res->json('amount_vnd'));
        $this->assertStringContainsString('img.vietqr.io/image/TPB-03738073001-compact2.png', (string) $res->json('qr_image_url'));
        $this->assertStringContainsString('addInfo='.$code, (string) $res->json('qr_image_url'));
        $this->assertSame($code, $res->json('transfer_content'));
        $this->assertSame('pending', $res->json('status'));
        $this->assertSame('TPB', $res->json('bank.bank_id'));
        $this->assertSame('TPBank', $res->json('bank.bank_name'));
        $this->assertSame('03738073001', $res->json('bank.account_no'));
        $this->assertSame('NGUYEN VAN DUY', $res->json('bank.account_name'));
        $this->assertStringContainsString('accountName=NGUYEN+VAN+DUY', (string) $res->json('qr_image_url'));

        $this->assertDatabaseHas('billing_orders', [
            'order_code' => $code,
            'method' => 'bank',
            'plan' => HotelPlan::STANDARD,
            'status' => 'pending',
            'amount_vnd' => 15000,
        ]);
    }

    public function test_billing_poll_is_not_blocked_after_waitlist_throttle(): void
    {
        $this->postJson('/api/cms/waitlist', ['email' => 'poll-limit@hotel.test'])->assertCreated();
        $code = $this->postJson('/api/cms/billing/checkout', [
            'email' => 'poll-limit@hotel.test',
            'plan' => HotelPlan::STANDARD,
            'method' => 'bank',
        ])->assertCreated()->json('order_code');

        $blocked = false;
        for ($i = 0; $i < 16; $i++) {
            $status = $this->postJson('/api/cms/waitlist', ['email' => "limit{$i}@hotel.test"])->status();
            if ($status === 429) {
                $blocked = true;
                break;
            }
            $this->assertContains($status, [201, 409]);
        }
        $this->assertTrue($blocked, 'Waitlist should eventually 429 so billing poll can be tested separately.');

        $this->getJson('/api/cms/billing/orders/'.$code)->assertOk()->assertJsonPath('order_code', $code);
        $this->postJson('/api/cms/billing/checkout', [
            'email' => 'poll-limit@hotel.test',
            'plan' => HotelPlan::PREMIUM,
            'method' => 'bank',
        ])->assertCreated();
    }

    public function test_stripe_checkout_is_disabled(): void
    {
        $this->postJson('/api/cms/waitlist', ['email' => 'card-off@hotel.test'])->assertCreated();

        $this->postJson('/api/cms/billing/checkout', [
            'email' => 'card-off@hotel.test',
            'plan' => HotelPlan::STANDARD,
            'method' => 'stripe',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['method']);
    }

    public function test_matching_bank_credit_marks_paid_extends_month_and_notifies_telegram(): void
    {
        $this->postJson('/api/cms/waitlist', [
            'email' => 'paid@hotel.test',
            'hotel_name' => 'Pearl',
        ])->assertCreated();

        $code = $this->postJson('/api/cms/billing/checkout', [
            'email' => 'paid@hotel.test',
            'plan' => HotelPlan::STANDARD,
            'method' => 'bank',
        ])->json('order_code');

        $this->artisan('billing:poll-bank', [
            '--transactions' => json_encode([
                [
                    'id' => 'txn-1',
                    'creditDebitIndicator' => 'CRDT',
                    'amount' => 15000,
                    'description' => 'IBFT'.$code.' chuyen tien',
                ],
            ]),
        ])->assertSuccessful();

        $order = BillingOrder::query()->where('order_code', $code)->first();
        $this->assertSame('paid', $order?->status);
        $this->assertNotNull($order?->paid_at);
        $this->assertTrue($order->period_ends_at->greaterThan(now()->addDays(27)));

        $hotel = $order->hotel;
        $this->assertSame(HotelPlan::STANDARD, $hotel->plan);
        $this->assertSame(3, $hotel->device_limit);
        $this->assertTrue($hotel->subscription_expires_at->greaterThan(now()->addDays(27)));

        $this->assertDatabaseHas('billing_transactions', [
            'provider' => 'bank',
            'provider_txn_id' => 'txn-1',
            'amount' => 15000,
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.telegram.org')
            && str_contains((string) $request['text'], $code)
            && str_contains((string) $request['text'], 'thành công'));
    }

    public function test_wrong_bank_amount_does_not_pay(): void
    {
        $this->postJson('/api/cms/waitlist', ['email' => 'wrong@hotel.test'])->assertCreated();
        $code = $this->postJson('/api/cms/billing/checkout', [
            'email' => 'wrong@hotel.test',
            'plan' => HotelPlan::STANDARD,
            'method' => 'bank',
        ])->json('order_code');

        $this->artisan('billing:poll-bank', [
            '--transactions' => json_encode([
                [
                    'id' => 'txn-bad',
                    'creditDebitIndicator' => 'CRDT',
                    'amount' => 14000,
                    'description' => $code,
                ],
            ]),
        ])->assertSuccessful();

        $this->assertSame('pending', BillingOrder::query()->where('order_code', $code)->value('status'));
        $this->assertSame(0, BillingTransaction::query()->count());
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.telegram.org'));
    }

    public function test_stripe_checkout_returns_hosted_url_and_webhook_pays_premium(): void
    {
        config(['services.stripe.enabled' => true]);
        $this->postJson('/api/cms/waitlist', ['email' => 'card@hotel.test', 'hotel_name' => 'Harbor'])->assertCreated();

        $res = $this->postJson('/api/cms/billing/checkout', [
            'email' => 'card@hotel.test',
            'plan' => HotelPlan::PREMIUM,
            'method' => 'stripe',
            'locale' => 'en',
        ])->assertCreated();

        $this->assertSame('https://checkout.stripe.com/c/pay/cs_test_123#fidn_test', $res->json('stripe_url'));
        $this->assertSame(500, $res->json('amount_usd_cents'));
        $code = $res->json('order_code');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.stripe.com/v1/checkout/sessions')
            && str_contains(urldecode($request->body()), 'join?paid='.$code));
        $this->getJson('/api/cms/billing/orders/'.$code)
            ->assertOk()
            ->assertJsonPath('stripe_url', 'https://checkout.stripe.com/c/pay/cs_test_123#fidn_test');

        $this->postJson('/api/cms/billing/stripe/webhook', [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_123',
                    'payment_status' => 'paid',
                    'client_reference_id' => $code,
                    'metadata' => ['order_code' => $code],
                ],
            ],
        ])->assertOk();

        $hotel = User::query()->where('email', 'card@hotel.test')->first()?->hotels()->first();
        $this->assertSame(HotelPlan::PREMIUM, $hotel?->plan);
        $this->assertNull($hotel?->device_limit);
        $this->assertTrue($hotel->subscriptionActive());
        $this->assertSame('paid', BillingOrder::query()->where('order_code', $code)->value('status'));
    }

    public function test_stripe_order_show_syncs_paid_session_without_webhook(): void
    {
        config(['services.stripe.enabled' => true]);
        $this->postJson('/api/cms/waitlist', ['email' => 'poll@hotel.test'])->assertCreated();
        $created = $this->postJson('/api/cms/billing/checkout', [
            'email' => 'poll@hotel.test',
            'plan' => HotelPlan::STANDARD,
            'method' => 'stripe',
        ])->assertCreated();
        $code = $created->json('order_code');
        $this->assertNotEmpty(BillingOrder::query()->where('order_code', $code)->value('stripe_session_id'));

        Http::fake([
            'api.stripe.com/v1/checkout/sessions/*' => Http::response([
                'id' => 'cs_test_123',
                'payment_status' => 'paid',
            ], 200),
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $this->getJson('/api/cms/billing/orders/'.$code)
            ->assertOk()
            ->assertJsonPath('status', 'paid');
    }

    public function test_renewal_extends_from_current_expiry(): void
    {
        $this->postJson('/api/cms/waitlist', ['email' => 'renew@hotel.test'])->assertCreated();
        $hotel = User::query()->where('email', 'renew@hotel.test')->first()?->hotels()->first();
        $hotel->forceFill([
            'plan' => HotelPlan::STANDARD,
            'device_limit' => 3,
            'subscription_expires_at' => now()->addDays(10),
        ])->save();

        $code = $this->postJson('/api/cms/billing/checkout', [
            'email' => 'renew@hotel.test',
            'plan' => HotelPlan::STANDARD,
            'method' => 'bank',
        ])->json('order_code');

        $this->artisan('billing:poll-bank', [
            '--transactions' => json_encode([
                ['id' => 'txn-r', 'creditDebitIndicator' => 'CRDT', 'amount' => 15000, 'description' => $code],
            ]),
        ]);

        $hotel->refresh();
        $this->assertTrue($hotel->subscription_expires_at->greaterThan(now()->addDays(38)));
        $this->assertTrue($hotel->subscription_expires_at->lessThan(now()->addDays(42)));
    }

    public function test_expired_paid_hotel_stops_screen_and_pairing(): void
    {
        $hotel = Hotel::factory()->create([
            'plan' => HotelPlan::STANDARD,
            'device_limit' => 3,
            'subscription_expires_at' => now()->subMinute(),
        ]);
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        $device = Device::factory()->paired($hotel, $room)->create();
        $this->assertFalse($hotel->subscriptionActive());

        Sanctum::actingAs($device, ['device']);
        $this->getJson('/api/device/screen')->assertForbidden();

        $staff = $this->staff('receptionist', $hotel);
        $code = $this->postJson('/api/device/pairing-codes', ['name' => 'TV mới'])->json('code');
        Sanctum::actingAs($staff);
        $this->postJson("/api/cms/hotels/{$hotel->id}/pairing-codes/claim", [
            'code' => $code,
            'room_id' => $room->id,
        ])->assertUnprocessable();
    }

    public function test_free_hotel_keeps_working_without_expiry(): void
    {
        $hotel = Hotel::factory()->create(['plan' => HotelPlan::FREE, 'device_limit' => 1, 'subscription_expires_at' => null]);
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        $device = Device::factory()->paired($hotel, $room)->create();

        Sanctum::actingAs($device, ['device']);
        $this->getJson('/api/device/screen')->assertOk();
    }

    public function test_expired_paid_hotel_downgrades_to_free_and_stops_extra_tvs(): void
    {
        $hotel = Hotel::factory()->create([
            'plan' => HotelPlan::STANDARD,
            'device_limit' => 3,
            'subscription_expires_at' => now()->subMinute(),
        ]);
        $firstRoom = Room::factory()->create(['hotel_id' => $hotel->id]);
        $secondRoom = Room::factory()->create(['hotel_id' => $hotel->id]);
        $first = Device::factory()->paired($hotel, $firstRoom)->create();
        $extra = Device::factory()->paired($hotel, $secondRoom)->create();

        $this->artisan('billing:expire-hotels')->assertSuccessful();

        $hotel->refresh();
        $this->assertSame(HotelPlan::FREE, $hotel->plan);
        $this->assertSame(1, $hotel->device_limit);

        Sanctum::actingAs($first, ['device']);
        $this->getJson('/api/device/screen')->assertOk();

        Sanctum::actingAs($extra, ['device']);
        $this->getJson('/api/device/screen')->assertForbidden();
    }
}
