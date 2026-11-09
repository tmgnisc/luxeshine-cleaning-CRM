<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Cleaner = 'cleaner';
    case Customer = 'customer';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $role): string => $role->value, self::cases());
    }

    public function isAdministrative(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Admin], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::Cleaner => 'Cleaner',
            self::Customer => 'Customer',
        };
    }
}
