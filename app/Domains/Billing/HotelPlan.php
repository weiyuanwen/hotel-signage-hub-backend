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
            self::FREE => 1,
            self::STANDARD => 3,
            self::PREMIUM => null,
            default => 1,
        };
    }

    /**
     * @return list<'pin'|'link'>
     */
    public static function pairingMethods(string $plan): array
    {
        return $plan === self::PREMIUM ? ['pin', 'link'] : ['pin'];
    }

    public static function pairingMode(string $plan): string
    {
        return in_array('link', self::pairingMethods($plan), true) ? 'link' : 'pin';
    }

    public static function isPaid(string $plan): bool
    {
        return in_array($plan, [self::STANDARD, self::PREMIUM], true);
    }

    public static function amountVnd(string $plan): int
    {
        return match ($plan) {
            self::STANDARD => 15000,
            self::PREMIUM => 50000,
            default => 0,
        };
    }

    public static function amountUsdCents(string $plan): int
    {
        return match ($plan) {
            self::STANDARD => 300,
            self::PREMIUM => 500,
            default => 0,
        };
    }

    public static function allowsPairingLinks(string $plan): bool
    {
        return in_array('link', self::pairingMethods($plan), true);
    }

    /**
     * @return list<string>
     */
    public static function featureLines(string $plan): array
    {
        return match ($plan) {
            self::STANDARD => [
                'Tối đa 3 TV',
                'Ghép bằng mã PIN trên màn hình',
                'Nền phòng: ảnh, MP4, YouTube/Vimeo',
            ],
            self::PREMIUM => [
                'Không giới hạn số TV',
                'Ghép bằng mã PIN hoặc link — cả hai đều dùng được',
                'Nền phòng: ảnh, MP4, YouTube/Vimeo',
            ],
            default => [
                '1 TV',
                'Ghép bằng mã PIN trên màn hình',
                'Không hết hạn',
            ],
        };
    }

    public static function pairingLabel(string $plan): string
    {
        return self::allowsPairingLinks($plan)
            ? 'Ghép TV bằng mã PIN hoặc link. Cả hai cách đều dùng được.'
            : 'TV hiện mã PIN. Nhập mã đó ở trang Phòng để ghép.';
    }

    public static function allowsDeviceBackgrounds(string $plan): bool
    {
        return in_array($plan, [self::STANDARD, self::PREMIUM], true);
    }

    public static function label(string $plan): string
    {
        return match ($plan) {
            self::TRIAL => 'Thử 1 TV',
            self::FREE => 'Miễn phí 1 TV',
            self::STANDARD => '3 TV',
            self::PREMIUM => 'Nhiều TV',
            default => $plan,
        };
    }
}
