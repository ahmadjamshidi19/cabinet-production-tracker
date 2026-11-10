<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\JobStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJobStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(JobStatus::class)],
        ];
    }

    public function targetStatus(): JobStatus
    {
        return JobStatus::from($this->string('status')->toString());
    }
}
