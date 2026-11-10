<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\JobStatus;
use App\Models\Order;
use App\Models\ProductionJob;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    private const ADMIN_EMAIL = 'admin@example.com';

    /**
     * Seeds demo users, orders and jobs. Safe to run repeatedly: when the demo
     * admin already exists the seeder does nothing.
     */
    public function run(): void
    {
        if (User::where('email', self::ADMIN_EMAIL)->exists()) {
            return;
        }

        DB::transaction(function (): void {
            User::factory()->admin()->create([
                'name' => 'Demo Admin',
                'email' => self::ADMIN_EMAIL,
            ]);

            $planner = User::factory()->planner()->create([
                'name' => 'Demo Planner',
                'email' => 'planner@example.com',
            ]);

            $installerOne = User::factory()->installer()->create([
                'name' => 'Demo Installer One',
                'email' => 'installer1@example.com',
            ]);

            $installerTwo = User::factory()->installer()->create([
                'name' => 'Demo Installer Two',
                'email' => 'installer2@example.com',
            ]);

            $this->seedOrder($planner, 'Alice Johnson', '12 Maple Street, Springfield', [
                ['Kitchen base cabinets', JobStatus::Installed, $installerOne],
                ['Wall cabinets', JobStatus::ReadyForInstall, $installerOne],
            ]);

            $this->seedOrder($planner, 'Bob Smith', '48 Oak Avenue, Riverton', [
                ['Pantry unit', JobStatus::Assembly, $installerTwo],
                ['Vanity cabinet', JobStatus::EdgeBanding, $installerTwo],
            ]);

            $this->seedOrder($planner, 'Carol White', '7 Pine Road, Lakeside', [
                ['Kitchen island', JobStatus::Cutting, $installerOne],
                ['Wall cabinets', JobStatus::Pending, null],
            ]);

            $this->seedOrder($planner, 'David Brown', '91 Cedar Lane, Hillview', [
                ['Wardrobe', JobStatus::Installed, $installerTwo],
                ['Shoe cabinet', JobStatus::Installed, $installerTwo],
            ]);

            $this->seedOrder($planner, 'Eva Green', '23 Birch Court, Fairview', [
                ['Laundry cabinets', JobStatus::Pending, null],
            ]);
        });
    }

    /**
     * @param  list<array{string, JobStatus, User|null}>  $jobs  title, current status, installer
     */
    private function seedOrder(User $planner, string $customerName, string $address, array $jobs): void
    {
        $order = Order::factory()->create([
            'customer_name' => $customerName,
            'address' => $address,
            'created_by' => $planner->id,
        ]);

        foreach ($jobs as [$title, $status, $installer]) {
            $job = ProductionJob::factory()
                ->for($order)
                ->withStatus($status)
                ->create([
                    'title' => $title,
                    'installer_id' => $installer?->id,
                ]);

            $this->recordHistory($job, $planner);
        }
    }

    /**
     * Writes one history row per step from pending up to the job's current status,
     * so the demo data agrees with the rule that every change is recorded.
     * Production steps are done by the planner, the install step by the installer.
     */
    private function recordHistory(ProductionJob $job, User $planner): void
    {
        $from = JobStatus::Pending;
        $at = CarbonImmutable::now()->subDays(7);

        while ($from !== $job->status) {
            $to = $from->allowedTransitions()[0];
            $at = $at->addHours(6);

            $actorId = $to === JobStatus::Installed
                ? ($job->installer_id ?? $planner->id)
                : $planner->id;

            $job->histories()->forceCreate([
                'changed_by' => $actorId,
                'from_status' => $from,
                'to_status' => $to,
                'created_at' => $at,
            ]);

            $from = $to;
        }
    }
}
