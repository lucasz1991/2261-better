<?php

namespace App\Services;

use App\Models\ClaimRating;
use App\Models\RatingEditRevision;
use App\Models\SyntheticRatingUser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RatingEditService
{
    public function __construct(private RatingEditSynchronizer $synchronizer) {}

    public static function versionToken(Model $model): string
    {
        return hash('sha256', serialize($model->getRawOriginal()));
    }

    public static function editable(ClaimRating $rating): bool
    {
        return $rating->status !== ClaimRating::STATUS_PROCESSING
            && ! $rating->isRetracted()
            && (bool) data_get($rating->data, 'synthetic')
            && $rating->syntheticUser
            && (bool) data_get($rating->syntheticUser->data, 'synthetic')
            && data_get($rating->syntheticUser->data, 'source_app') === '2261-better';
    }

    public function save(int $ratingId, array $input, string $ratingToken, string $userToken, array $baseSnapshot): RatingEditRevision
    {
        Gate::authorize('ratings.edit');
        if (! Schema::hasTable('rating_edit_revisions')) {
            throw ValidationException::withMessages(['editReason' => 'Die lokale Bearbeitungsmigration ist noch nicht ausgeführt.']);
        }
        $values = Validator::make($input, [
            'editUserName' => ['required', 'string', 'max:191'],
            'editUserUsername' => ['required', 'string', 'regex:/^[a-z0-9][a-z0-9._-]{3,41}$/'],
            'editUserFirstName' => ['nullable', 'string', 'max:100'],
            'editUserLastName' => ['nullable', 'string', 'max:100'],
            'editRatingScore' => ['nullable', 'numeric', 'between:0,1'],
            'editSummary' => ['nullable', 'string', 'max:10000'],
            'editRegulationComment' => ['nullable', 'string', 'max:10000'],
            'editScheduledFor' => ['nullable', 'date'],
            'editReason' => ['required', 'string', 'min:5', 'max:1000'],
            'editContentConfirmed' => ['boolean'],
        ])->validate();

        $revision = app(UsernameAllocator::class)->transaction(function () use ($ratingId, $values, $ratingToken, $userToken, $baseSnapshot): RatingEditRevision {
            $userId = ClaimRating::query()->whereKey($ratingId)->value('synthetic_rating_user_id');
            $user = SyntheticRatingUser::query()->lockForUpdate()->find($userId);
            $relatedRatings = ClaimRating::query()->where('synthetic_rating_user_id', $userId)->orderBy('id')->lockForUpdate()->get();
            $rating = $relatedRatings->firstWhere('id', $ratingId);
            if (! $rating || ! $user) {
                throw ValidationException::withMessages(['editReason' => 'Die Benutzerzuordnung wurde geändert. Bitte neu öffnen.']);
            }
            $rating->setRelation('syntheticUser', $user);
            if (! self::editable($rating)) {
                throw ValidationException::withMessages(['editReason' => 'Dieser Datensatz kann aktuell nicht bearbeitet werden.']);
            }
            $published = (bool) ($rating->executed_at || $rating->base_claim_rating_id);
            if ($published) {
                Gate::authorize('ratings.edit_published');
            }
            if (! hash_equals(self::versionToken($rating), $ratingToken) || ! hash_equals(self::versionToken($user), $userToken)) {
                throw ValidationException::withMessages(['editReason' => 'Bewertung oder Benutzer wurden zwischenzeitlich geändert. Bitte neu öffnen und die Änderungen prüfen.']);
            }
            if (RatingEditRevision::query()->whereIn('status', ['pending', 'failed'])->where(function ($query) use ($rating, $user): void {
                $query->where('claim_rating_id', $rating->id)->orWhere('synthetic_rating_user_id', $user->id);
            })->exists()) {
                throw ValidationException::withMessages(['editReason' => 'Für diesen Benutzer ist noch eine Übertragung oder ein Konflikt offen. Bitte zuerst im Änderungsverlauf klären.']);
            }
            $conflicts = RatingEditRevision::query()->where('synthetic_rating_user_id', $user->id)->where('status', 'conflict')->orderBy('id')->lockForUpdate()->get();
            if ($conflicts->contains(fn ($entry) => (int) $entry->claim_rating_id !== (int) $rating->id)) {
                throw ValidationException::withMessages(['editReason' => 'Bitte zuerst die Bewertung mit dem offenen Konflikt öffnen und dort den Zielwert korrigieren oder die Änderung verwerfen.']);
            }
            if ($rating->base_claim_rating_id && (int) ($baseSnapshot['rating_id'] ?? 0) !== (int) $rating->base_claim_rating_id) {
                throw ValidationException::withMessages(['editReason' => 'Die Base-Daten konnten nicht sicher geladen werden. Bitte die Bewertung bei erreichbarer Base neu öffnen.']);
            }

            $userBefore = $this->userValues($user);
            $userAfter = [
                'name' => trim($values['editUserName']),
                'username' => trim($values['editUserUsername']),
                'first_name' => trim($values['editUserFirstName'] ?? '') ?: null,
                'last_name' => trim($values['editUserLastName'] ?? '') ?: null,
            ];
            if ($userAfter['name'] === '') {
                throw ValidationException::withMessages(['editUserName' => 'Bitte einen Anzeigenamen eingeben.']);
            }
            $userChanges = $this->changes($userBefore, $userAfter);
            // A corrected target supersedes the blocked revision while retaining
            // its other still-unsynchronized fields. Otherwise a new username
            // could silently discard an earlier pending display-name edit.
            foreach ($conflicts as $conflict) {
                foreach (($conflict->changes['user'] ?? []) as $field => $change) {
                    $userChanges[$field] ??= ['before' => $change['before'], 'after' => $userAfter[$field]];
                }
            }
            if ($userChanges !== []) {
                Gate::authorize('synthetic_users.edit');
                if ($user->base_user_id && ! $rating->base_claim_rating_id) {
                    throw ValidationException::withMessages(['editUserName' => 'Der Benutzer hat eine Base-Verknüpfung ohne zugehörige Base-Bewertung. Bitte die Zuordnung zuerst prüfen.']);
                }
                if ($rating->base_claim_rating_id) {
                    foreach (array_keys($userChanges) as $field) {
                        if (! ($baseSnapshot['mapping'][$field] ?? null)) {
                            throw ValidationException::withMessages(['editUserUsername' => 'Das Feld '.$field.' hat im vorhandenen Base-Schema noch kein unterstütztes Ziel.']);
                        }
                    }
                }
            }
            $ratingBefore = $this->ratingValues($rating);
            $scheduleUnchanged = ($values['editScheduledFor'] ?? '') === ($rating->scheduled_for?->format('Y-m-d\TH:i') ?? '');
            $ratingAfter = [
                'rating_score' => ($values['editRatingScore'] ?? '') === '' || $values['editRatingScore'] === null
                    ? null : number_format((float) $values['editRatingScore'], 2, '.', ''),
                'summary' => (string) ($values['editSummary'] ?? ''),
                'regulation_comment' => trim($values['editRegulationComment'] ?? '') ?: null,
                'scheduled_for' => $published || $scheduleUnchanged ? $ratingBefore['scheduled_for'] : (! empty($values['editScheduledFor']) ? Carbon::parse($values['editScheduledFor'])->toDateTimeString() : null),
            ];
            if (! $published && $ratingAfter['scheduled_for'] !== $ratingBefore['scheduled_for'] && $ratingAfter['scheduled_for'] && Carbon::parse($ratingAfter['scheduled_for'])->isPast()) {
                throw ValidationException::withMessages(['editScheduledFor' => 'Der neue Ausführungszeitpunkt muss in der Zukunft liegen.']);
            }
            $ratingChanges = $this->changes($ratingBefore, $ratingAfter);
            foreach ($conflicts as $conflict) {
                foreach (($conflict->changes['rating'] ?? []) as $field => $change) {
                    $ratingChanges[$field] ??= ['before' => $change['before'], 'after' => $ratingAfter[$field]];
                }
            }
            if ($userChanges === [] && $ratingChanges === []) {
                throw ValidationException::withMessages(['editReason' => 'Es wurden keine Änderungen vorgenommen.']);
            }
            if (array_intersect(array_keys($ratingChanges), ['summary', 'rating_score', 'regulation_comment']) !== [] && ! ($values['editContentConfirmed'] ?? false)) {
                throw ValidationException::withMessages(['editContentConfirmed' => 'Bitte bestätigen, dass Bewertungstext, Kommentar und Score fachlich zusammenpassen.']);
            }

            if (isset($userChanges['username'])) {
                app(UsernameAllocator::class)->rename($user, $userAfter['username']);
                $user->refresh();
            }
            $userData = $user->data ?? [];
            $userRevision = (int) data_get($userData, 'edit_revision', 0) + 1;
            if ($userChanges !== []) {
                data_set($userData, 'persona.first_name', $userAfter['first_name']);
                data_set($userData, 'persona.last_name', $userAfter['last_name']);
                data_set($userData, 'persona.display_name', trim(($userAfter['first_name'] ?? '').' '.($userAfter['last_name'] ?? '')) ?: $userAfter['name']);
                $userData['manual_edit'] = array_merge($userData['manual_edit'] ?? [], [
                    'edited_at' => now()->toIso8601String(), 'actor_id' => auth()->id(), 'preserve_for_ai_generation' => true,
                    'changed_fields' => array_values(array_unique(array_merge((array) data_get($userData, 'manual_edit.changed_fields', []), array_keys($userChanges)))),
                ]);
            }
            $userData['edit_revision'] = $userRevision;
            $user->forceFill([...$userAfter, 'data' => $userData])->saveQuietly();
            $ratingData = $rating->data ?? [];
            $ratingRevision = (int) data_get($ratingData, 'edit_revision', 0) + 1;
            $ratingData['edit_revision'] = $ratingRevision;
            $ratingData['manual_edit'] = array_merge($ratingData['manual_edit'] ?? [], [
                'edited_at' => now()->toIso8601String(), 'actor_id' => auth()->id(), 'preserve_for_ai_generation' => true,
                'protected_fields' => array_values(array_unique(array_merge(
                    data_get($ratingData, 'manual_edit.protected_fields', []),
                    array_values(array_intersect(array_keys($ratingChanges), ['summary', 'rating_score', 'regulation_comment']))
                ))),
            ]);
            $ratingData['planning']['synthetic_user_profile'] = $user->publicProfile();
            $attachments = $rating->attachments ?? [];
            if (isset($ratingChanges['summary'])) {
                data_set($attachments, 'scorings.ai_overall_comment', $ratingAfter['summary']);
            }
            $answers = $rating->answers ?? [];
            if (isset($ratingChanges['regulation_comment'])) {
                $answers['regulationComment'] = $ratingAfter['regulation_comment'];
            }
            $revision = RatingEditRevision::create([
                'operation_id' => (string) Str::uuid(),
                'claim_rating_id' => $rating->id,
                'synthetic_rating_user_id' => $user->id,
                'actor_id' => auth()->id(),
                'rating_revision' => $ratingRevision,
                'user_revision' => $userRevision,
                'reason' => trim($values['editReason']),
                'changes' => ['rating' => $ratingChanges, 'user' => $userChanges, 'user_after' => $userAfter, 'supersedes_revision_ids' => $conflicts->pluck('id')->all()],
                'base_snapshot' => $baseSnapshot ?: null,
                'status' => $rating->base_claim_rating_id ? 'pending' : 'local',
            ]);
            $sync = ['status' => $revision->status, 'revision_id' => $revision->id, 'synced_at' => null, 'error' => null];
            $ratingData['edit_sync'] = $sync;
            $userData = $user->data ?? [];
            if ($userChanges !== []) {
                $userData['edit_sync'] = $sync;
            }
            $user->forceFill(['data' => $userData])->saveQuietly();
            $rating->forceFill([
                'data' => $ratingData,
                'rating_score' => $ratingAfter['rating_score'],
                'scheduled_for' => $ratingAfter['scheduled_for'],
                'answers' => $answers,
                'attachments' => $attachments,
            ])->saveQuietly();
            foreach ($conflicts as $conflict) {
                $conflict->update(['status' => 'superseded', 'last_error' => 'Zielwerte wurden in Änderung #'.$revision->id.' korrigiert.']);
            }

            // Keep existing snapshots for the same person consistent without
            // touching another rating's text, score or publication state.
            if ($userChanges !== []) {
                $relatedRatings->where('id', '!=', $rating->id)->each(function (ClaimRating $other) use ($user): void {
                    $data = $other->data ?? [];
                    $data['planning']['synthetic_user_profile'] = $user->publicProfile();
                    $other->forceFill(['data' => $data])->saveQuietly();
                });
            }

            return $revision;
        });

        return $revision->status === 'pending' ? $this->synchronizer->synchronize($revision) : $revision;
    }

    public function resolveConflict(RatingEditRevision $revision, array $snapshot, string $reason): RatingEditRevision
    {
        Gate::authorize('ratings.edit');
        Gate::authorize('ratings.edit_published');
        if (! empty($revision->changes['user'])) {
            Gate::authorize('synthetic_users.edit');
        }
        Validator::make(['editReason' => $reason], ['editReason' => 'required|string|min:5|max:1000'])->validate();
        DB::transaction(function () use ($revision, $snapshot, $reason): void {
            $user = SyntheticRatingUser::query()->lockForUpdate()->findOrFail($revision->synthetic_rating_user_id);
            $rating = ClaimRating::query()->lockForUpdate()->findOrFail($revision->claim_rating_id);
            $entry = RatingEditRevision::query()->lockForUpdate()->findOrFail($revision->id);
            if ($entry->status !== 'conflict' || ! self::editable($rating)
                || (int) data_get($rating->data, 'edit_revision') !== (int) $entry->rating_revision
                || (int) data_get($user->data, 'edit_revision') !== (int) $entry->user_revision
                || (int) ($snapshot['rating_id'] ?? 0) !== (int) $rating->base_claim_rating_id) {
                throw ValidationException::withMessages(['editReason' => 'Dieser Konflikt kann in diesem Stand nicht aufgelöst werden. Bitte neu öffnen.']);
            }
            $current = $this->synchronizer->capture($rating);
            // Compare the displayed remote values with a fresh capture before
            // treating the user's explicit acknowledgement as authoritative.
            foreach (['rating_id', 'user_id', 'customer_id', 'mapping', 'rating', 'user'] as $key) {
                if (($current[$key] ?? null) !== ($snapshot[$key] ?? null)) {
                    throw ValidationException::withMessages(['editReason' => 'Die Base-Daten wurden erneut geändert. Bitte neu öffnen und erneut vergleichen.']);
                }
            }
            $changes = $entry->changes;
            $changes['conflict_resolutions'][] = [
                'actor_id' => auth()->id(), 'at' => now()->toIso8601String(), 'reason' => trim($reason),
                'previous_snapshot' => $entry->base_snapshot, 'accepted_snapshot' => $current,
            ];
            $entry->update(['changes' => $changes, 'base_snapshot' => $current, 'status' => 'pending', 'last_error' => null]);
        }, 3);

        return $this->synchronizer->synchronize($revision->fresh());
    }

    /** Discard an unapplied conflicting request; never undo an external edit. */
    public function discardConflict(RatingEditRevision $revision, string $reason): RatingEditRevision
    {
        Gate::authorize('ratings.edit');
        Gate::authorize('ratings.edit_published');
        if (! empty($revision->changes['user'])) {
            Gate::authorize('synthetic_users.edit');
        }
        Validator::make(['editReason' => $reason], ['editReason' => 'required|string|min:5|max:1000'])->validate();
        app(UsernameAllocator::class)->transaction(function () use ($revision, $reason): void {
            $user = SyntheticRatingUser::query()->lockForUpdate()->findOrFail($revision->synthetic_rating_user_id);
            $ratings = ClaimRating::query()->where('synthetic_rating_user_id', $user->id)->orderBy('id')->lockForUpdate()->get();
            $rating = $ratings->firstWhere('id', (int) $revision->claim_rating_id);
            $entry = RatingEditRevision::query()->lockForUpdate()->findOrFail($revision->id);
            if (! $rating || ! self::editable($rating) || $entry->status !== 'conflict'
                || (int) data_get($rating->data, 'edit_revision') !== (int) $entry->rating_revision
                || (int) data_get($user->data, 'edit_revision') !== (int) $entry->user_revision) {
                throw ValidationException::withMessages(['editReason' => 'Diese Änderung kann in diesem Stand nicht verworfen werden.']);
            }
            $current = $this->synchronizer->capture($rating);
            foreach (['user', 'rating'] as $group) {
                foreach (($entry->changes[$group] ?? []) as $field => $change) {
                    if ($field === 'scheduled_for') {
                        continue;
                    }
                    if ((string) data_get($current, $group.'.'.$field) !== (string) data_get($entry->base_snapshot, $group.'.'.$field)) {
                        throw ValidationException::withMessages(['editReason' => 'Base wurde zwischenzeitlich geändert. Bitte die aktuellen Werte vergleichen und stattdessen den gewünschten Zielwert im Formular korrigieren.']);
                    }
                }
            }
            $changes = $entry->changes;
            if (isset($changes['user']['username'])) {
                app(UsernameAllocator::class)->rename($user, $changes['user']['username']['before']);
                $user->refresh();
            }
            foreach (($changes['user'] ?? []) as $field => $change) {
                $user->setAttribute($field, $change['before']);
            }
            $userData = $user->data ?? [];
            $userData['edit_revision'] = (int) $entry->user_revision + 1;
            data_set($userData, 'persona.first_name', $user->first_name);
            data_set($userData, 'persona.last_name', $user->last_name);
            data_set($userData, 'persona.display_name', trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->name);
            $state = ['status' => 'local', 'revision_id' => $entry->id, 'error' => 'Konflikt verworfen; lokale Vorherwerte wiederhergestellt. Base blieb unverändert.', 'synced_at' => null];
            if (! empty($changes['user'])) {
                $userData['edit_sync'] = $state;
            }
            $user->forceFill(['data' => $userData])->saveQuietly();
            $attachments = $rating->attachments ?? [];
            $answers = $rating->answers ?? [];
            foreach (($changes['rating'] ?? []) as $field => $change) {
                if ($field === 'summary') {
                    data_set($attachments, 'scorings.ai_overall_comment', $change['before']);
                } elseif ($field === 'regulation_comment') {
                    $answers['regulationComment'] = $change['before'];
                } elseif (in_array($field, ['rating_score', 'scheduled_for'], true)) {
                    $rating->setAttribute($field, $change['before']);
                }
            }
            $data = $rating->data ?? [];
            $data['edit_revision'] = (int) $entry->rating_revision + 1;
            $data['edit_sync'] = $state;
            $data['planning']['synthetic_user_profile'] = $user->publicProfile();
            $rating->forceFill(['data' => $data, 'answers' => $answers, 'attachments' => $attachments])->saveQuietly();
            foreach ($ratings->where('id', '!=', $rating->id) as $other) {
                $data = $other->data ?? [];
                $data['planning']['synthetic_user_profile'] = $user->publicProfile();
                $other->forceFill(['data' => $data])->saveQuietly();
            }
            $changes['discarded'] = ['actor_id' => auth()->id(), 'at' => now()->toIso8601String(), 'reason' => trim($reason)];
            $entry->update(['status' => 'discarded', 'changes' => $changes, 'last_error' => $state['error']]);
        });

        return $revision->fresh();
    }

    private function userValues(SyntheticRatingUser $user): array
    {
        return ['name' => (string) $user->name, 'username' => (string) $user->username, 'first_name' => $user->first_name ?: null, 'last_name' => $user->last_name ?: null];
    }

    private function ratingValues(ClaimRating $rating): array
    {
        return [
            'rating_score' => $rating->rating_score === null ? null : number_format((float) $rating->rating_score, 2, '.', ''),
            'summary' => (string) data_get($rating->attachments, 'scorings.ai_overall_comment', ''),
            'regulation_comment' => data_get($rating->answers, 'regulationComment'),
            'scheduled_for' => $rating->scheduled_for?->toDateTimeString(),
        ];
    }

    private function changes(array $before, array $after): array
    {
        $changes = [];
        foreach ($after as $field => $value) {
            if (($before[$field] ?? null) !== $value) {
                $changes[$field] = ['before' => $before[$field] ?? null, 'after' => $value];
            }
        }

        return $changes;
    }
}
