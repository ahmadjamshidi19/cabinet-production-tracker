<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginatedRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function index(PaginatedRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Order::class);

        $orders = Order::query()
            ->with('productionJobs:id,order_id,status')
            ->orderByDesc('id')
            ->paginate($request->perPage());

        return OrderResource::collection($orders);
    }

    public function store(StoreOrderRequest $request, #[CurrentUser] User $user): JsonResponse
    {
        $order = $user->orders()->create($request->validated());

        return OrderResource::make($order)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
