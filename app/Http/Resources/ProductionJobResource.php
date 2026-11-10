<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ProductionJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductionJob
 */
class ProductionJobResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'title' => $this->title,
            'status' => $this->status->value,
            'installer_id' => $this->installer_id,
            'order' => $this->whenLoaded('order', fn (): array => [
                'id' => $this->order->id,
                'customer_name' => $this->order->customer_name,
                'address' => $this->order->address,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
