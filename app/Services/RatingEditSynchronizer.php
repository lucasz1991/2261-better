<?php

namespace App\Services;

use App\Exceptions\RatingEditConflict;
use App\Models\ClaimRating;
use App\Models\RatingEditRevision;
use App\Models\SyntheticRatingUser;
use App\Support\Database\RegCheckDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Applies explicit field changes, never the publication payload. */
class RatingEditSynchronizer
{
    public function __construct(private ?string $baseConnection = null) {}

    public function capture(ClaimRating $rating): array
    {
        if (! $rating->base_claim_rating_id) {
            return [];
        }

        $connection = $this->connection();

        return DB::connection($connection)->transaction(function () use ($connection, $rating): array {
            [$baseRating, $baseUser, $user] = $this->ownedRecords($connection, $rating);
            [$mapping, $customer, $canCreate] = $this->profileMapping($connection, $baseUser);
            $values = [];
            foreach ($mapping as $field => $table) {
                $record = $table === 'users' ? $baseUser : $customer;
                $values[$field] = $table ? ($record?->{$field} ?? null) : null;
            }

            return [
                'rating_id' => (int) $baseRating->id,
                'user_id' => (int) $baseUser->id,
                'customer_id' => $customer?->id,
                'mapping' => $mapping,
                'can_create_customer' => $canCreate,
                'rating' => $this->ratingValues($baseRating),
                'user' => $values,
                'privacy_name_visibility' => data_get($this->decode($baseUser->privacy_settings ?? null), 'ratings.name_visibility', 'none'),
                'captured_at' => now()->toIso8601String(),
            ];
        });
    }

    public function synchronize(RatingEditRevision $revision): RatingEditRevision
    {
        if (in_array($revision->status, ['local', 'synced', 'superseded', 'conflict'], true)) {
            return $revision;
        }

        // Keep the local outbox lock until the Base transaction is committed. If
        // confirmation fails, changed values themselves make a retry idempotent.
        try {
            DB::transaction(function () use ($revision): void {
                $user = SyntheticRatingUser::query()->lockForUpdate()->find($revision->synthetic_rating_user_id);
                $rating = ClaimRating::query()->lockForUpdate()->find($revision->claim_rating_id);
                $entry = RatingEditRevision::query()->lockForUpdate()->findOrFail($revision->id);
                if (! in_array($entry->status, ['pending', 'failed'], true)) {
                    return;
                }
                if (! $rating || ! $user || $rating->isRetracted()) {
                    throw new RatingEditConflict('Bewertung oder Benutzer ist nicht mehr für diese Änderung verfügbar.');
                }
                if ((int) data_get($rating->data, 'edit_revision', 0) !== (int) $entry->rating_revision
                    || (int) data_get($user->data, 'edit_revision', 0) !== (int) $entry->user_revision) {
                    $entry->update(['status' => 'superseded']);

                    return;
                }
                $entry->increment('attempts');
                $connection = $this->connection();
                DB::connection($connection)->transaction(function () use ($connection, $entry, $rating, $user): void {
                    $this->applyOnBase($connection, $entry, $rating, $user);
                });
                $entry->update(['status' => 'synced', 'last_error' => null, 'synced_at' => now()]);
                $this->markLocal($rating, $user, $entry, 'synced');
            }, 3);
        } catch (\Throwable $error) {
            $status = $error instanceof RatingEditConflict ? 'conflict' : 'failed';
            $message = $error instanceof RatingEditConflict
                ? $error->getMessage()
                : 'Die Base-Übertragung ist derzeit nicht möglich. Lokal gespeichert; ein erneuter Versuch ist möglich.';
            DB::transaction(function () use ($revision, $status, $message): void {
                $user = SyntheticRatingUser::query()->lockForUpdate()->find($revision->synthetic_rating_user_id);
                $rating = ClaimRating::query()->lockForUpdate()->find($revision->claim_rating_id);
                $entry = RatingEditRevision::query()->lockForUpdate()->find($revision->id);
                if (! $entry || ! in_array($entry->status, ['pending', 'failed'], true)) {
                    return;
                }
                $entry->update(['status' => $status, 'last_error' => $message, 'attempts' => $entry->attempts + 1]);
                if ($rating && $user) {
                    $this->markLocal($rating, $user, $entry, $status, $message);
                }
            }, 3);
        }

        return $revision->fresh();
    }

    private function applyOnBase(string $connection, RatingEditRevision $entry, ClaimRating $rating, SyntheticRatingUser $user): void
    {
        [$baseRating, $baseUser] = $this->ownedRecords($connection, $rating);
        $snapshot = $entry->base_snapshot ?? [];
        if ((int) ($snapshot['rating_id'] ?? 0) !== (int) $baseRating->id
            || (int) ($snapshot['user_id'] ?? 0) !== (int) $baseUser->id) {
            throw new RatingEditConflict('Die Base-Zuordnung wurde seit dem Öffnen geändert.');
        }

        [$mapping, $customer, $canCreate] = $this->profileMapping($connection, $baseUser);
        if ($mapping !== ($snapshot['mapping'] ?? [])) {
            throw new RatingEditConflict('Das Base-Profilmodell wurde geändert. Bitte die Bewertung neu öffnen.');
        }
        $changes = $entry->changes ?? [];
        $userChanges = $changes['user'] ?? [];
        $ratingChanges = $changes['rating'] ?? [];
        $userPayload = [];
        $customerPayload = [];
        foreach ($userChanges as $field => $change) {
            if (! in_array($field, ['name', 'username', 'first_name', 'last_name'], true) || ! ($mapping[$field] ?? null)) {
                throw new RatingEditConflict('Ein geändertes Benutzerfeld wird vom Base-Schema nicht unterstützt.');
            }
            $table = $mapping[$field];
            $current = $table === 'users' ? ($baseUser->{$field} ?? null) : ($customer?->{$field} ?? null);
            $before = $snapshot['user'][$field] ?? null;
            $after = $change['after'];
            $this->assertUnchangedOrApplied($current, $before, $after, 'Benutzerfeld '.$field);
            if ($table === 'users') {
                $userPayload[$field] = $after;
            } else {
                $customerPayload[$field] = $after ?? '';
            }
        }
        if (array_key_exists('username', $userChanges)) {
            $username = (string) $userChanges['username']['after'];
            $table = $mapping['username'];
            $query = DB::connection($connection)->table($table)->whereRaw('LOWER(username) = ?', [strtolower($username)]);
            $query->where('id', '!=', $table === 'users' ? $baseUser->id : ($customer?->id ?? 0));
            if ($query->exists()) {
                throw new RatingEditConflict('Dieser Benutzername ist in Base bereits vergeben.');
            }
        }

        if ($customerPayload !== []) {
            if (! $customer) {
                if (! $canCreate || ! empty($snapshot['customer_id'])) {
                    throw new RatingEditConflict('Das zugehörige Kundenprofil fehlt oder kann nicht sicher angelegt werden.');
                }
                $seed = $changes['user_after'] ?? [];
                $payload = [
                    'user_id' => $baseUser->id,
                    'first_name' => (string) ($seed['first_name'] ?? ''),
                    'last_name' => (string) ($seed['last_name'] ?? ''),
                    'username' => (string) ($seed['username'] ?? ''),
                    ...$customerPayload,
                ];
                if (DB::connection($connection)->table('customers')->whereRaw('LOWER(username) = ?', [strtolower($payload['username'])])->exists()) {
                    throw new RatingEditConflict('Der Benutzername für das neue Base-Kundenprofil ist bereits vergeben.');
                }
                $this->assertCustomerInsertSupported($connection, $payload);
                $this->addTimestamps($connection, 'customers', $payload, true);
                DB::connection($connection)->table('customers')->insert($payload);
            } else {
                // A retry after Base commit may find the profile created by this
                // operation; only accept its values if they already match.
                if (! empty($snapshot['customer_id']) && (int) $snapshot['customer_id'] !== (int) $customer->id) {
                    throw new RatingEditConflict('Das zugehörige Kundenprofil wurde ausgetauscht.');
                }
                if (empty($snapshot['customer_id'])) {
                    foreach (['username', 'first_name', 'last_name'] as $field) {
                        if ((string) ($customer->{$field} ?? '') !== (string) data_get($changes, 'user_after.'.$field, '')) {
                            throw new RatingEditConflict('Zwischenzeitlich wurde ein abweichendes Kundenprofil angelegt. Bitte abgleichen.');
                        }
                    }
                }
                $this->addTimestamps($connection, 'customers', $customerPayload);
                DB::connection($connection)->table('customers')->where('id', $customer->id)->update($customerPayload);
            }
        }
        if ($userPayload !== []) {
            $this->addTimestamps($connection, 'users', $userPayload);
            DB::connection($connection)->table('users')->where('id', $baseUser->id)->update($userPayload);
        }

        $currentValues = $this->ratingValues($baseRating);
        $ratingPayload = [];
        foreach ($ratingChanges as $field => $change) {
            if (! in_array($field, ['rating_score', 'summary', 'regulation_comment'], true)) {
                continue; // Local scheduling is deliberately never synchronized.
            }
            $this->assertUnchangedOrApplied($currentValues[$field] ?? null, $snapshot['rating'][$field] ?? null, $change['after'], 'Bewertungsfeld '.$field);
            if ($field === 'summary') {
                $attachments = $this->decode($baseRating->attachments ?? null);
                data_set($attachments, 'scorings.ai_overall_comment', $change['after']);
                $ratingPayload['attachments'] = $this->json($attachments);
            } elseif ($field === 'regulation_comment') {
                $answers = $this->decode($baseRating->answers ?? null);
                $answers['regulationComment'] = $change['after'];
                $ratingPayload['answers'] = $this->json($answers);
            } else {
                $ratingPayload[$field] = $change['after'];
            }
        }
        if ($ratingPayload !== []) {
            $this->addTimestamps($connection, 'claim_ratings', $ratingPayload);
            DB::connection($connection)->table('claim_ratings')->where('id', $baseRating->id)->update($ratingPayload);
        }
    }

    private function ownedRecords(string $connection, ClaimRating $rating): array
    {
        $user = $rating->syntheticUser;
        if (! $user || ! (bool) data_get($user->data, 'synthetic') || data_get($user->data, 'source_app') !== '2261-better') {
            throw new RatingEditConflict('Der Benutzer ist keinem eigenen synthetischen Datensatz zugeordnet.');
        }
        $baseUserId = (int) ($user->base_user_id ?: $rating->base_user_id);
        if (! $baseUserId || ($rating->base_user_id && (int) $rating->base_user_id !== $baseUserId)
            || ! $rating->base_claim_rating_id) {
            throw new RatingEditConflict('Die Base-Zuordnung ist unvollständig oder widersprüchlich.');
        }
        $baseRating = DB::connection($connection)->table('claim_ratings')->where('id', $rating->base_claim_rating_id)->lockForUpdate()->first();
        $baseUser = DB::connection($connection)->table('users')->where('id', $baseUserId)->lockForUpdate()->first();
        $data = $this->decode($baseRating?->data ?? null);
        if (! $baseRating || ! $baseUser || ! empty($baseRating->deleted_at) || ! empty($baseUser->deleted_at)
            || (int) $baseRating->user_id !== $baseUserId
            || ($data['synthetic'] ?? false) !== true
            || ($data['source_app'] ?? null) !== '2261-better'
            || (int) ($data['local_claim_rating_id'] ?? 0) !== (int) $rating->id
            || (isset($data['local_synthetic_rating_user_id']) && (int) $data['local_synthetic_rating_user_id'] !== (int) $user->id)
            || (string) $baseUser->email !== (string) $user->email) {
            throw new RatingEditConflict('Die verknüpfte Base-Bewertung oder der Benutzer gehört nicht eindeutig zu diesem 2261-Datensatz.');
        }

        return [$baseRating, $baseUser, $user];
    }

    private function profileMapping(string $connection, object $baseUser): array
    {
        $columns = Schema::connection($connection)->getColumnListing('users');
        $mapping = ['name' => in_array('name', $columns, true) ? 'users' : null];
        $customer = null;
        $customerColumns = [];
        if (Schema::connection($connection)->hasTable('customers')) {
            $customerColumns = Schema::connection($connection)->getColumnListing('customers');
            if (in_array('user_id', $customerColumns, true)) {
                $customers = DB::connection($connection)->table('customers')->where('user_id', $baseUser->id)->lockForUpdate()->get();
                if ($customers->count() > 1 || ($customers->first() && ! empty($customers->first()->deleted_at))) {
                    throw new RatingEditConflict('Die Kundenprofil-Zuordnung ist nicht eindeutig oder wurde gelöscht.');
                }
                $customer = $customers->first();
            }
        }
        foreach (['username', 'first_name', 'last_name'] as $field) {
            // The repository's canonical profile fields live in customers.
            // Optional legacy user columns are a fallback only when no supported
            // customer field exists; never silently write the wrong copy.
            $mapping[$field] = in_array('user_id', $customerColumns, true) && in_array($field, $customerColumns, true) ? 'customers'
                : (in_array($field, $columns, true) ? 'users' : null);
        }
        $canCreate = ! $customer && count(array_intersect(['user_id', 'first_name', 'last_name', 'username'], $customerColumns)) === 4;

        return [$mapping, $customer, $canCreate];
    }

    private function assertCustomerInsertSupported(string $connection, array $payload): void
    {
        foreach (Schema::connection($connection)->getColumns('customers') as $column) {
            if (! array_key_exists($column['name'], $payload)
                && ! in_array($column['name'], ['created_at', 'updated_at'], true)
                && ! ($column['nullable'] ?? false)
                && ($column['default'] ?? null) === null
                && ! ($column['auto_increment'] ?? false)) {
                throw new RatingEditConflict('Das Base-Kundenprofil benötigt zusätzliche Pflichtfelder. Die Änderung wurde nicht übertragen.');
            }
        }
    }

    private function ratingValues(object $rating): array
    {
        return [
            'rating_score' => $rating->rating_score === null ? null : number_format((float) $rating->rating_score, 2, '.', ''),
            'summary' => (string) data_get($this->decode($rating->attachments ?? null), 'scorings.ai_overall_comment', ''),
            'regulation_comment' => data_get($this->decode($rating->answers ?? null), 'regulationComment'),
        ];
    }

    private function assertUnchangedOrApplied(mixed $current, mixed $before, mixed $after, string $label): void
    {
        if ($current !== $before && $current !== $after && (string) $current !== (string) $before && (string) $current !== (string) $after) {
            throw new RatingEditConflict($label.' wurde zwischenzeitlich in Base geändert. Bitte vor einer weiteren Änderung abgleichen.');
        }
    }

    private function markLocal(ClaimRating $rating, SyntheticRatingUser $user, RatingEditRevision $entry, string $status, ?string $error = null): void
    {
        foreach ([$rating, $user] as $model) {
            $data = $model->data ?? [];
            if ((int) data_get($data, 'edit_sync.revision_id') !== (int) $entry->id) {
                continue;
            }
            $data['edit_sync'] = array_merge($data['edit_sync'] ?? [], [
                'status' => $status,
                'error' => $error,
                'synced_at' => $status === 'synced' ? now()->toIso8601String() : null,
            ]);
            $model->forceFill(['data' => $data])->saveQuietly();
        }
    }

    private function addTimestamps(string $connection, string $table, array &$payload, bool $create = false): void
    {
        $columns = Schema::connection($connection)->getColumnListing($table);
        if (in_array('updated_at', $columns, true)) {
            $payload['updated_at'] = now();
        }
        if ($create && in_array('created_at', $columns, true)) {
            $payload['created_at'] = now();
        }
    }

    protected function connection(): string
    {
        return $this->baseConnection ?? RegCheckDatabase::connectionName();
    }

    private function decode(mixed $value): array
    {
        $decoded = is_array($value) ? $value : json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
