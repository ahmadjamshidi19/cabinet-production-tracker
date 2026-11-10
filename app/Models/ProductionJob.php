<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JobStatus;
use App\Enums\UserRole;
use Database\Factories\ProductionJobFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property JobStatus $status
 */
class ProductionJob extends Model
{
    /** @use HasFactory<ProductionJobFactory> */
    use HasFactory;

    /** Explicit on purpose: `jobs` is Laravel's queue table. */
    protected $table = 'production_jobs';

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => JobStatus::Pending->value,
    ];

    /** Status is not mass assignable: it changes only through the transition action. */
    /** @var list<string> */
    protected $fillable = [
        'title',
        'installer_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => JobStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function installer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installer_id');
    }

    /**
     * @return HasMany<JobStatusHistory, $this>
     */
    public function histories(): HasMany
    {
        return $this->hasMany(JobStatusHistory::class, 'job_id');
    }

    /**
     * Installers only see jobs assigned to them; other roles see everything.
     *
     * @param  Builder<ProductionJob>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->role === UserRole::Installer) {
            $query->where('installer_id', $user->id);
        }
    }

    /**
     * Narrows the query to one status. A null status leaves the query untouched.
     *
     * @param  Builder<ProductionJob>  $query
     */
    public function scopeOfStatus(Builder $query, ?JobStatus $status): void
    {
        if ($status !== null) {
            $query->where('status', $status->value);
        }
    }
}
