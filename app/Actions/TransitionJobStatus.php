<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\JobStatus;
use App\Exceptions\InvalidStatusTransition;
use App\Models\ProductionJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TransitionJobStatus
{
    /**
     * Moves a job to the target status and records who did it.
     *
     * The row is locked first so that the status we validate against is the one we
     * overwrite. The status change and its history row share one transaction.
     *
     * @throws InvalidStatusTransition
     */
    public function handle(ProductionJob $job, JobStatus $to, User $actor): ProductionJob
    {
        return DB::transaction(function () use ($job, $to, $actor): ProductionJob {
            $locked = ProductionJob::query()
                ->whereKey($job->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $from = $locked->status;

            if (! $from->canTransitionTo($to)) {
                throw new InvalidStatusTransition($from, $to);
            }

            // Status is not mass assignable, so it is set explicitly.
            $locked->status = $to;
            $locked->save();

            $locked->histories()->create([
                'changed_by' => $actor->id,
                'from_status' => $from,
                'to_status' => $to,
            ]);

            return $locked;
        });
    }
}
