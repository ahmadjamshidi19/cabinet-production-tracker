<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Enums\JobStatus;
use App\Models\Order;
use App\Models\ProductionJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductionJobApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_installer_lists_only_own_jobs(): void
    {
        $installer = User::factory()->installer()->create();
        $own = ProductionJob::factory()->assignedTo($installer)->create();
        ProductionJob::factory()->assignedTo(User::factory()->installer()->create())->create();
        ProductionJob::factory()->create();

        Sanctum::actingAs($installer);

        $this->getJson('/api/jobs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_status_filter_is_applied_and_validated(): void
    {
        ProductionJob::factory()->withStatus(JobStatus::Cutting)->create();
        ProductionJob::factory()->withStatus(JobStatus::Assembly)->create();

        Sanctum::actingAs(User::factory()->planner()->create());

        $this->getJson('/api/jobs?status=cutting')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'cutting');

        $this->getJson('/api/jobs?status=unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_planner_creates_job_but_installer_cannot(): void
    {
        $order = Order::factory()->create();
        $installer = User::factory()->installer()->create();
        $planner = User::factory()->planner()->create();
        $payload = ['title' => 'Pantry unit', 'installer_id' => $installer->id];

        Sanctum::actingAs($installer);
        $this->postJson("/api/orders/{$order->id}/jobs", $payload)->assertForbidden();
        $this->assertDatabaseCount('production_jobs', 0);

        Sanctum::actingAs($planner);
        $this->postJson("/api/orders/{$order->id}/jobs", $payload + ['status' => 'installed'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.installer_id', $installer->id);

        $this->postJson("/api/orders/{$order->id}/jobs", ['title' => 'Bad', 'installer_id' => $planner->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('installer_id');

        $this->assertDatabaseCount('production_jobs', 1);
    }

    public function test_job_list_is_paginated(): void
    {
        ProductionJob::factory()->count(5)->create();

        Sanctum::actingAs(User::factory()->planner()->create());

        $this->getJson('/api/jobs?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 3);

        $this->getJson('/api/jobs?per_page=2&page=3')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_per_page_above_the_limit_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->planner()->create());

        $this->getJson('/api/jobs?per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }
}
