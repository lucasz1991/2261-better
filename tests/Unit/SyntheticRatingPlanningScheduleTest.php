<?php

namespace Tests\Unit;

use App\Console\Kernel;
use Illuminate\Console\Scheduling\Schedule;
use ReflectionMethod;
use Tests\TestCase;

class SyntheticRatingPlanningScheduleTest extends TestCase
{
    public function test_automatic_planning_runs_once_daily_at_0005(): void
    {
        $schedule = new Schedule(config('app.timezone'));
        $method = new ReflectionMethod(app(Kernel::class), 'schedule');
        $method->invoke(app(Kernel::class), $schedule);

        $planningEvents = collect($schedule->events())
            ->filter(fn ($event): bool => str_contains((string) $event->command, 'ratings:plan-synthetic'))
            ->values();

        $this->assertCount(1, $planningEvents);
        $this->assertSame('5 0 * * *', $planningEvents->first()->expression);
        $this->assertTrue($planningEvents->first()->withoutOverlapping);
    }

    public function test_due_rating_execution_still_runs_every_minute(): void
    {
        $schedule = new Schedule(config('app.timezone'));
        $method = new ReflectionMethod(app(Kernel::class), 'schedule');
        $method->invoke(app(Kernel::class), $schedule);

        $executionEvents = collect($schedule->events())
            ->filter(fn ($event): bool => str_contains((string) $event->command, 'ratings:run-due-synthetic'))
            ->values();

        $this->assertCount(1, $executionEvents);
        $this->assertSame('* * * * *', $executionEvents->first()->expression);
    }

    public function test_pending_edit_synchronization_runs_every_minute_without_overlapping(): void
    {
        $schedule = new Schedule(config('app.timezone'));
        (new ReflectionMethod(app(Kernel::class), 'schedule'))->invoke(app(Kernel::class), $schedule);
        $events = collect($schedule->events())->filter(fn ($event): bool => str_contains((string) $event->command, 'ratings:sync-edits'))->values();
        $this->assertCount(1, $events);
        $this->assertSame('* * * * *', $events->first()->expression);
        $this->assertTrue($events->first()->withoutOverlapping);
    }
}
