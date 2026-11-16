<?php

namespace App\Livewire\Admin\Concerns;

use App\Exceptions\RatingEditConflict;
use App\Models\ClaimRating;
use App\Models\RatingEditRevision;
use App\Services\RatingEditService;
use App\Services\RatingEditSynchronizer;
use App\Services\UsernameAllocator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Locked;

trait ShowsClaimRatingModal
{
    public bool $showRatingModal = false;

    #[Locked]
    public ?int $selectedRatingId = null;

    #[Locked]
    public string $editRatingToken = '';

    #[Locked]
    public string $editUserToken = '';

    #[Locked]
    public array $editBaseSnapshot = [];

    #[Locked]
    public string $editBaseError = '';

    public string $editScheduledFor = '';

    public string $editRatingScore = '';

    public string $editSummary = '';

    public string $editRegulationComment = '';

    public string $editUserName = '';

    public string $editUserFirstName = '';

    public string $editUserLastName = '';

    public string $editUserUsername = '';

    public string $editReason = '';

    public bool $editContentConfirmed = false;

    public bool $editConflictConfirmed = false;

    public bool $editApplySuggestionToName = false;

    #[Locked]
    public array $editUsernameSuggestions = [];

    public function openRatingModal(int $ratingId): void
    {
        Gate::authorize('ratings.view');
        $this->selectedRatingId = $this->ratingDetailQuery()->whereKey($ratingId)->value('id');
        abort_unless($this->selectedRatingId, 404);
        $this->fillRatingEditForm();
        $this->showRatingModal = true;
    }

    public function closeRatingModal(): void
    {
        $this->showRatingModal = false;
        $this->selectedRatingId = null;
        $this->resetRatingEditForm();
    }

    public function selectedRating(): ?ClaimRating
    {
        return $this->selectedRatingId ? $this->ratingDetailQuery()->whereKey($this->selectedRatingId)->first() : null;
    }

    public function canEditSelectedRating(): bool
    {
        $rating = $this->selectedRating();

        return $rating && RatingEditService::editable($rating)
            && Gate::allows('ratings.edit')
            && (! ($rating->executed_at || $rating->base_claim_rating_id) || Gate::allows('ratings.edit_published'));
    }

    public function saveRatingDetailEdits(): void
    {
        $rating = $this->selectedRating();
        abort_unless($rating && $this->canEditSelectedRating(), 403);
        $revision = app(RatingEditService::class)->save($rating->id, [
            'editUserName' => $this->editUserName,
            'editUserUsername' => strtolower(trim($this->editUserUsername)),
            'editUserFirstName' => $this->editUserFirstName,
            'editUserLastName' => $this->editUserLastName,
            'editRatingScore' => str_replace(',', '.', $this->editRatingScore),
            'editSummary' => $this->editSummary,
            'editRegulationComment' => $this->editRegulationComment,
            'editScheduledFor' => $this->editScheduledFor,
            'editReason' => $this->editReason,
            'editContentConfirmed' => $this->editContentConfirmed,
        ], $this->editRatingToken, $this->editUserToken, $this->editBaseSnapshot);
        $this->fillRatingEditForm();
        $this->dispatch('rating-user-edited');
        session()->flash(in_array($revision->status, ['synced', 'local'], true) ? 'success' : 'error', $revision->status_label.'.'.($revision->last_error ? ' '.$revision->last_error : ''));
    }

    public function suggestRatingUsernames(): void
    {
        Gate::authorize('synthetic_users.edit');
        $rating = $this->selectedRating();
        abort_unless($rating && $this->canEditSelectedRating() && $rating->syntheticUser, 403);
        try {
            $this->editUsernameSuggestions = app(UsernameAllocator::class)->suggestions($rating->syntheticUser, 5);
        } catch (\Throwable $error) {
            $this->addError('editUserUsername', 'Derzeit konnten keine geeigneten Vorschläge erzeugt werden.');
        }
    }

    public function useRatingUsernameSuggestion(int $index): void
    {
        Gate::authorize('synthetic_users.edit');
        abort_unless($this->canEditSelectedRating() && isset($this->editUsernameSuggestions[$index]['username']), 403);
        $this->editUserUsername = $this->editUsernameSuggestions[$index]['username'];
        if ($this->editApplySuggestionToName) {
            $this->editUserName = $this->editUserUsername;
        }
    }

    public function retryRatingEdit(int $revisionId): void
    {
        Gate::authorize('ratings.edit');
        Gate::authorize('ratings.edit_published');
        $rating = $this->selectedRating();
        abort_unless($rating, 404);
        $revision = RatingEditRevision::query()->whereKey($revisionId)->where('synthetic_rating_user_id', $rating->synthetic_rating_user_id)->firstOrFail();
        if (! empty($revision->changes['user'])) {
            Gate::authorize('synthetic_users.edit');
        }
        abort_unless(in_array($revision->status, ['pending', 'failed'], true), 422);
        $revision = app(RatingEditSynchronizer::class)->synchronize($revision);
        $this->fillRatingEditForm();
        $this->dispatch('rating-user-edited');
        session()->flash($revision->status === 'synced' ? 'success' : 'error', $revision->status_label);
    }

    public function resolveRatingEditConflict(int $revisionId): void
    {
        $rating = $this->selectedRating();
        abort_unless($rating, 404);
        $this->validate(['editConflictConfirmed' => 'accepted', 'editReason' => 'required|string|min:5|max:1000']);
        $revision = RatingEditRevision::query()->whereKey($revisionId)->where('claim_rating_id', $rating->id)->firstOrFail();
        $revision = app(RatingEditService::class)->resolveConflict($revision, $this->editBaseSnapshot, $this->editReason);
        $this->fillRatingEditForm();
        $this->dispatch('rating-user-edited');
        session()->flash($revision->status === 'synced' ? 'success' : 'error', $revision->status_label);
    }

    public function discardRatingEditConflict(int $revisionId): void
    {
        $rating = $this->selectedRating();
        abort_unless($rating, 404);
        $this->validate(['editConflictConfirmed' => 'accepted', 'editReason' => 'required|string|min:5|max:1000']);
        $revision = RatingEditRevision::query()->whereKey($revisionId)->where('claim_rating_id', $rating->id)->firstOrFail();
        $revision = app(RatingEditService::class)->discardConflict($revision, $this->editReason);
        $this->fillRatingEditForm();
        $this->dispatch('rating-user-edited');
        session()->flash('success', $revision->status_label.'. Base blieb unverändert.');
    }

    public function prepareRatingEditRestore(int $revisionId): void
    {
        Gate::authorize('ratings.edit');
        $rating = $this->selectedRating();
        abort_unless($rating && $this->canEditSelectedRating(), 403);
        $revision = RatingEditRevision::query()->whereKey($revisionId)->where('claim_rating_id', $rating->id)->whereIn('status', ['synced', 'local'])->firstOrFail();
        if (! empty($revision->changes['user'])) {
            Gate::authorize('synthetic_users.edit');
        }
        $this->fillRatingEditForm();
        $bindings = [
            'user' => ['name' => 'editUserName', 'username' => 'editUserUsername', 'first_name' => 'editUserFirstName', 'last_name' => 'editUserLastName'],
            'rating' => ['rating_score' => 'editRatingScore', 'summary' => 'editSummary', 'regulation_comment' => 'editRegulationComment', 'scheduled_for' => 'editScheduledFor'],
        ];
        foreach ($bindings as $group => $fields) {
            foreach ($fields as $field => $property) {
                if (isset($revision->changes[$group][$field])) {
                    $value = $revision->changes[$group][$field]['before'];
                    $this->{$property} = $field === 'scheduled_for' && $value ? Carbon::parse($value)->format('Y-m-d\TH:i') : (string) $value;
                }
            }
        }
        $this->editReason = 'Vorherwerte aus Änderung #'.$revision->id.' wiederherstellen';
        session()->flash('success', 'Vorherwerte wurden ins Formular geladen. Bitte prüfen und als neue Änderung speichern.');
    }

    public function ratingEditHistory(): array
    {
        $rating = $this->selectedRating();
        if (! $rating || ! Schema::hasTable('rating_edit_revisions')) {
            return [];
        }

        return RatingEditRevision::query()->where('synthetic_rating_user_id', $rating->synthetic_rating_user_id)->latest('id')->limit(20)->get()->all();
    }

    private function fillRatingEditForm(): void
    {
        $this->resetValidation();
        $rating = $this->selectedRating();
        $user = $rating?->syntheticUser;
        if (! $rating || ! $user) {
            $this->resetRatingEditForm();

            return;
        }
        $this->editRatingToken = RatingEditService::versionToken($rating);
        $this->editUserToken = RatingEditService::versionToken($user);
        $this->editScheduledFor = $rating->scheduled_for?->format('Y-m-d\TH:i') ?? '';
        $this->editRatingScore = $rating->rating_score !== null ? (string) $rating->rating_score : '';
        $this->editSummary = (string) data_get($rating->attachments, 'scorings.ai_overall_comment', '');
        $this->editRegulationComment = (string) data_get($rating->answers, 'regulationComment', '');
        $this->editUserName = (string) $user->name;
        $this->editUserUsername = (string) $user->username;
        $this->editUserFirstName = (string) $user->first_name;
        $this->editUserLastName = (string) $user->last_name;
        $this->editReason = '';
        $this->editContentConfirmed = false;
        $this->editConflictConfirmed = false;
        $this->editUsernameSuggestions = [];
        $this->editBaseSnapshot = [];
        $this->editBaseError = '';
        if ($rating->base_claim_rating_id && $this->canEditSelectedRating()) {
            try {
                $this->editBaseSnapshot = app(RatingEditSynchronizer::class)->capture($rating);
            } catch (RatingEditConflict $error) {
                $this->editBaseError = $error->getMessage();
            } catch (\Throwable $error) {
                $this->editBaseError = 'Die Base-Daten sind derzeit nicht erreichbar. Zum sicheren Bearbeiten bitte später neu öffnen.';
            }
        }
    }

    private function resetRatingEditForm(): void
    {
        foreach (['editScheduledFor', 'editRatingScore', 'editSummary', 'editRegulationComment', 'editUserName', 'editUserFirstName', 'editUserLastName', 'editUserUsername', 'editReason', 'editRatingToken', 'editUserToken', 'editBaseError'] as $property) {
            $this->{$property} = '';
        }
        $this->editBaseSnapshot = [];
        $this->editUsernameSuggestions = [];
        $this->editContentConfirmed = false;
        $this->editConflictConfirmed = false;
        $this->resetValidation();
    }

    abstract protected function ratingDetailQuery(): Builder;
}
