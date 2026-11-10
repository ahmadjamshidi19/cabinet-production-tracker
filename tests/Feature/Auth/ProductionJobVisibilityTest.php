<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\ProductionJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductionJobVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_installer_sees_only_own_jobs(): void
    {
        $installer = User::factory()->installer()->create();
        $otherInstaller = User::factory()->installer()->create();

        $own = ProductionJob::factory()->assignedTo($installer)->create();
        ProductionJob::factory()->assignedTo($otherInstaller)->create();
        ProductionJob::factory()->create();

        $visibleIds = ProductionJob::visibleTo($installer)->pluck('id')->all();

        $this->assertSame([$own->id], $visibleIds);
    }

    public function test_planner_and_admin_see_all_jobs(): void
    {
        ProductionJob::factory()->count(3)->create();

        $this->assertCount(3, ProductionJob::visibleTo(User::factory()->planner()->create())->get());
        $this->assertCount(3, ProductionJob::visibleTo(User::factory()->admin()->create())->get());
    }

    public function test_route_binding_hides_another_installers_job(): void
    {
        Route::middleware(['api', 'auth:sanctum'])
            ->get('/api/testing/jobs/{job}', fn (ProductionJob $job) => response()->json(['id' => $job->id]));

        $installer = User::factory()->installer()->create();
        $own = ProductionJob::factory()->assignedTo($installer)->create();
        $foreign = ProductionJob::factory()
            ->assignedTo(User::factory()->installer()->create())
            ->create();

        Sanctum::actingAs($installer);

        $this->getJson("/api/testing/jobs/{$own->id}")->assertOk();
        $this->getJson("/api/testing/jobs/{$foreign->id}")->assertNotFound();
    }
}
