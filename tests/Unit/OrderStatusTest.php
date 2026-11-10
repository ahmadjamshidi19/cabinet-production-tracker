<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\JobStatus;
use App\Enums\OrderStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    /**
     * @param  list<JobStatus>  $jobStatuses
     */
    #[DataProvider('derivationCases')]
    public function test_it_derives_order_status_from_job_statuses(array $jobStatuses, OrderStatus $expected): void
    {
        $this->assertSame($expected, OrderStatus::fromJobStatuses(collect($jobStatuses)));
    }

    /**
     * @return array<string, array{list<JobStatus>, OrderStatus}>
     */
    public static function derivationCases(): array
    {
        return [
            'no jobs' => [[], OrderStatus::Pending],
            'all pending' => [[JobStatus::Pending, JobStatus::Pending], OrderStatus::Pending],
            'one job started' => [[JobStatus::Pending, JobStatus::Cutting], OrderStatus::InProgress],
            'all installed' => [[JobStatus::Installed, JobStatus::Installed], OrderStatus::Installed],
            'installed and ready' => [[JobStatus::Installed, JobStatus::ReadyForInstall], OrderStatus::InProgress],
        ];
    }
}
