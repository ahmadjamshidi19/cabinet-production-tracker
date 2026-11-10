<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JobStatus;
use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'customer_name',
        'address',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ProductionJob, $this>
     */
    public function productionJobs(): HasMany
    {
        return $this->hasMany(ProductionJob::class);
    }

    /**
     * Derived from the jobs and never stored (decision D1).
     * Eager load `productionJobs` before serializing lists to avoid N+1 queries.
     *
     * @return Attribute<OrderStatus, never>
     */
    protected function status(): Attribute
    {
        return Attribute::get(fn (): OrderStatus => OrderStatus::fromJobStatuses(
            $this->productionJobs->map(fn (ProductionJob $job): JobStatus => $job->status),
        ));
    }
}
