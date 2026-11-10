<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Enums\JobStatus;
use App\Models\ProductionJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JobStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_planner_moves_job_forward_and_history_is_recorded(): void
    {
        $planner = User::factory()->planner()->create();
        $job = ProductionJob::factory()->withStatus(JobStatus::Pending)->create();

        Sanctum::actingAs($planner);

        $this->patchJson("/api/jobs/{$job->id}/status", ['status' => 'cutting'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cutting');

        $this->assertSame(JobStatus::Cutting, $job->fresh()->status);
        $this->assertDatabaseHas('job_status_histories', [
            'job_id' => $job->id,
            'changed_by' => $planner->id,
            'from_status' => 'pending',
            'to_status' => 'cutting',
        ]);
    }

    public function test_invalid_transition_is_rejected_and_nothing_changes(): void
    {
        $job = ProductionJob::factory()->withStatus(JobStatus::Installed)->create();

        Sanctum::actingAs(User::factory()->planner()->create());

        $this->patchJson("/api/jobs/{$job->id}/status", ['status' => 'cutting'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertSame(JobStatus::Installed, $job->fresh()->status);
        $this->assertDatabaseCount('job_status_histories', 0);
    }

    public function test_installer_completes_own_job_at_the_install_step(): void
    {
        $installer = User::factory()->installer()->create();
        $job = ProductionJob::factory()
            ->withStatus(JobStatus::ReadyForInstall)
            ->assignedTo($installer)
            ->create();

        Sanctum::actingAs($installer);

        $this->patchJson("/api/jobs/{$job->id}/status", ['status' => 'installed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'installed');

        $this->assertDatabaseHas('job_status_histories', [
            'job_id' => $job->id,
            'changed_by' => $installer->id,
            'from_status' => 'ready_for_install',
            'to_status' => 'installed',
        ]);
    }

    public function test_installer_gets_404_for_another_installers_job(): void
    {
        $job = ProductionJob::factory()
            ->withStatus(JobStatus::ReadyForInstall)
            ->assignedTo(User::factory()->installer()->create())
            ->create();

        Sanctum::actingAs(User::factory()->installer()->create());

        $this->patchJson("/api/jobs/{$job->id}/status", ['status' => 'installed'])
            ->assertNotFound();

        $this->assertSame(JobStatus::ReadyForInstall, $job->fresh()->status);
        $this->assertDatabaseCount('job_status_histories', 0);
    }

    public function test_installer_cannot_perform_production_steps_on_own_job(): void
    {
        $installer = User::factory()->installer()->create();
        $job = ProductionJob::factory()
            ->withStatus(JobStatus::Pending)
            ->assignedTo($installer)
            ->create();

        Sanctum::actingAs($installer);

        $this->patchJson("/api/jobs/{$job->id}/status", ['status' => 'cutting'])
            ->assertForbidden();

        $this->assertSame(JobStatus::Pending, $job->fresh()->status);
        $this->assertDatabaseCount('job_status_histories', 0);
    }

    public function test_transition_locks_the_job_row(): void
    {
        $job = ProductionJob::factory()->withStatus(JobStatus::Pending)->create();

        Sanctum::actingAs(User::factory()->planner()->create());

        DB::enableQueryLog();

        $this->patchJson("/api/jobs/{$job->id}/status", ['status' => 'cutting'])->assertOk();

        $lockingQueries = collect(DB::getQueryLog())
            ->filter(fn (array $entry): bool => str_contains(strtolower($entry['query']), 'for update'));

        $this->assertCount(1, $lockingQueries);
    }
}
