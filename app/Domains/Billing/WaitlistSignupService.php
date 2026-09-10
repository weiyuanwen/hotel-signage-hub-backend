<?php

namespace App\Domains\Billing;

use App\Mail\WaitlistAlreadyRegisteredMail;
use App\Mail\WaitlistCredentialsMail;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class WaitlistSignupService
{
    /**
     * @return array{status: 'created'|'existing'}
     */
    public function register(string $email, ?string $hotelName, string $plan = HotelPlan::FREE): array
    {
        $email = Str::lower(trim($email));
        $plan = in_array($plan, HotelPlan::keys(), true) ? $plan : HotelPlan::FREE;

        $existing = User::query()->where('email', $email)->first();

        if ($existing) {
            Mail::to($existing->email)->send(new WaitlistAlreadyRegisteredMail($existing));

            return ['status' => 'existing'];
        }

        $plainPassword = Str::password(12, symbols: false);
        $displayName = $this->nameFromEmail($email);
        $name = $hotelName !== null && trim($hotelName) !== ''
            ? trim($hotelName)
            : 'Khách sạn '.$displayName;

        DB::transaction(function () use ($email, $plainPassword, $displayName, $name, $plan) {
            $hotel = Hotel::query()->create([
                'name' => $name,
                'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
                'timezone' => 'Asia/Ho_Chi_Minh',
                'default_locale' => 'vi',
                'weather_region' => 'ho-chi-minh',
                'is_active' => true,
                'plan' => $plan,
                'device_limit' => HotelPlan::deviceLimit($plan),
            ]);

            $user = User::query()->create([
                'name' => $displayName,
                'email' => $email,
                'password' => $plainPassword,
                'is_active' => true,
            ]);
            $user->assignRole('hotel-manager');
            $user->hotels()->attach($hotel->id, ['is_primary' => true]);

            Mail::to($user->email)->send(new WaitlistCredentialsMail($user, $hotel, $plainPassword));
        });

        return ['status' => 'created'];
    }

    private function nameFromEmail(string $email): string
    {
        $local = Str::before($email, '@');
        $clean = str_replace(['.', '_', '-'], ' ', $local);
        $title = Str::title(trim($clean));

        return $title !== '' ? $title : 'Quản lý';
    }
}
