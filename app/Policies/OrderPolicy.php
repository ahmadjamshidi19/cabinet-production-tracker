<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canManageProduction();
    }

    public function view(User $user, Order $order): bool
    {
        return $user->role->canManageProduction();
    }

    public function create(User $user): bool
    {
        return $user->role->canManageProduction();
    }
}
