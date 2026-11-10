<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\JobStatus;
use App\Enums\UserRole;
use App\Models\ProductionJob;
use App\Models\User;

class ProductionJobPolicy
{
    /**
     * Every authenticated role may list jobs. What an installer actually sees
     * is narrowed by the visibleTo query scope.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProductionJob $job): bool
    {
        return $user->role->canManageProduction() || $job->installer_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->role->canManageProduction();
    }

    /**
     * Role x transition matrix from docs/design.md. Whether the transition itself
     * is valid is decided by the JobStatus enum, not here.
     */
    public function transition(User $user, ProductionJob $job, JobStatus $target): bool
    {
        return match ($user->role) {
            UserRole::Admin, UserRole::Planner => true,
            UserRole::Installer => $job->installer_id === $user->id && $target === JobStatus::Installed,
        };
    }
}
