<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\JobStatus;
use Illuminate\Validation\Rule;

class ListJobsRequest extends PaginatedRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'status' => ['nullable', Rule::enum(JobStatus::class)],
        ];
    }

    public function status(): ?JobStatus
    {
        return $this->enum('status', JobStatus::class);
    }
}
