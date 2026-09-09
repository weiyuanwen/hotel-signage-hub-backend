<?php

namespace App\Domains\Content;

enum WelcomeTemplateKey: string
{
    case Dusk = 'dusk';
    case Linen = 'linen';
    case Harbor = 'harbor';
    case Garden = 'garden';
    case Stone = 'stone';

    public function builtInLabel(): string
    {
        return match ($this) {
            self::Dusk => 'Đêm vàng',
            self::Linen => 'Sáng nhẹ',
            self::Harbor => 'Cảng đêm',
            self::Garden => 'Vườn trà',
            self::Stone => 'Đá ấm',
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
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
