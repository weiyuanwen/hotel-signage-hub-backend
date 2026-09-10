<?php

namespace Tests\Feature;

use App\Domains\Billing\HotelPlan;
use App\Mail\WaitlistAlreadyRegisteredMail;
use App\Mail\WaitlistCredentialsMail;
use App\Models\Device;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class WaitlistAndBillingTest extends TestCase
{
    use CreatesStaff;

    public function test_waitlist_creates_free_hotel_and_mails_credentials(): void
    {
        Mail::fake();

        $this->postJson('/api/cms/waitlist', [
            'email' => 'le.tan@khachsan.vn',
            'hotel_name' => 'Nhà khách Sông Hàn',
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'created')
            ->assertJsonMissing(['password']);

        $user = User::query()->where('email', 'le.tan@khachsan.vn')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('hotel-manager'));

        $hotel = $user->hotels()->first();
        $this->assertNotNull($hotel);
        $this->assertSame('Nhà khách Sông Hàn', $hotel->name);
        $this->assertSame(HotelPlan::FREE, $hotel->plan);
        $this->assertSame(3, $hotel->device_limit);
        $this->assertSame('pin', $hotel->pairingMode());

        Mail::assertSent(WaitlistCredentialsMail::class, function (WaitlistCredentialsMail $mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->plainPassword !== '';
        });
    }

    public function test_waitlist_standard_plan_gets_link_pairing_and_twenty_devices(): void
    {
        Mail::fake();

        $this->postJson('/api/cms/waitlist', [
            'email' => 'quanly@bayside.test',
            'plan' => HotelPlan::STANDARD,
        ])->assertCreated();

        $hotel = User::query()->where('email', 'quanly@bayside.test')->first()?->hotels()->first();
        $this->assertSame(HotelPlan::STANDARD, $hotel?->plan);
        $this->assertSame(20, $hotel?->device_limit);
        $this->assertSame('link', $hotel?->pairingMode());
    }

    public function test_existing_email_gets_reminder_instead_of_a_second_hotel(): void
    {
        Mail::fake();
        $hotel = Hotel::factory()->create();
        $this->staff('hotel-manager', $hotel, true);
        $user = User::factory()->create(['email' => 'da.co@khachsan.vn']);
        $user->assignRole('hotel-manager');
        $user->hotels()->attach($hotel->id, ['is_primary' => true]);

        $before = Hotel::query()->count();

        $this->postJson('/api/cms/waitlist', ['email' => 'da.co@khachsan.vn'])
            ->assertStatus(409)
            ->assertJsonPath('status', 'existing');

        $this->assertSame($before, Hotel::query()->count());
        Mail::assertSent(WaitlistAlreadyRegisteredMail::class, fn (WaitlistAlreadyRegisteredMail $mail) => $mail->hasTo($user->email));
        Mail::assertNotSent(WaitlistCredentialsMail::class);
    }

    public function test_trial_hotel_rejects_a_second_paired_tv(): void
    {
        $hotel = Hotel::factory()->create(['plan' => HotelPlan::TRIAL, 'device_limit' => 1]);
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        $staff = $this->staff('receptionist', $hotel);
        Device::factory()->paired($hotel, $room)->create();

        $code = $this->postJson('/api/device/pairing-codes', ['name' => 'TV 2'])->json('code');

        Sanctum::actingAs($staff);
        $this->postJson("/api/cms/hotels/{$hotel->id}/pairing-codes/claim", [
            'code' => $code,
            'room_id' => $room->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);

        $this->assertSame(1, $hotel->fresh()->pairedDeviceCount());
    }

    public function test_standard_plan_issues_a_link_and_tv_consumes_it(): void
    {
        $hotel = Hotel::factory()->create(['plan' => HotelPlan::STANDARD, 'device_limit' => 20]);
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        $staff = $this->staff('hotel-manager', $hotel);

        Sanctum::actingAs($staff);
        $url = $this->postJson("/api/cms/hotels/{$hotel->id}/pairing-links", [
            'room_id' => $room->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.room_id', $room->id)
            ->json('data.url');

        $this->assertStringContainsString('pair=', $url);
        $token = (string) parse_url($url, PHP_URL_QUERY);
        parse_str($token, $query);
        $pair = $query['pair'] ?? '';

        $this->postJson("/api/device/pairing-links/{$pair}")
            ->assertOk()
            ->assertJsonPath('status', 'paired')
            ->assertJsonPath('hotel_id', $hotel->id)
            ->assertJsonPath('room_id', $room->id)
            ->assertJsonStructure(['token']);

        $this->assertSame(1, $hotel->fresh()->pairedDeviceCount());

        $this->postJson("/api/device/pairing-links/{$pair}")
            ->assertUnprocessable();
    }

    public function test_free_plan_cannot_create_pairing_links(): void
    {
        $hotel = Hotel::factory()->create(['plan' => HotelPlan::FREE, 'device_limit' => 3]);
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        Sanctum::actingAs($this->staff('hotel-manager', $hotel));

        $this->postJson("/api/cms/hotels/{$hotel->id}/pairing-links", [
            'room_id' => $room->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['plan']);
    }

    public function test_hotel_index_includes_plan_quota(): void
    {
        $hotel = Hotel::factory()->create(['plan' => HotelPlan::FREE, 'device_limit' => 3]);
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        Device::factory()->paired($hotel, $room)->create();
        Sanctum::actingAs($this->staff('hotel-manager', $hotel));

        $this->getJson('/api/cms/hotels')
            ->assertOk()
            ->assertJsonPath('data.0.plan', HotelPlan::FREE)
            ->assertJsonPath('data.0.device_limit', 3)
            ->assertJsonPath('data.0.pairing_mode', 'pin')
            ->assertJsonPath('data.0.paired_device_count', 1)
            ->assertJsonPath('data.0.plan_label', 'Miễn phí mãi');
    }
}
