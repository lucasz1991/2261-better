<?php

namespace App\Services;

use App\Jobs\PlanSyntheticClaimRatings;
use App\Models\ClaimRating;
use App\Support\Database\RegCheckDatabase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class ReplanSyntheticClaimRatings
{
    /**
     * @param  array<int, int|string>  $ratingIds
     * @return array<string, mixed>
     */
    public function replace(array $ratingIds): array
    {
        $requestedIds = $this->normalizeIds($ratingIds);
        $report = [
            'ok' => false,
            'reason' => null,
            'batch_id' => null,
            'requested_count' => count($requestedIds),
            'replaced_count' => 0,
            'created_count' => 0,
            'skipped_count' => 0,
            'replaced_ids' => [],
            'created_ids' => [],
            'skipped_ids' => [],
            'deleted_synthetic_user_count' => 0,
            'removed_base_connection_count' => 0,
            'dates' => [],
        ];

        if ($requestedIds === []) {
            $report['reason'] = 'Keine Bewertungen fuer die Neuplanung ausgewaehlt.';

            return $report;
        }

        $cutoff = CarbonImmutable::now();
        $batchId = (string) Str::uuid();

        $report = DB::transaction(function () use ($requestedIds, $cutoff, $batchId, $report): array {
            $ratings = ClaimRating::query()
                ->with('syntheticUser')
                ->whereKey($requestedIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $replannable = $ratings
                ->filter(fn (ClaimRating $rating): bool => $rating->isReplannableAt($cutoff))
                ->values();
            $replacedIds = $replannable->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
            $skippedIds = array_values(array_diff($requestedIds, $replacedIds));

            $report['batch_id'] = $batchId;
            $report['replaced_count'] = count($replacedIds);
            $report['skipped_count'] = count($skippedIds);
            $report['replaced_ids'] = $replacedIds;
            $report['skipped_ids'] = $skippedIds;

            if ($replannable->isEmpty()) {
                $report['reason'] = 'Keine der ausgewaehlten Bewertungen ist noch bevorstehend und neu planbar.';

                return $report;
            }

            $ratingsByDate = $replannable
                ->groupBy(fn (ClaimRating $rating): string => $rating->scheduled_for->toDateString())
                ->sortKeys();
            $oldSyntheticUserIds = $replannable
                ->pluck('synthetic_rating_user_id')
                ->filter()
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->values()
                ->all();

            foreach ($ratingsByDate as $date => $dateRatings) {
                $expectedCount = $dateRatings->count();
                $excludedTimes = ClaimRating::query()
                    ->whereDate('scheduled_for', (string) $date)
                    ->whereNotNull('scheduled_for')
                    ->pluck('scheduled_for')
                    ->map(fn (mixed $scheduledFor): string => CarbonImmutable::parse((string) $scheduledFor)->toDateTimeString())
                    ->all();
                $planningReport = $this->planForDate((string) $date, $expectedCount, $excludedTimes);
                $createdIds = collect($planningReport['created'] ?? [])
                    ->pluck('id')
                    ->filter()
                    ->map(fn (mixed $id): int => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

                if (! ($planningReport['ok'] ?? false)
                    || (int) ($planningReport['created_count'] ?? 0) !== $expectedCount
                    || count($createdIds) !== $expectedCount) {
                    $reason = (string) ($planningReport['reason'] ?? 'Unbekannter Planungsfehler.');

                    throw new RuntimeException(
                        "Die {$expectedCount} Bewertungen fuer {$date} konnten nicht vollstaendig ersetzt werden: {$reason}"
                    );
                }

                $createdRatings = ClaimRating::query()
                    ->with('syntheticUser')
                    ->whereKey($createdIds)
                    ->orderBy('id')
                    ->get();

                $this->assertFreshReplacement(
                    $createdRatings,
                    $dateRatings,
                    (string) $date,
                    $expectedCount,
                    $oldSyntheticUserIds
                );

                $replacedDateIds = $dateRatings
                    ->pluck('id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->all();
                $replannedAt = now()->toDateTimeString();

                foreach ($createdRatings as $createdRating) {
                    $data = $createdRating->data ?? [];
                    $planning = is_array($data['planning'] ?? null) ? $data['planning'] : [];
                    $planning['replacement'] = [
                        'batch_id' => $batchId,
                        'replanned_at' => $replannedAt,
                        'replanned_by' => self::class,
                        'replaced_claim_rating_ids' => $replacedDateIds,
                    ];
                    $data['planning'] = $planning;

                    $createdRating->forceFill(['data' => $data])->saveQuietly();
                }

                $report['created_ids'] = array_merge($report['created_ids'], $createdIds);
                $report['dates'][(string) $date] = [
                    'replaced_count' => $expectedCount,
                    'created_count' => count($createdIds),
                    'replaced_ids' => $replacedDateIds,
                    'created_ids' => $createdIds,
                ];
            }

            $discardedAt = now();

            foreach ($replannable as $rating) {
                $data = $rating->data ?? [];
                $planning = is_array($data['planning'] ?? null) ? $data['planning'] : [];
                $planning['replacement'] = array_merge(
                    is_array($planning['replacement'] ?? null) ? $planning['replacement'] : [],
                    [
                        'discarded_at' => $discardedAt->toDateTimeString(),
                        'discarded_by' => self::class,
                        'replacement_batch_id' => $batchId,
                    ]
                );
                $data['planning'] = $planning;

                $rating->forceFill(['data' => $data])->saveQuietly();
                $rating->delete();
            }

            $report['removed_base_connection_count'] = $this->removeBaseConnections($replannable);
            $report['deleted_synthetic_user_count'] = $this->deleteOrphanedSyntheticUsers($replannable);

            $report['created_count'] = count($report['created_ids']);
            $report['ok'] = $report['created_count'] === $report['replaced_count'];
            $report['reason'] = $report['ok']
                ? 'Ausgewaehlte bevorstehende Bewertungen wurden vollstaendig neu geplant.'
                : 'Die ausgewaehlten Bewertungen konnten nicht vollstaendig ersetzt werden.';

            if (! $report['ok']) {
                throw new RuntimeException($report['reason']);
            }

            return $report;
        }, 3);

        if ($report['ok']) {
            Log::info('Selected synthetic rating plans were replaced.', [
                'batch_id' => $report['batch_id'],
                'requested_count' => $report['requested_count'],
                'replaced_count' => $report['replaced_count'],
                'created_count' => $report['created_count'],
                'skipped_count' => $report['skipped_count'],
                'removed_base_connection_count' => $report['removed_base_connection_count'],
            ]);
        }

        return $report;
    }

    /**
     * @param  array<int, string>  $excludedTimes
     * @return array<string, mixed>
     */
    protected function planForDate(string $date, int $count, array $excludedTimes): array
    {
        return (new PlanSyntheticClaimRatings(
            date: $date,
            targetCount: $count,
            createExactCount: true,
            excludedScheduledFor: $excludedTimes,
        ))->handle();
    }

    /**
     * @param  Collection<int, ClaimRating>  $ratings
     */
    protected function removeBaseConnections(Collection $ratings): int
    {
        $linkedRatings = $ratings
            ->filter(function (ClaimRating $rating): bool {
                return (bool) $rating->base_claim_rating_id
                    || (bool) $rating->base_user_id
                    || (bool) $rating->syntheticUser?->base_user_id;
            })
            ->values();

        if ($linkedRatings->isEmpty()) {
            return 0;
        }

        $connection = RegCheckDatabase::connectionName();
        $publisher = app(BaseClaimRatingPublisher::class);

        DB::connection($connection)->transaction(function () use ($connection, $linkedRatings, $publisher): void {
            foreach ($linkedRatings as $rating) {
                $publisher->retractOnConnection($rating, $connection, true);
            }
        });

        return $linkedRatings->count();
    }

    /**
     * @param  Collection<int, ClaimRating>  $ratings
     */
    private function deleteOrphanedSyntheticUsers(Collection $ratings): int
    {
        $deletedCount = 0;
        $syntheticUsers = $ratings
            ->pluck('syntheticUser')
            ->filter()
            ->unique('id');

        foreach ($syntheticUsers as $syntheticUser) {
            if ($syntheticUser->claimRatings()->exists()) {
                continue;
            }

            $syntheticUser->delete();
            $deletedCount++;
        }

        return $deletedCount;
    }

    /**
     * @param  Collection<int, ClaimRating>  $createdRatings
     * @param  Collection<int, ClaimRating>  $replacedRatings
     * @param  array<int, int>  $oldSyntheticUserIds
     */
    private function assertFreshReplacement(
        Collection $createdRatings,
        Collection $replacedRatings,
        string $date,
        int $expectedCount,
        array $oldSyntheticUserIds
    ): void {
        if ($createdRatings->count() !== $expectedCount) {
            throw new RuntimeException("Die neu geplanten Bewertungen fuer {$date} konnten nicht geladen werden.");
        }

        $oldMinutes = $replacedRatings
            ->mapWithKeys(fn (ClaimRating $rating): array => [$rating->scheduled_for->format('Y-m-d H:i') => true])
            ->all();
        $newMinutes = [];

        foreach ($createdRatings as $createdRating) {
            $scheduledMinute = $createdRating->scheduled_for?->format('Y-m-d H:i');

            if (! $scheduledMinute
                || $createdRating->scheduled_for->toDateString() !== $date
                || isset($oldMinutes[$scheduledMinute])
                || isset($newMinutes[$scheduledMinute])) {
                throw new RuntimeException("Fuer {$date} konnte keine vollstaendig neue Uhrzeit geplant werden.");
            }

            if (! $createdRating->synthetic_rating_user_id
                || ! $createdRating->syntheticUser
                || in_array((int) $createdRating->synthetic_rating_user_id, $oldSyntheticUserIds, true)) {
                throw new RuntimeException("Fuer {$date} konnte keine neue synthetische Person angelegt werden.");
            }

            $newMinutes[$scheduledMinute] = true;
        }
    }

    /**
     * @param  array<int, int|string>  $ratingIds
     * @return array<int, int>
     */
    private function normalizeIds(array $ratingIds): array
    {
        return collect($ratingIds)
            ->filter(fn (mixed $id): bool => is_int($id) || (is_string($id) && ctype_digit($id)))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
