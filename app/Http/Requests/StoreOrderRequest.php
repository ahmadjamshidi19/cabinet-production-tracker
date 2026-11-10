<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Order::class);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
        ];
    }
}
