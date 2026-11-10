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

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_planner_creates_order_and_creator_comes_from_the_token(): void
    {
        $planner = User::factory()->planner()->create();
        $someoneElse = User::factory()->admin()->create();

        Sanctum::actingAs($planner);

        $this->postJson('/api/orders', [
            'customer_name' => 'Alice Johnson',
            'address' => '12 Maple Street, Springfield',
            'created_by' => $someoneElse->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.created_by', $planner->id);

        $this->assertDatabaseHas('orders', [
            'customer_name' => 'Alice Johnson',
            'created_by' => $planner->id,
        ]);
    }

    public function test_installer_cannot_create_or_list_orders(): void
    {
        Sanctum::actingAs(User::factory()->installer()->create());

        $this->postJson('/api/orders', [
            'customer_name' => 'Alice Johnson',
            'address' => '12 Maple Street, Springfield',
        ])->assertForbidden();

        $this->getJson('/api/orders')->assertForbidden();

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_list_exposes_status_derived_from_jobs(): void
    {
        $order = Order::factory()->create();
        ProductionJob::factory()->count(2)->for($order)->withStatus(JobStatus::Installed)->create();

        Sanctum::actingAs(User::factory()->planner()->create());

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'installed');
    }
}
