<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JobStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property JobStatus|null $from_status
 * @property JobStatus $to_status
 */
class JobStatusHistory extends Model
{
    /** Append-only table: there is no updated_at column. */
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'changed_by',
        'from_status',
        'to_status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => JobStatus::class,
            'to_status' => JobStatus::class,
        ];
    }

    /**
     * @return BelongsTo<ProductionJob, $this>
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(ProductionJob::class, 'job_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
