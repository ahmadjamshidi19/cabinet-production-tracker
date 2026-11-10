<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListJobsRequest;
use App\Http\Resources\ProductionJobResource;
use App\Models\ProductionJob;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ProductionJobController extends Controller
{
    public function index(ListJobsRequest $request, #[CurrentUser] User $user): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', ProductionJob::class);

        $jobs = ProductionJob::query()
            ->visibleTo($user)
            ->ofStatus($request->status())
            ->with('order:id,customer_name,address')
            ->orderByDesc('id')
            ->paginate($request->perPage());

        return ProductionJobResource::collection($jobs);
    }
}
