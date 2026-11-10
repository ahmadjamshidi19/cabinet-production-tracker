<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Planner = 'planner';
    case Installer = 'installer';

    public function canManageProduction(): bool
    {
        return match ($this) {
            self::Admin, self::Planner => true,
            self::Installer => false,
        };
    }
}
