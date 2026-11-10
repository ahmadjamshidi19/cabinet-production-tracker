<?php

namespace Database\Factories;

use App\Enums\JobStatus;
use App\Models\Order;
use App\Models\ProductionJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionJob>
 */
class ProductionJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'installer_id' => null,
            'title' => fake()->randomElement([
                'Kitchen base cabinets',
                'Wall cabinets',
                'Pantry unit',
                'Vanity cabinet',
            ]),
            'status' => JobStatus::Pending,
        ];
    }

    public function withStatus(JobStatus $status): static
    {
        return $this->state(['status' => $status]);
    }

    public function assignedTo(User $installer): static
    {
        return $this->state(['installer_id' => $installer->id]);
    }
}
