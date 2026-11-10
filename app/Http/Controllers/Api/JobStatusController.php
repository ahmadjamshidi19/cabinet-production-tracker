<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\TransitionJobStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateJobStatusRequest;
use App\Http\Resources\ProductionJobResource;
use App\Models\ProductionJob;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Facades\Gate;

class JobStatusController extends Controller
{
    public function __invoke(
        UpdateJobStatusRequest $request,
        ProductionJob $job,
        TransitionJobStatus $transition,
        #[CurrentUser] User $user,
    ): ProductionJobResource {
        $target = $request->targetStatus();

        Gate::authorize('transition', [$job, $target]);

        return ProductionJobResource::make($transition->handle($job, $target, $user));
    }
}
