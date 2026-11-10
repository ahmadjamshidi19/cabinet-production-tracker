<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Support\Collection;

enum OrderStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Installed = 'installed';

    /**
     * Derives the order status from its job statuses (see docs/design.md, section 5.1).
     * An order without jobs counts as pending.
     *
     * @param  Collection<int, JobStatus>  $statuses
     */
    public static function fromJobStatuses(Collection $statuses): self
    {
        if ($statuses->every(fn (JobStatus $status): bool => $status === JobStatus::Pending)) {
            return self::Pending;
        }

        if ($statuses->every(fn (JobStatus $status): bool => $status === JobStatus::Installed)) {
            return self::Installed;
        }

        return self::InProgress;
    }
}
