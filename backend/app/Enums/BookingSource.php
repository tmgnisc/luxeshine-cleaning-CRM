<?php

namespace App\Enums;

enum BookingSource: string
{
    case Website = 'website';
    case Admin = 'admin';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $source): string => $source->value, self::cases());
    }
}
