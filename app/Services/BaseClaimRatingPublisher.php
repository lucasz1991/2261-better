<?php

namespace App\Services;

use App\Models\ClaimRating;
use App\Support\Database\RegCheckDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BaseClaimRatingPublisher
{
    public function publish(ClaimRating $rating): int
    {
        $rating->refresh();

        if ($rating->base_claim_rating_id) {
            $existingBaseRating = $this->baseRating((int) $rating->base_claim_rating_id);

            if ($existingBaseRating) {
                if (! $this->isOwnSyntheticBaseRating($existingBaseRating, $rating)) {
                    throw new \RuntimeException('Die vorhandene Base-ID gehoert nicht zu diesem synthetischen 2261-better Datensatz.');
                }

                return (int) $rating->base_claim_rating_id;
            }
        }

        if (! is_array($rating->answers) || $rating->answers === []) {
            throw new \RuntimeException('Die Bewertung hat noch keine AI-Antworten und kann nicht ausgefuehrt werden.');
        }

        $connection = RegCheckDatabase::connectionName();
        $now = now();
        $payload = $this->basePayload($rating, $now);

        return DB::connection($connection)->transaction(function () use ($connection, $rating, $payload, $now): int {
            $baseId = DB::connection($connection)
                ->table('claim_ratings')
                ->insertGetId($this->filterPayloadForTable($connection, $payload));

            $data = $rating->data ?? [];
            $data['base_publish'] = [
                'base_claim_rating_id' => $baseId,
                'published_at' => $now->toDateTimeString(),
                'connection' => $connection,
                'synthetic' => true,
                'do_not_publish' => true,
            ];

            $rating->forceFill([
                'base_claim_rating_id' => $baseId,
                'status' => ClaimRating::STATUS_RATED,
                'execution_started_at' => $rating->execution_started_at ?? $now,
                'executed_at' => $rating->executed_at ?? $now,
                'last_execution_error' => null,
                'is_public' => false,
                'data' => $data,
            ])->saveQuietly();

            return $baseId;
        });
    }

    public function retract(ClaimRating $rating): void
    {
        $rating->refresh();

        if (! $rating->base_claim_rating_id) {
            $this->resetLocalExecution($rating);

            return;
        }

        $connection = RegCheckDatabase::connectionName();
        $baseId = (int) $rating->base_claim_rating_id;

        DB::connection($connection)->transaction(function () use ($connection, $rating, $baseId): void {
            $baseRating = DB::connection($connection)
                ->table('claim_ratings')
                ->where('id', $baseId)
                ->first();

            if ($baseRating && ! $this->isOwnSyntheticBaseRating($baseRating, $rating)) {
                throw new \RuntimeException('Base-Bewertung wurde nicht geloescht, weil sie nicht als eigener synthetischer Datensatz markiert ist.');
            }

            if ($baseRating) {
                DB::connection($connection)
                    ->table('claim_ratings')
                    ->where('id', $baseId)
                    ->delete();
            }

            $this->resetLocalExecution($rating);
        });
    }

    private function baseRating(int $baseId): ?object
    {
        $connection = RegCheckDatabase::connectionName();

        return DB::connection($connection)
            ->table('claim_ratings')
            ->where('id', $baseId)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function basePayload(ClaimRating $rating, mixed $now): array
    {
        $data = $rating->data ?? [];
        $data['synthetic'] = true;
        $data['do_not_publish'] = true;
        $data['source_app'] = '2261-better';
        $data['local_claim_rating_id'] = $rating->id;

        $adminReview = $rating->admin_review ?? [];
        $adminReview['synthetic'] = true;
        $adminReview['do_not_publish'] = true;
        $adminReview['source_app'] = '2261-better';
        $adminReview['local_claim_rating_id'] = $rating->id;

        return [
            'user_id' => null,
            'insurance_subtype_id' => $rating->insurance_subtype_id,
            'insurance_type_id' => $rating->insurance_type_id,
            'rating_questionnaire_versions_id' => $rating->rating_questionnaire_versions_id,
            'insurance_id' => $rating->insurance_id,
            'answers' => json_encode($rating->answers ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => ClaimRating::STATUS_RATED,
            'attachments' => json_encode($rating->attachments ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'rating_score' => $rating->rating_score,
            'tag_ids' => json_encode($rating->tag_ids ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'moderator_comment' => 'Synthetischer interner Testdatensatz aus 2261-better. Nicht veroeffentlichen.',
            'is_public' => false,
            'admin_review' => json_encode($adminReview, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'verification_hash' => (string) Str::uuid(),
            'created_at' => $rating->scheduled_for ?? $rating->executed_at ?? $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function filterPayloadForTable(string $connection, array $payload): array
    {
        return collect($payload)
            ->filter(fn (mixed $value, string $column): bool => Schema::connection($connection)->hasColumn('claim_ratings', $column))
            ->all();
    }

    private function isOwnSyntheticBaseRating(object $baseRating, ClaimRating $localRating): bool
    {
        $data = $this->decodeJson($baseRating->data ?? null);
        $adminReview = $this->decodeJson($baseRating->admin_review ?? null);

        return ($data['synthetic'] ?? false) === true
            && ($data['source_app'] ?? null) === '2261-better'
            && (int) ($data['local_claim_rating_id'] ?? 0) === (int) $localRating->id
            && (($data['do_not_publish'] ?? false) === true || ($adminReview['do_not_publish'] ?? false) === true);
    }

    private function resetLocalExecution(ClaimRating $rating): void
    {
        $data = $rating->data ?? [];
        $data['base_publish']['retracted_at'] = now()->toDateTimeString();

        $rating->forceFill([
            'base_claim_rating_id' => null,
            'executed_at' => null,
            'execution_started_at' => null,
            'last_execution_error' => null,
            'status' => ClaimRating::STATUS_SCHEDULED,
            'is_public' => false,
            'data' => $data,
        ])->saveQuietly();
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
