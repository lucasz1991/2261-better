<?php

namespace App\Services;

use App\Models\ClaimRating;
use App\Models\SyntheticRatingUser;
use App\Support\Rating\SyntheticIdentityGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class UsernameAllocator
{
    private const NAMESPACE = 'synthetic_rating_users';

    public function __construct(private readonly SyntheticIdentityGenerator $generator) {}

    /** Acquire the shared namespace before locking users/ratings in a composite edit. */
    public function transaction(callable $callback): mixed
    {
        return $this->withLock($callback);
    }

    /**
     * The callback persists the user and any plan link within the same transaction.
     * Repeated callbacks may return the already linked identity without changing it.
     */
    public function create(array $persona, callable $persist, string $token, ?string $preferredUsername = null): SyntheticRatingUser
    {
        $this->requireRegistry();

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $history = $this->history();
            $taken = $this->takenKeys($history);
            $exists = fn (string $username): bool => isset($taken[$this->key($username)]);
            $candidate = null;

            if ($attempt === 0 && filled($preferredUsername)
                && $this->generator->isPseudonymUsername($persona, $preferredUsername)
                && ! $exists($preferredUsername)) {
                $candidate = array_merge($this->generator->describeUsername($preferredUsername), [
                    'username' => $this->key($preferredUsername),
                    'source' => 'legacy_profile',
                ]);

                if (empty($candidate['core_key']) || ! $this->generator->isUsernameCandidateAllowed($candidate, $history)) {
                    $candidate = null;
                }
            }

            $candidate ??= $this->generator->usernameCandidate($persona, $exists, $token, $history);

            $user = $this->withLock(function () use ($persist, $candidate): ?SyntheticRatingUser {
                $currentHistory = $this->history();

                if (isset($this->takenKeys($currentHistory)[$this->key($candidate['username'])])
                    || ! $this->generator->isUsernameCandidateAllowed($candidate, $currentHistory)) {
                    return null;
                }

                $metadata = $this->metadata($candidate, $candidate['source'] ?? 'generated');
                $user = $persist($metadata);

                if (! $user instanceof SyntheticRatingUser || ! $user->exists) {
                    throw new RuntimeException('Die Namensvergabe hat keine gespeicherte Person geliefert.');
                }

                // The callback may find an existing user after the lock was acquired.
                $isNewAllocation = $this->key((string) $user->username) === $this->key($candidate['username']);

                if (! $isNewAllocation) {
                    $metadata = $this->metadataFor($user);
                }

                $this->assertAvailable((string) $user->username, (int) $user->id, false);
                $this->record($user, $metadata, $isNewAllocation);

                return $user;
            });

            if ($user) {
                return $user;
            }
        }

        throw new RuntimeException('Die Namensvergabe wurde gleichzeitig verändert. Bitte den Auftrag erneut ausführen.');
    }

    /** Rename only the username, leaving the public display name and persona intact. */
    public function rename(SyntheticRatingUser $user, string $username): array
    {
        return $this->withLock(function () use ($user, $username): array {
            $fresh = SyntheticRatingUser::withTrashed()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $key = $this->key($username);
            $this->assertAvailable($key, (int) $fresh->id);

            if ($key === $this->key((string) $fresh->username)) {
                $user->setRawAttributes($fresh->getAttributes(), true);

                return $this->metadataFor($fresh);
            }

            // Retain the old username reservation even after a rename or soft deletion.
            if (filled($fresh->username)) {
                $previousKey = $this->key($fresh->username);
                $previousOwner = DB::table('synthetic_username_registry')->where('key_hash', hash('sha256', $previousKey))->first();

                if ($previousOwner && (int) $previousOwner->synthetic_rating_user_id !== (int) $fresh->id) {
                    // A legacy duplicate must be repairable without taking over the
                    // first owner's reservation. Keep the conflict as audit evidence.
                    DB::table('synthetic_username_conflicts')->insertOrIgnore([
                        'key_hash' => hash('sha256', $previousKey),
                        'owner_user_id' => $previousOwner->synthetic_rating_user_id,
                        'conflicting_user_id' => $fresh->id,
                        'detected_at' => now(),
                    ]);
                } else {
                    $this->record($fresh, $this->metadataFor($fresh));
                }
            }

            $metadata = $this->metadata(array_merge($this->generator->describeUsername($key), ['username' => $key]), 'manual');
            $data = $fresh->data ?? [];
            $data['username_generation'] = $metadata;
            $data['manual_edit'] = array_merge((array) ($data['manual_edit'] ?? []), [
                'edited_at' => now()->toDateTimeString(),
                'preserve_for_ai_generation' => true,
            ]);
            $fresh->forceFill(['username' => $key, 'data' => $data])->saveQuietly();
            $this->record($fresh, $metadata, true);
            $user->setRawAttributes($fresh->getAttributes(), true);

            return $metadata;
        });
    }

    public function assertAvailable(string $username, ?int $ignoreUserId = null, bool $validateSyntax = true): void
    {
        $this->requireRegistry();
        $key = $this->key($username);

        if ($validateSyntax && preg_match('/^[a-z0-9][a-z0-9._-]{3,41}$/', $key) !== 1) {
            throw ValidationException::withMessages(['editUserUsername' => 'Der Benutzername muss 4–42 Zeichen aus Buchstaben, Zahlen, Punkt, Unterstrich oder Bindestrich enthalten.']);
        }

        if ($key === '') {
            throw ValidationException::withMessages(['editUserUsername' => 'Bitte einen Benutzernamen eingeben.']);
        }

        $occupied = DB::table('synthetic_username_registry')->where('key_hash', hash('sha256', $key))
            ->when($ignoreUserId !== null, fn ($query) => $query->where('synthetic_rating_user_id', '<>', $ignoreUserId))->exists();

        if (! $occupied) {
            // Includes unregistered legacy rows and soft-deleted identities. Normalizing
            // in PHP keeps the contract identical across SQLite and MySQL collations.
            foreach (SyntheticRatingUser::withTrashed()->when($ignoreUserId !== null, fn ($query) => $query->whereKeyNot($ignoreUserId))->cursor(['id', 'username']) as $other) {
                if ($this->key((string) $other->username) === $key) {
                    $occupied = true;
                    break;
                }
            }
        }

        if ($occupied) {
            throw ValidationException::withMessages(['editUserUsername' => 'Dieser Benutzername ist bereits vergeben oder für eine andere Person reserviert.']);
        }
    }

    /** Read-only alternatives. A suggestion is revalidated only when saved. */
    public function suggestions(SyntheticRatingUser $user, int $count = 5): array
    {
        $history = $this->history();
        $taken = $this->takenKeys($history);
        $persona = (array) data_get($user->data, 'persona', []);
        $suggestions = [];

        for ($index = 0; $index < min(10, max(1, $count)); $index++) {
            $candidate = $this->generator->usernameCandidate($persona, fn (string $name): bool => isset($taken[$this->key($name)]), (string) Str::uuid(), $history);
            $suggestions[] = $candidate;
            $taken[$this->key($candidate['username'])] = true;
            $history[] = array_merge($candidate, ['created_at' => now()->toDateTimeString(), 'is_reserved' => true]);
        }

        return $suggestions;
    }

    /** Includes historical names, existing legacy rows, and unexecuted plan identities. */
    public function history(?int $ignoreUserId = null): array
    {
        $this->requireRegistry();
        $planningContext = $this->planningContext();
        $reservedIds = $planningContext['reserved'];
        $scheduledDays = $planningContext['days'];
        $rows = [];
        $current = [];
        $currentRows = [];
        $seen = [];

        foreach (SyntheticRatingUser::withTrashed()->orderBy('id')->cursor(['id', 'username', 'data', 'created_at', 'deleted_at']) as $user) {
            if ((int) $user->id === $ignoreUserId || blank($user->username)) {
                continue;
            }

            $current[(int) $user->id] = $this->key($user->username);
            $metadata = $this->metadataFor($user);
            $currentRows[(int) $user->id] = array_merge($metadata, [
                'username' => $user->username,
                'user_id' => (int) $user->id,
                'created_at' => $metadata['assigned_at'] ?? $user->created_at?->toDateTimeString(),
                'is_reserved' => isset($reservedIds[(int) $user->id]) && ! $user->trashed(),
                'scheduled_days' => $scheduledDays[(int) $user->id] ?? [],
            ]);
        }

        foreach (DB::table('synthetic_username_allocations')->orderBy('assigned_at')->orderBy('id')->cursor() as $reservation) {
            $userId = (int) $reservation->synthetic_rating_user_id;

            if ($userId === $ignoreUserId) {
                continue;
            }

            $metadata = $this->decode($reservation->metadata);

            if (empty($metadata['core_key'])) {
                $metadata = array_replace($metadata, $this->generator->describeUsername($reservation->username));
            }

            $key = $this->key((string) $reservation->username);
            $seen[$userId.'|'.$key] = true;
            $rows[] = array_merge($metadata, [
                'username' => $reservation->username,
                'user_id' => $userId,
                'allocation_id' => (int) $reservation->id,
                'created_at' => $reservation->assigned_at,
                'is_reserved' => isset($reservedIds[$userId]) && ($current[$userId] ?? null) === $key,
                'scheduled_days' => ($current[$userId] ?? null) === $key ? ($scheduledDays[$userId] ?? []) : [],
            ]);
        }

        foreach ($currentRows as $userId => $row) {
            if (! isset($seen[$userId.'|'.$this->key($row['username'])])) {
                $rows[] = $row;
            }
        }

        $history = array_values($rows);
        usort($history, fn (array $left, array $right): int => strcmp((string) ($left['created_at'] ?? ''), (string) ($right['created_at'] ?? ''))
            ?: (($left['allocation_id'] ?? 0) <=> ($right['allocation_id'] ?? 0)));

        return $history;
    }

    public function key(string $username): string
    {
        return Str::lower(Str::ascii(trim($username)));
    }

    private function metadataFor(SyntheticRatingUser $user): array
    {
        $stored = data_get($user->data, 'username_generation');

        if (is_array($stored) && ($stored['username_key'] ?? null) === $this->key((string) $user->username)) {
            return $stored;
        }

        return array_merge($this->generator->describeUsername((string) $user->username), ['source' => 'legacy']);
    }

    private function metadata(array $candidate, string $source): array
    {
        return array_merge($candidate, [
            'username_key' => $this->key($candidate['username']),
            'source' => $source,
            'assigned_at' => now()->toDateTimeString(),
        ]);
    }

    private function record(SyntheticRatingUser $user, array $metadata, bool $newAllocation = false): void
    {
        $key = $this->key((string) $user->username);
        $hash = hash('sha256', $key);
        $existing = DB::table('synthetic_username_registry')->where('key_hash', $hash)->first();

        if ($existing) {
            if ((int) $existing->synthetic_rating_user_id !== (int) $user->id) {
                throw ValidationException::withMessages(['editUserUsername' => 'Der Benutzername ist bereits einer anderen Person zugeordnet.']);
            }

            // An explicit return to one's own former name is a new allocation event.
            if ($newAllocation) {
                DB::table('synthetic_username_registry')->where('id', $existing->id)->update([
                    'assigned_at' => now(),
                    'metadata' => json_encode($metadata),
                    'updated_at' => now(),
                ]);
            }

        } else {
            $registryId = DB::table('synthetic_username_registry')->insertGetId([
                'key_hash' => $hash,
                'username_key' => $key,
                'username' => $user->username,
                'synthetic_rating_user_id' => $user->id,
                'core_key' => $metadata['core_key'] ?? null,
                'family_keys' => json_encode($metadata['family_keys'] ?? []),
                'metadata' => json_encode($metadata),
                'source' => $metadata['source'] ?? 'legacy',
                'assigned_at' => $metadata['assigned_at'] ?? $user->created_at ?? now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $registryId = $existing?->id ?? $registryId;

        if ($newAllocation || ! DB::table('synthetic_username_allocations')->where('registry_id', $registryId)->exists()) {
            DB::table('synthetic_username_allocations')->insert([
                'registry_id' => $registryId,
                'synthetic_rating_user_id' => $user->id,
                'username' => $user->username,
                'metadata' => json_encode($metadata),
                'assigned_at' => $metadata['assigned_at'] ?? $user->created_at ?? now(),
            ]);
        }
    }

    private function withLock(callable $callback): mixed
    {
        $this->requireRegistry();
        $connection = DB::connection();

        return $connection->transaction(function () use ($callback, $connection): mixed {
            // Acquire a write lock before any transactional read. This also avoids
            // deferred-read upgrades on SQLite, where FOR UPDATE is unavailable.
            $locked = $connection->table('synthetic_username_locks')->where('namespace', self::NAMESPACE)->increment('revision', 1, ['updated_at' => now()]);

            if ($locked !== 1) {
                throw new RuntimeException('Die Namensvergabe ist nicht initialisiert. Bitte die lokale Migration ausführen.');
            }

            return $callback();
        }, 3);
    }

    private function requireRegistry(): void
    {
        if (! Schema::hasTable('synthetic_username_registry') || ! Schema::hasTable('synthetic_username_locks') || ! Schema::hasTable('synthetic_username_allocations')) {
            throw new RuntimeException('Die sichere Namensvergabe benötigt die neue Datenbankmigration. Bitte zuerst php artisan migrate ausführen.');
        }
    }

    private function takenKeys(array $history): array
    {
        $taken = [];

        foreach ($history as $entry) {
            $taken[$this->key((string) ($entry['username'] ?? ''))] = true;
        }

        return $taken;
    }

    private function planningContext(): array
    {
        if (! Schema::hasColumns('claim_ratings', ['scheduled_for', 'executed_at', 'synthetic_rating_user_id', 'status'])) {
            return ['reserved' => [], 'days' => []];
        }

        $reserved = [];
        $days = [];

        foreach (ClaimRating::query()->whereNotNull('synthetic_rating_user_id')->whereNotNull('scheduled_for')
            ->whereNotIn('status', [ClaimRating::STATUS_RETRACTED, ClaimRating::STATUS_REJECTED])
            ->cursor(['synthetic_rating_user_id', 'scheduled_for', 'executed_at', 'status']) as $rating) {
            $userId = (int) $rating->synthetic_rating_user_id;
            $day = $rating->scheduled_for->copy()->timezone((string) config('app.timezone', 'Europe/Berlin'))->toDateString();
            $days[$userId][$day] = $day;

            if (! $rating->executed_at && in_array($rating->status, [ClaimRating::STATUS_SCHEDULED, ClaimRating::STATUS_PROCESSING, ClaimRating::STATUS_RATED, ClaimRating::STATUS_FAILED], true)) {
                $reserved[$userId] = true;
            }
        }

        return ['reserved' => $reserved, 'days' => array_map('array_values', $days)];
    }

    private function decode(mixed $value): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return is_array($decoded) ? $decoded : [];
    }
}
