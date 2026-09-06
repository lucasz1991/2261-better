<?php

namespace Tests\Unit;

use App\Jobs\PlanSyntheticClaimRatings;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PlanSyntheticClaimRatingsProviderWeightTest extends TestCase
{
    private string $connection = 'provider_weight_test';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set("database.connections.{$this->connection}", [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge($this->connection);
        $this->createBaseTables();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_high_provider_weight_is_selected_far_more_often(): void
    {
        $job = new PlanSyntheticClaimRatings;
        $providers = [
            ['id' => 1, 'name' => 'High Provider', 'weight' => 100.0],
            ['id' => 2, 'name' => 'Low Provider', 'weight' => 1.0],
        ];

        $counts = [1 => 0, 2 => 0];

        for ($i = 0; $i < 500; $i++) {
            $selected = $this->invokePrivate($job, 'weightedRandomProvider', [$providers]);
            $counts[$selected['id']]++;
        }

        $this->assertGreaterThan(450, $counts[1]);
        $this->assertLessThan(50, $counts[2]);
    }

    public function test_provider_with_zero_weight_is_not_selected(): void
    {
        $job = new PlanSyntheticClaimRatings;
        $providers = [
            ['id' => 1, 'name' => 'Zero Provider', 'weight' => 0.0],
            ['id' => 2, 'name' => 'Active Provider', 'weight' => 1.0],
        ];

        for ($i = 0; $i < 25; $i++) {
            $selected = $this->invokePrivate($job, 'weightedRandomProvider', [$providers]);

            $this->assertSame(2, $selected['id']);
        }
    }

    public function test_type_subtype_pairs_are_filtered_to_selected_provider(): void
    {
        $this->seedProviderFixture();
        $job = new PlanSyntheticClaimRatings;

        $result = $this->invokePrivate($job, 'eligiblePairs', [
            $this->connection,
            [
                'type_weights' => [10 => 1.0, 20 => 1.0],
                'subtype_weights' => [
                    10 => [100 => 1.0],
                    20 => [200 => 1.0],
                ],
            ],
            1,
        ]);

        $this->assertFalse($result['weight_fallback']);
        $this->assertCount(1, $result['pairs']);
        $this->assertSame(10, $result['pairs'][0]['type_id']);
        $this->assertSame(100, $result['pairs'][0]['subtype_id']);
    }

    public function test_active_provider_without_valid_pair_returns_no_pairs(): void
    {
        $this->seedProviderFixture();
        $job = new PlanSyntheticClaimRatings;

        $providers = $this->invokePrivate($job, 'eligibleProviders', [
            $this->connection,
            ['provider_weights' => [3 => 100.0]],
        ]);

        $pairs = $this->invokePrivate($job, 'eligiblePairs', [
            $this->connection,
            [
                'type_weights' => [10 => 1.0, 20 => 1.0],
                'subtype_weights' => [
                    10 => [100 => 1.0],
                    20 => [200 => 1.0],
                ],
            ],
            3,
        ]);

        $this->assertContains(3, array_column($providers, 'id'));
        $this->assertSame([], $pairs['pairs']);
    }

    public function test_exact_planning_avoids_old_and_duplicate_visible_minutes(): void
    {
        Carbon::setTestNow('2026-09-04 10:00:00');
        CarbonImmutable::setTestNow('2026-09-04 10:00:00');
        $excludedTimes = [];

        for ($minute = 0; $minute < 60; $minute++) {
            $excludedTimes[] = sprintf('2026-09-06 10:%02d:30', $minute);
        }

        $job = new PlanSyntheticClaimRatings(
            date: '2026-09-06',
            targetCount: 2,
            createExactCount: true,
            excludedScheduledFor: $excludedTimes,
        );
        $this->invokePrivate($job, 'initializeReservedScheduleMinutes', []);

        $first = $this->invokePrivate($job, 'scheduledTime', [
            CarbonImmutable::parse('2026-09-06'),
            [10 => 100],
        ]);
        $second = $this->invokePrivate($job, 'scheduledTime', [
            CarbonImmutable::parse('2026-09-06'),
            [10 => 100],
        ]);

        $this->assertSame('2026-09-06', $first->toDateString());
        $this->assertSame('2026-09-06', $second->toDateString());
        $excludedMinutes = array_map(
            fn (string $time): string => CarbonImmutable::parse($time)->format('Y-m-d H:i'),
            $excludedTimes
        );
        $this->assertNotContains($first->format('Y-m-d H:i'), $excludedMinutes);
        $this->assertNotContains($second->format('Y-m-d H:i'), $excludedMinutes);
        $this->assertNotSame($first->format('Y-m-d H:i'), $second->format('Y-m-d H:i'));
    }

    private function createBaseTables(): void
    {
        Schema::connection($this->connection)->create('insurances', function ($table): void {
            $table->integer('id')->primary();
            $table->string('name');
            $table->boolean('is_active')->default(true);
        });

        Schema::connection($this->connection)->create('insurance_types', function ($table): void {
            $table->integer('id')->primary();
            $table->string('name');
            $table->boolean('is_active')->default(true);
        });

        Schema::connection($this->connection)->create('insurance_subtypes', function ($table): void {
            $table->integer('id')->primary();
            $table->string('name');
            $table->boolean('is_active')->default(true);
        });

        Schema::connection($this->connection)->create('insurance_insurance_type', function ($table): void {
            $table->integer('insurance_id');
            $table->integer('insurance_type_id');
        });

        Schema::connection($this->connection)->create('insurance_type_insurance_subtype', function ($table): void {
            $table->integer('insurance_type_id');
            $table->integer('insurance_subtype_id');
        });
    }

    private function seedProviderFixture(): void
    {
        DB::connection($this->connection)->table('insurances')->insert([
            ['id' => 1, 'name' => 'Provider A', 'is_active' => true],
            ['id' => 2, 'name' => 'Provider B', 'is_active' => true],
            ['id' => 3, 'name' => 'Provider Without Pairs', 'is_active' => true],
        ]);

        DB::connection($this->connection)->table('insurance_types')->insert([
            ['id' => 10, 'name' => 'Type A', 'is_active' => true],
            ['id' => 20, 'name' => 'Type B', 'is_active' => true],
        ]);

        DB::connection($this->connection)->table('insurance_subtypes')->insert([
            ['id' => 100, 'name' => 'Subtype A', 'is_active' => true],
            ['id' => 200, 'name' => 'Subtype B', 'is_active' => true],
        ]);

        DB::connection($this->connection)->table('insurance_insurance_type')->insert([
            ['insurance_id' => 1, 'insurance_type_id' => 10],
            ['insurance_id' => 2, 'insurance_type_id' => 20],
        ]);

        DB::connection($this->connection)->table('insurance_type_insurance_subtype')->insert([
            ['insurance_type_id' => 10, 'insurance_subtype_id' => 100],
            ['insurance_type_id' => 20, 'insurance_subtype_id' => 200],
        ]);
    }

    private function invokePrivate(object $object, string $method, array $arguments): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }
}
