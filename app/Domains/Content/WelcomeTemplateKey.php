<?php

namespace App\Domains\Content;

enum WelcomeTemplateKey: string
{
    case Dusk = 'dusk';
    case Linen = 'linen';
    case Harbor = 'harbor';
    case Garden = 'garden';
    case Stone = 'stone';
    case Vista = 'vista';

    public function builtInLabel(): string
    {
        return match ($this) {
            self::Dusk => 'Đêm vàng',
            self::Linen => 'Sáng nhẹ',
            self::Harbor => 'Cảng đêm',
            self::Garden => 'Vườn trà',
            self::Stone => 'Đá ấm',
            self::Vista => 'Tầm nhìn',
        };
    }

    public function sortOrder(): int
    {
        return match ($this) {
            self::Dusk => 1,
            self::Linen => 2,
            self::Harbor => 3,
            self::Garden => 4,
            self::Stone => 5,
            self::Vista => 6,
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
