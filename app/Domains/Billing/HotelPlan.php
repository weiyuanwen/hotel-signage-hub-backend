<?php

namespace App\Domains\Billing;

class HotelPlan
{
    public const TRIAL = 'trial';

    public const FREE = 'free';

    public const STANDARD = 'standard';

    public const PREMIUM = 'premium';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [self::TRIAL, self::FREE, self::STANDARD, self::PREMIUM];
    }

    public static function deviceLimit(string $plan): ?int
    {
        return match ($plan) {
            self::TRIAL => 1,
            self::FREE => 3,
            self::STANDARD => 20,
            self::PREMIUM => null,
            default => 3,
        };
    }

    public static function pairingMode(string $plan): string
    {
        return in_array($plan, [self::STANDARD, self::PREMIUM], true) ? 'link' : 'pin';
    }

    public static function allowsPairingLinks(string $plan): bool
    {
        return self::pairingMode($plan) === 'link';
    }

    public static function allowsDeviceBackgrounds(string $plan): bool
    {
        return in_array($plan, [self::STANDARD, self::PREMIUM], true);
    }

    public static function label(string $plan): string
    {
        return match ($plan) {
            self::TRIAL => 'Thử 1 TV',
            self::FREE => 'Miễn phí mãi',
            self::STANDARD => 'Plus',
            self::PREMIUM => 'Cao cấp',
            default => $plan,
        };
    }
}
