<?php

declare(strict_types=1);

namespace App\Enums;

enum JobStatus: string
{
    case Pending = 'pending';
    case Cutting = 'cutting';
    case EdgeBanding = 'edge_banding';
    case Assembly = 'assembly';
    case ReadyForInstall = 'ready_for_install';
    case Installed = 'installed';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Cutting],
            self::Cutting => [self::EdgeBanding],
            self::EdgeBanding => [self::Assembly],
            self::Assembly => [self::ReadyForInstall],
            self::ReadyForInstall => [self::Installed],
            self::Installed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
