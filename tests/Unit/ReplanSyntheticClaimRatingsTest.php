<?php

namespace Tests\Unit;

use App\Models\ClaimRating;
use App\Models\SyntheticRatingUser;
use App\Services\BaseClaimRatingPublisher;
use App\Services\ReplanSyntheticClaimRatings;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ReplanSyntheticClaimRatingsTest extends TestCase
{
    private string $connection = 'replan_test';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-04 10:00:00');
        config()->set("database.connections.{$this->connection}", [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        config()->set('database.default', $this->connection);

        DB::purge($this->connection);
        $this->createTables();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::purge($this->connection);

        parent::tearDown();
    }

    public function test_only_selected_future_unexecuted_ratings_are_replaced_with_fresh_plans(): void
    {
        $firstFuture = $this->createRating('2026-09-06 09:15:00');
        $preparedFuture = $this->createRating('2026-09-07 10:30:00', [
            'status' => ClaimRating::STATUS_RATED,
            'answers' => ['prepared' => true],
            'data' => [
                'synthetic' => true,
                'ai_generation' => ['generated_at' => now()->toDateTimeString()],
            ],
        ]);
        $executed = $this->createRating('2026-09-08 11:00:00', [
            'status' => ClaimRating::STATUS_RATED,
            'execution_started_at' => now()->subMinute(),
            'executed_at' => now(),
            'base_claim_rating_id' => 501,
        ]);
        $baseLinkedFuture = $this->createRating('2026-09-09 14:20:00', [
            'base_user_id' => 601,
        ]);
        $baseLinkedFuture->syntheticUser->forceFill(['base_user_id' => 601])->saveQuietly();
        $past = $this->createRating('2026-09-03 12:00:00');

        $this->assertEqualsCanonicalizing(
            [$firstFuture->id, $preparedFuture->id, $baseLinkedFuture->id],
            ClaimRating::query()->replannable(now())->pluck('id')->all()
        );

        $oldUserIds = [
            $firstFuture->synthetic_rating_user_id,
            $preparedFuture->synthetic_rating_user_id,
            $baseLinkedFuture->synthetic_rating_user_id,
        ];
        $oldMinutes = [
            $firstFuture->scheduled_for->format('Y-m-d H:i'),
            $preparedFuture->scheduled_for->format('Y-m-d H:i'),
            $baseLinkedFuture->scheduled_for->format('Y-m-d H:i'),
        ];
        $service = $this->successfulService();
        $report = $service->replace([
            (string) $firstFuture->id,
            $preparedFuture->id,
            $executed->id,
            $baseLinkedFuture->id,
            $past->id,
        ]);

        $this->assertTrue($report['ok']);
        $this->assertSame(5, $report['requested_count']);
        $this->assertSame(3, $report['replaced_count']);
        $this->assertSame(3, $report['created_count']);
        $this->assertSame(2, $report['skipped_count']);
        $this->assertSame(1, $report['removed_base_connection_count']);
        $this->assertEqualsCanonicalizing([$executed->id, $past->id], $report['skipped_ids']);
        $this->assertSame([$baseLinkedFuture->id], $service->removedBaseConnectionIds);
        $this->assertCount(3, $service->calls);
        $this->assertSame(['2026-09-06', '2026-09-07', '2026-09-09'], array_column($service->calls, 'date'));

        $this->assertTrue(ClaimRating::withTrashed()->findOrFail($firstFuture->id)->trashed());
        $this->assertTrue(ClaimRating::withTrashed()->findOrFail($preparedFuture->id)->trashed());
        $this->assertTrue(ClaimRating::withTrashed()->findOrFail($baseLinkedFuture->id)->trashed());
        $this->assertFalse(ClaimRating::findOrFail($executed->id)->trashed());
        $this->assertFalse(ClaimRating::findOrFail($past->id)->trashed());

        foreach ($oldUserIds as $oldUserId) {
            $this->assertTrue(SyntheticRatingUser::withTrashed()->findOrFail($oldUserId)->trashed());
        }

        $createdRatings = ClaimRating::query()
            ->with('syntheticUser')
            ->whereIn('id', $report['created_ids'])
            ->orderBy('scheduled_for')
            ->get();

        $this->assertCount(3, $createdRatings);
        $this->assertSame(['2026-09-06', '2026-09-07', '2026-09-09'], $createdRatings->pluck('scheduled_for')->map->toDateString()->all());
        $this->assertEmpty(array_intersect($oldMinutes, $createdRatings->pluck('scheduled_for')->map->format('Y-m-d H:i')->all()));

        foreach ($createdRatings as $createdRating) {
            $this->assertNotNull($createdRating->syntheticUser);
            $this->assertNotContains($createdRating->synthetic_rating_user_id, $oldUserIds);
            $this->assertSame($report['batch_id'], data_get($createdRating->data, 'planning.replacement.batch_id'));
        }
    }

    public function test_incomplete_replacement_rolls_back_ratings_and_synthetic_users(): void
    {
        $future = $this->createRating('2026-09-06 09:15:00');
        $oldUserId = $future->synthetic_rating_user_id;
        $service = new class extends ReplanSyntheticClaimRatings
        {
            protected function planForDate(string $date, int $count, array $excludedTimes): array
            {
                return [
                    'ok' => false,
                    'reason' => 'Testweise keine aktive Kombination.',
                    'created_count' => 0,
                    'created' => [],
                ];
            }
        };

        try {
            $service->replace([$future->id]);
            $this->fail('Expected incomplete replacement to throw.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('konnten nicht vollstaendig ersetzt werden', $exception->getMessage());
        }

        $this->assertNotNull(ClaimRating::find($future->id));
        $this->assertFalse(ClaimRating::withTrashed()->findOrFail($future->id)->trashed());
        $this->assertNotNull(SyntheticRatingUser::find($oldUserId));
        $this->assertFalse(SyntheticRatingUser::withTrashed()->findOrFail($oldUserId)->trashed());
    }

    public function test_owned_base_rating_link_can_be_removed_before_replanning(): void
    {
        $baseConnection = 'replan_base_test';
        config()->set("database.connections.{$baseConnection}", [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge($baseConnection);
        Schema::connection($baseConnection)->create('claim_ratings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->json('data')->nullable();
            $table->json('admin_review')->nullable();
        });

        $rating = $this->createRating('2026-09-09 14:20:00', [
            'base_claim_rating_id' => 951,
            'base_user_id' => 952,
        ]);
        $rating->syntheticUser->forceFill(['base_user_id' => 952])->saveQuietly();
        DB::connection($baseConnection)->table('claim_ratings')->insert([
            'id' => 951,
            'user_id' => 952,
            'data' => json_encode([
                'synthetic' => true,
                'source_app' => '2261-better',
                'local_claim_rating_id' => $rating->id,
                'do_not_publish' => true,
            ]),
            'admin_review' => json_encode([
                'synthetic' => true,
                'source_app' => '2261-better',
                'do_not_publish' => true,
            ]),
        ]);

        app(BaseClaimRatingPublisher::class)->retractOnConnection($rating, $baseConnection);

        $this->assertFalse(DB::connection($baseConnection)->table('claim_ratings')->where('id', 951)->exists());
        $rating->refresh();
        $this->assertNull($rating->base_claim_rating_id);
        $this->assertNull($rating->base_user_id);
        $this->assertNull($rating->execution_started_at);
        $this->assertNull($rating->executed_at);
        $this->assertSame(ClaimRating::STATUS_RETRACTED, $rating->status);
        $this->assertTrue($rating->isManualOnlyAfterRetract());
        $this->assertNull($rating->syntheticUser->fresh()->base_user_id);

        DB::purge($baseConnection);
    }

    public function test_unknown_base_user_link_is_not_removed_in_strict_replanning_mode(): void
    {
        $baseConnection = 'replan_unknown_base_test';
        config()->set("database.connections.{$baseConnection}", [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge($baseConnection);
        Schema::connection($baseConnection)->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
        });

        $rating = $this->createRating('2026-09-09 14:20:00', [
            'base_user_id' => 777,
        ]);
        DB::connection($baseConnection)->table('users')->insert([
            'id' => 777,
            'name' => 'Fremder Benutzer',
            'email' => 'fremd@example.com',
        ]);

        try {
            app(BaseClaimRatingPublisher::class)->retractOnConnection($rating, $baseConnection, true);
            $this->fail('Expected strict Base ownership validation to throw.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('nicht als eigener synthetischer', $exception->getMessage());
        }

        $this->assertTrue(DB::connection($baseConnection)->table('users')->where('id', 777)->exists());
        $rating->refresh();
        $this->assertSame(777, $rating->base_user_id);
        $this->assertSame(ClaimRating::STATUS_SCHEDULED, $rating->status);

        DB::purge($baseConnection);
    }

    private function successfulService(): ReplanSyntheticClaimRatings
    {
        return new class extends ReplanSyntheticClaimRatings
        {
            public array $calls = [];

            public array $removedBaseConnectionIds = [];

            protected function removeBaseConnections(Collection $ratings): int
            {
                $linkedRatings = $ratings
                    ->filter(fn (ClaimRating $rating): bool => (bool) $rating->base_claim_rating_id || (bool) $rating->base_user_id)
                    ->values();
                $this->removedBaseConnectionIds = $linkedRatings->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

                foreach ($linkedRatings as $rating) {
                    $rating->forceFill([
                        'base_claim_rating_id' => null,
                        'base_user_id' => null,
                    ])->saveQuietly();
                }

                return $linkedRatings->count();
            }

            protected function planForDate(string $date, int $count, array $excludedTimes): array
            {
                $this->calls[] = compact('date', 'count', 'excludedTimes');
                $created = [];

                for ($index = 0; $index < $count; $index++) {
                    $token = Str::lower(Str::random(16));
                    $syntheticUser = SyntheticRatingUser::create([
                        'name' => "Neue Person {$token}",
                        'first_name' => 'Neue',
                        'last_name' => "Person {$token}",
                        'username' => "neustern_{$token}",
                        'email' => "{$token}@example.net",
                        'email_domain' => 'example.net',
                        'role' => 'guest',
                        'status' => true,
                        'email_verified_at' => now(),
                        'data' => ['synthetic' => true],
                    ]);
                    $scheduledFor = Carbon::parse($date)->setTime(18, $index, 15);
                    $rating = ClaimRating::create([
                        'synthetic_rating_user_id' => $syntheticUser->id,
                        'status' => ClaimRating::STATUS_SCHEDULED,
                        'scheduled_for' => $scheduledFor,
                        'data' => [
                            'synthetic' => true,
                            'planning' => [
                                'planned_for' => $scheduledFor->toDateTimeString(),
                            ],
                        ],
                        'verification_hash' => (string) Str::uuid(),
                    ]);

                    $created[] = [
                        'id' => $rating->id,
                        'scheduled_for' => $scheduledFor->toDateTimeString(),
                    ];
                }

                return [
                    'ok' => true,
                    'reason' => 'Planung abgeschlossen.',
                    'created_count' => count($created),
                    'created' => $created,
                ];
            }
        };
    }

    private function createRating(string $scheduledFor, array $overrides = []): ClaimRating
    {
        $token = Str::lower(Str::random(16));
        $syntheticUser = SyntheticRatingUser::create([
            'name' => "Alte Person {$token}",
            'first_name' => 'Alte',
            'last_name' => "Person {$token}",
            'username' => "altstern_{$token}",
            'email' => "{$token}@example.org",
            'email_domain' => 'example.org',
            'role' => 'guest',
            'status' => true,
            'email_verified_at' => now(),
            'data' => ['synthetic' => true],
        ]);
        $attributes = array_replace_recursive([
            'synthetic_rating_user_id' => $syntheticUser->id,
            'status' => ClaimRating::STATUS_SCHEDULED,
            'scheduled_for' => Carbon::parse($scheduledFor),
            'data' => [
                'synthetic' => true,
                'planning' => ['planned_for' => $scheduledFor],
            ],
            'verification_hash' => (string) Str::uuid(),
        ], $overrides);

        return ClaimRating::create($attributes)->fresh('syntheticUser');
    }

    private function createTables(): void
    {
        Schema::connection($this->connection)->create('synthetic_rating_users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('base_user_id')->nullable()->unique();
            $table->string('name');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('username')->nullable();
            $table->string('email')->unique();
            $table->string('email_domain')->nullable();
            $table->string('role')->default('guest');
            $table->boolean('status')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($this->connection)->create('claim_ratings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('base_claim_rating_id')->nullable()->unique();
            $table->unsignedBigInteger('base_user_id')->nullable();
            $table->unsignedBigInteger('synthetic_rating_user_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('insurance_subtype_id')->nullable();
            $table->unsignedBigInteger('insurance_type_id')->nullable();
            $table->unsignedBigInteger('rating_questionnaire_versions_id')->nullable();
            $table->unsignedBigInteger('insurance_id')->nullable();
            $table->json('answers')->nullable();
            $table->string('status')->default(ClaimRating::STATUS_PENDING);
            $table->dateTime('scheduled_for')->nullable();
            $table->dateTime('execution_started_at')->nullable();
            $table->dateTime('executed_at')->nullable();
            $table->unsignedSmallInteger('execution_attempts')->default(0);
            $table->text('last_execution_error')->nullable();
            $table->json('attachments')->nullable();
            $table->decimal('rating_score', 3, 2)->nullable();
            $table->json('tag_ids')->nullable();
            $table->text('moderator_comment')->nullable();
            $table->boolean('is_public')->default(false);
            $table->json('admin_review')->nullable();
            $table->json('data')->nullable();
            $table->uuid('verification_hash')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
