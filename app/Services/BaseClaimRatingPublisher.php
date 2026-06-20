<?php

namespace App\Services;

use App\Models\ClaimRating;
use App\Models\SyntheticRatingUser;
use App\Support\Database\RegCheckDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BaseClaimRatingPublisher
{
    public function resetSyntheticUserForNewAiRun(ClaimRating $rating): void
    {
        $rating->refresh();

        $syntheticUser = $rating->syntheticUser;
        $baseUserId = (int) ($rating->base_user_id ?: ($syntheticUser?->base_user_id ?? 0));

        if ($baseUserId > 0) {
            $connection = RegCheckDatabase::connectionName();

            DB::connection($connection)->transaction(function () use ($connection, $baseUserId): void {
                $this->deleteSyntheticBaseUserIfUnused($connection, $baseUserId);
            });
        }

        if ($syntheticUser) {
            $syntheticUser->markBaseUserRetracted();
            $syntheticUser->delete();
        }

        $rating->forceFill([
            'synthetic_rating_user_id' => null,
            'base_user_id' => null,
        ])->saveQuietly();
    }

    public function publish(ClaimRating $rating): int
    {
        $rating->refresh();

        if ($rating->isRetracted()) {
            throw new \RuntimeException('Zurueckgerufene Bewertungen duerfen nicht erneut in die Base-Datenbank geschrieben werden.');
        }

        if ($rating->base_claim_rating_id) {
            $existingBaseRating = $this->baseRating((int) $rating->base_claim_rating_id);

            if ($existingBaseRating) {
                if (! $this->isOwnSyntheticBaseRating($existingBaseRating, $rating)) {
                    throw new \RuntimeException('Die vorhandene Base-ID gehoert nicht zu diesem synthetischen 2261-better Datensatz.');
                }

                $this->syncExistingBaseRating($rating, (int) $rating->base_claim_rating_id);

                return (int) $rating->base_claim_rating_id;
            }
        }

        if (! is_array($rating->answers) || $rating->answers === []) {
            throw new \RuntimeException('Die Bewertung hat noch keine AI-Antworten und kann nicht ausgefuehrt werden.');
        }

        $connection = RegCheckDatabase::connectionName();
        $now = now();

        return DB::connection($connection)->transaction(function () use ($connection, $rating, $now): int {
            $baseUserId = $this->ensureSyntheticBaseUser($connection, $rating, $now);
            $rating->refresh();
            $payload = $this->basePayload($rating, $now);
            $payload['user_id'] = $baseUserId;

            $baseId = DB::connection($connection)
                ->table('claim_ratings')
                ->insertGetId($this->filterPayloadForTable($connection, $payload));

            $data = $rating->data ?? [];
            $data['base_publish'] = [
                'base_claim_rating_id' => $baseId,
                'base_user_id' => $baseUserId,
                'synthetic_rating_user_id' => $rating->synthetic_rating_user_id,
                'published_at' => $now->toDateTimeString(),
                'connection' => $connection,
                'synthetic' => true,
                'do_not_publish' => true,
                'public_demo_visibility' => true,
            ];

            $rating->forceFill([
                'base_claim_rating_id' => $baseId,
                'base_user_id' => $baseUserId,
                'status' => ClaimRating::STATUS_RATED,
                'execution_started_at' => $rating->execution_started_at ?? $now,
                'executed_at' => $rating->executed_at ?? $now,
                'last_execution_error' => null,
                'is_public' => true,
                'data' => $data,
            ])->saveQuietly();

            return $baseId;
        });
    }

    public function retract(ClaimRating $rating): void
    {
        $rating->refresh();

        if (! $rating->base_claim_rating_id) {
            $baseUserId = (int) ($rating->base_user_id ?: ($rating->syntheticUser?->base_user_id ?? 0));

            if ($baseUserId > 0) {
                $connection = RegCheckDatabase::connectionName();
                DB::connection($connection)->transaction(function () use ($connection, $rating, $baseUserId): void {
                    $this->deleteSyntheticBaseUserIfUnused($connection, $baseUserId);
                    $this->resetLocalExecution($rating);
                });

                return;
            }

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
            $baseUserId = (int) ($rating->base_user_id ?: ($rating->syntheticUser?->base_user_id ?? ($baseRating->user_id ?? 0)));

            if ($baseRating && ! $this->isOwnSyntheticBaseRating($baseRating, $rating)) {
                throw new \RuntimeException('Base-Bewertung wurde nicht geloescht, weil sie nicht als eigener synthetischer Datensatz markiert ist.');
            }

            if ($baseRating) {
                DB::connection($connection)
                    ->table('claim_ratings')
                    ->where('id', $baseId)
                    ->delete();
            }

            $this->deleteSyntheticBaseUserIfUnused($connection, $baseUserId);
            $this->resetLocalExecution($rating);
        });
    }

    /**
     * Entfernt alle von 2261-better erzeugten synthetischen Base-Bewertungen
     * und verwaiste synthetische Testnutzer.
     *
     * @return array<string, mixed>
     */
    public function retractAll(): array
    {
        $ratingsQuery = ClaimRating::query()
            ->where(function ($query): void {
                $query->whereNotNull('base_claim_rating_id');

                if (Schema::hasColumn('claim_ratings', 'base_user_id')) {
                    $query->orWhereNotNull('base_user_id');
                }

                if (Schema::hasTable('synthetic_rating_users') && Schema::hasColumn('claim_ratings', 'synthetic_rating_user_id')) {
                    $query->orWhereIn(
                        'synthetic_rating_user_id',
                        SyntheticRatingUser::query()->whereNotNull('base_user_id')->pluck('id')
                    );
                }
            });

        $ratings = $ratingsQuery->orderBy('id')->get();

        $report = [
            'ratings_checked' => $ratings->count(),
            'ratings_retracted' => 0,
            'failed' => [],
            'orphan_users_deleted' => 0,
        ];

        foreach ($ratings as $rating) {
            try {
                $this->retract($rating);
                $report['ratings_retracted']++;
            } catch (\Throwable $exception) {
                $report['failed'][] = [
                    'claim_rating_id' => $rating->id,
                    'base_claim_rating_id' => $rating->base_claim_rating_id,
                    'base_user_id' => $rating->base_user_id,
                    'synthetic_rating_user_id' => $rating->synthetic_rating_user_id,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        $report['orphan_users_deleted'] = $this->retractOrphanLocalSyntheticUsers(RegCheckDatabase::connectionName())
            + $this->deleteOrphanSyntheticUsers(RegCheckDatabase::connectionName());

        return $report;
    }

    private function baseRating(int $baseId): ?object
    {
        $connection = RegCheckDatabase::connectionName();

        return DB::connection($connection)
            ->table('claim_ratings')
            ->where('id', $baseId)
            ->first();
    }

    private function syncExistingBaseRating(ClaimRating $rating, int $baseId): void
    {
        $connection = RegCheckDatabase::connectionName();
        $now = now();

        DB::connection($connection)->transaction(function () use ($connection, $rating, $baseId, $now): void {
            $baseUserId = $this->ensureSyntheticBaseUser($connection, $rating, $now);
            $rating->refresh();
            $payload = $this->basePayload($rating, $now);
            $payload['user_id'] = $baseUserId;
            unset($payload['created_at']);

            DB::connection($connection)
                ->table('claim_ratings')
                ->where('id', $baseId)
                ->update($this->filterPayloadForTable($connection, $payload));

            $data = $rating->data ?? [];
            $data['base_publish'] = array_merge($data['base_publish'] ?? [], [
                'base_claim_rating_id' => $baseId,
                'base_user_id' => $baseUserId,
                'synthetic_rating_user_id' => $rating->synthetic_rating_user_id,
                'synced_at' => $now->toDateTimeString(),
                'connection' => $connection,
                'synthetic' => true,
                'do_not_publish' => true,
                'public_demo_visibility' => true,
            ]);

            $rating->forceFill([
                'base_user_id' => $baseUserId,
                'status' => ClaimRating::STATUS_RATED,
                'last_execution_error' => null,
                'is_public' => true,
                'data' => $data,
            ])->saveQuietly();
        });
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
        $data['local_synthetic_rating_user_id'] = $rating->synthetic_rating_user_id;

        $adminReview = $rating->admin_review ?? [];
        $adminReview['synthetic'] = true;
        $adminReview['do_not_publish'] = true;
        $adminReview['source_app'] = '2261-better';
        $adminReview['local_claim_rating_id'] = $rating->id;
        $adminReview['local_synthetic_rating_user_id'] = $rating->synthetic_rating_user_id;

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
            'moderator_comment' => 'Synthetischer Testdatensatz aus 2261-better.',
            'is_public' => true,
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
    private function filterPayloadForTable(string $connection, array $payload, string $table = 'claim_ratings'): array
    {
        return collect($payload)
            ->filter(fn (mixed $value, string $column): bool => Schema::connection($connection)->hasColumn($table, $column))
            ->all();
    }

    private function ensureSyntheticBaseUser(string $connection, ClaimRating $rating, mixed $now): ?int
    {
        if (! Schema::connection($connection)->hasTable('users')) {
            return null;
        }

        $syntheticUser = $this->localSyntheticUser($rating);
        $baseUserId = $syntheticUser->base_user_id ?: $rating->base_user_id;

        if ($baseUserId) {
            $existingUser = DB::connection($connection)
                ->table('users')
                ->where('id', (int) $baseUserId)
                ->first();

            if ($existingUser) {
                if (! $this->isOwnSyntheticBaseUser($existingUser)) {
                    throw new \RuntimeException('Die vorhandene Base-User-ID gehoert nicht zu einem synthetischen 2261-better Testnutzer.');
                }

                $syntheticUser->markBaseUserCreated((int) $baseUserId);
                $rating->forceFill([
                    'synthetic_rating_user_id' => $syntheticUser->id,
                    'base_user_id' => (int) $baseUserId,
                ])->saveQuietly();

                return (int) $baseUserId;
            }
        }

        $existingUserByEmail = DB::connection($connection)
            ->table('users')
            ->where('email', $syntheticUser->email)
            ->first();

        if ($existingUserByEmail) {
            if (! $this->isOwnSyntheticBaseUser($existingUserByEmail)) {
                throw new \RuntimeException('Die synthetische Testnutzer-E-Mail kollidiert mit einem bestehenden Base-User.');
            }

            $syntheticUser->markBaseUserCreated((int) $existingUserByEmail->id);
            $rating->forceFill([
                'synthetic_rating_user_id' => $syntheticUser->id,
                'base_user_id' => (int) $existingUserByEmail->id,
            ])->saveQuietly();

            return (int) $existingUserByEmail->id;
        }

        $payload = [
            'name' => $syntheticUser->name,
            'first_name' => $syntheticUser->first_name,
            'last_name' => $syntheticUser->last_name,
            'username' => $syntheticUser->username ?: $syntheticUser->name,
            'email' => $syntheticUser->email,
            'email_verified_at' => $syntheticUser->email_verified_at ?? $now,
            'password' => Hash::make(Str::random(40)),
            'role' => $syntheticUser->role ?: 'guest',
            'status' => (bool) $syntheticUser->status,
            'privacy_settings' => json_encode($syntheticUser->privacySettings(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $newBaseUserId = DB::connection($connection)
            ->table('users')
            ->insertGetId($this->filterPayloadForTable($connection, $payload, 'users'));

        $syntheticUser->markBaseUserCreated((int) $newBaseUserId);
        $rating->forceFill([
            'synthetic_rating_user_id' => $syntheticUser->id,
            'base_user_id' => (int) $newBaseUserId,
        ])->saveQuietly();

        return (int) $newBaseUserId;
    }

    private function localSyntheticUser(ClaimRating $rating): SyntheticRatingUser
    {
        return SyntheticRatingUser::ensureForClaimRating($rating);
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

    private function isOwnSyntheticBaseUser(object $baseUser): bool
    {
        $email = (string) ($baseUser->email ?? '');

        if ($this->isOwnSyntheticEmail($email)) {
            return true;
        }

        $baseUserId = (int) ($baseUser->id ?? 0);

        if ($baseUserId <= 0 || $email === '' || ! Schema::hasTable('synthetic_rating_users')) {
            return false;
        }

        return SyntheticRatingUser::withTrashed()
            ->where('base_user_id', $baseUserId)
            ->where('email', $email)
            ->where('data->source_app', '2261-better')
            ->exists();
    }

    private function isOwnSyntheticEmail(string $email): bool
    {
        $domain = strtolower(trim(substr(strrchr($email, '@') ?: '', 1)));

        return str_starts_with($email, 'synthetic-2261-')
            && $domain !== ''
            && $domain !== 'regulierungs-check.de'
            && ! str_ends_with($domain, '.regulierungs-check.de');
    }

    private function deleteSyntheticBaseUserIfUnused(string $connection, int $baseUserId): void
    {
        if ($baseUserId <= 0 || ! Schema::connection($connection)->hasTable('users')) {
            return;
        }

        $baseUser = DB::connection($connection)
            ->table('users')
            ->where('id', $baseUserId)
            ->first();

        if (! $baseUser || ! $this->isOwnSyntheticBaseUser($baseUser)) {
            if (! $baseUser) {
                $this->markLocalSyntheticUsersRetracted($baseUserId);
            }

            return;
        }

        // Zähle nur 2261-better Bewertungen, nicht andere Bewertungen des Users
        $ratingsCount = Schema::connection($connection)->hasTable('claim_ratings')
            ? DB::connection($connection)
                ->table('claim_ratings')
                ->where('user_id', $baseUserId)
                ->where($this->sourceAppJsonWhere())
                ->count()
            : 0;

        if ($ratingsCount === 0) {
            DB::connection($connection)
                ->table('users')
                ->where('id', $baseUserId)
                ->delete();

            $this->markLocalSyntheticUsersRetracted($baseUserId);
        }
    }

    private function markLocalSyntheticUsersRetracted(int $baseUserId): void
    {
        if (! Schema::hasTable('synthetic_rating_users')) {
            return;
        }

        SyntheticRatingUser::query()
            ->where('base_user_id', $baseUserId)
            ->get()
            ->each(fn (SyntheticRatingUser $syntheticUser): mixed => $syntheticUser->markBaseUserRetracted());
    }

    private function deleteOrphanSyntheticUsers(string $connection): int
    {
        if (! Schema::connection($connection)->hasTable('users')) {
            return 0;
        }

        $users = DB::connection($connection)
            ->table('users')
            ->where('email', 'like', 'synthetic-2261-%@%')
            ->get(['id', 'email', 'name']);

        $deleted = 0;

        foreach ($users as $user) {
            // Zähle nur 2261-better Bewertungen
            $ratingsCount = Schema::connection($connection)->hasTable('claim_ratings')
                ? DB::connection($connection)
                    ->table('claim_ratings')
                    ->where('user_id', (int) $user->id)
                    ->where($this->sourceAppJsonWhere())
                    ->count()
                : 0;

            if ($ratingsCount > 0 || ! $this->isOwnSyntheticBaseUser($user)) {
                continue;
            }

            DB::connection($connection)
                ->table('users')
                ->where('id', (int) $user->id)
                ->delete();

            $deleted++;
        }

        return $deleted;
    }

    private function retractOrphanLocalSyntheticUsers(string $connection): int
    {
        if (! Schema::hasTable('synthetic_rating_users')) {
            return 0;
        }

        $deleted = 0;

        SyntheticRatingUser::query()
            ->whereNotNull('base_user_id')
            ->orderBy('id')
            ->get()
            ->each(function (SyntheticRatingUser $syntheticUser) use ($connection, &$deleted): void {
                $baseUserId = (int) $syntheticUser->base_user_id;
                $localRatingsCount = ClaimRating::query()
                    ->where('synthetic_rating_user_id', $syntheticUser->id)
                    ->where(function ($query): void {
                        $query
                            ->whereNotNull('base_claim_rating_id')
                            ->orWhereNotNull('executed_at');
                    })
                    ->count();

                if ($localRatingsCount > 0) {
                    return;
                }

                $this->deleteSyntheticBaseUserIfUnused($connection, $baseUserId);

                if (! $syntheticUser->fresh()?->base_user_id) {
                    $deleted++;
                }
            });

        return $deleted;
    }

    private function resetLocalExecution(ClaimRating $rating): void
    {
        $data = $rating->data ?? [];
        $data['base_publish']['retracted_at'] = now()->toDateTimeString();
        $data['execution_control'] = array_merge($data['execution_control'] ?? [], [
            'manual_only_after_retract' => true,
            'manual_only_since' => now()->toDateTimeString(),
            'manual_only_reason' => 'base_retracted',
        ]);

        if ($rating->syntheticUser?->base_user_id) {
            $rating->syntheticUser->markBaseUserRetracted();
        }

        $rating->forceFill([
            'base_claim_rating_id' => null,
            'base_user_id' => null,
            'executed_at' => null,
            'execution_started_at' => null,
            'last_execution_error' => null,
            'status' => ClaimRating::STATUS_RETRACTED,
            'is_public' => false,
            'data' => $data,
        ])->saveQuietly();
    }

    private function sourceAppJsonWhere(): \Closure
    {
        return function ($query): void {
            $query
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.source_app')) = '2261-better'")
                ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(admin_review, '$.source_app')) = '2261-better'");
        };
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
