<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\JobStatus;
use DomainException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class InvalidStatusTransition extends DomainException
{
    public function __construct(
        public readonly JobStatus $from,
        public readonly JobStatus $to,
    ) {
        parent::__construct(sprintf(
            'A job cannot move from "%s" to "%s".',
            $from->value,
            $to->value,
        ));
    }

    /**
     * This is an expected client error, so it must not be logged as a server failure.
     * A report method that does not return false marks the exception as handled.
     */
    public function report(): void
    {
        // Intentionally empty.
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => ['status' => [$this->getMessage()]],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
