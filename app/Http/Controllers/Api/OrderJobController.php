<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListJobsRequest;
use App\Http\Requests\StoreJobRequest;
use App\Http\Resources\ProductionJobResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class OrderJobController extends Controller
{
    public function index(ListJobsRequest $request, Order $order): AnonymousResourceCollection
    {
        Gate::authorize('view', $order);

        $jobs = $order->productionJobs()
            ->ofStatus($request->status())
            ->orderBy('id')
            ->paginate($request->perPage());

        return ProductionJobResource::collection($jobs);
    }

    public function store(StoreJobRequest $request, Order $order): JsonResponse
    {
        $job = $order->productionJobs()->create($request->validated());

        return ProductionJobResource::make($job)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
