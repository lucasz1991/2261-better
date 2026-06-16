<?php

namespace App\Livewire\Admin\Concerns;

use App\Models\ClaimRating;
use App\Models\SyntheticRatingUser;
use Illuminate\Database\Eloquent\Builder;

trait ShowsClaimRatingModal
{
    public bool $showRatingModal = false;
    public ?int $selectedRatingId = null;
    public string $editScheduledFor = '';
    public string $editRatingScore = '';
    public string $editAnswersJson = '';
    public string $editAttachmentsJson = '';
    public string $editAdminReviewJson = '';
    public string $editDataJson = '';
    public string $editUserName = '';
    public string $editUserFirstName = '';
    public string $editUserLastName = '';
    public string $editUserUsername = '';
    public string $editUserEmail = '';
    public bool $editUserStatus = true;
    public string $editUserEmailVerifiedAt = '';
    public string $editUserDataJson = '';

    public function openRatingModal(int $ratingId): void
    {
        $this->selectedRatingId = $this->ratingDetailQuery()
            ->whereKey($ratingId)
            ->value('id');

        if (! $this->selectedRatingId) {
            abort(404);
        }

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
        if (! $this->selectedRatingId) {
            return null;
        }

        return $this->ratingDetailQuery()
            ->whereKey($this->selectedRatingId)
            ->first();
    }

    public function canEditSelectedRating(): bool
    {
        $rating = $this->selectedRating();

        return $rating
            && ! $rating->executed_at
            && ! $rating->base_claim_rating_id
            && $rating->status !== ClaimRating::STATUS_PROCESSING;
    }

    public function saveRatingDetailEdits(): void
    {
        $rating = $this->selectedRating();

        if (! $rating || ! $this->canEditSelectedRating()) {
            session()->flash('error', 'Diese Bewertung kann nicht bearbeitet werden, weil sie nicht mehr nur geplant ist.');

            return;
        }

        $answers = $this->decodeEditorJson($this->editAnswersJson, 'Antworten');
        $attachments = $this->decodeEditorJson($this->editAttachmentsJson, 'Attachments');
        $adminReview = $this->decodeEditorJson($this->editAdminReviewJson, 'Admin-Review');
        $data = $this->decodeEditorJson($this->editDataJson, 'Daten');
        $userData = $this->decodeEditorJson($this->editUserDataJson, 'Benutzerdaten');

        if ($answers === null || $attachments === null || $adminReview === null || $data === null || $userData === null) {
            return;
        }

        $scheduledFor = trim($this->editScheduledFor) !== ''
            ? \Carbon\Carbon::parse($this->editScheduledFor)
            : null;

        $rating->forceFill([
            'scheduled_for' => $scheduledFor,
            'rating_score' => trim($this->editRatingScore) !== '' ? (float) str_replace(',', '.', $this->editRatingScore) : null,
            'answers' => $answers,
            'attachments' => $attachments,
            'admin_review' => $adminReview,
            'data' => $data,
            'last_execution_error' => null,
        ])->saveQuietly();

        $syntheticUser = $rating->syntheticUser ?: SyntheticRatingUser::createForClaimRating($rating);
        $userName = trim($this->editUserName);
        $userFirstName = trim($this->editUserFirstName);
        $userLastName = trim($this->editUserLastName);
        $userUsername = trim($this->editUserUsername) ?: $userName;
        $userEmail = trim($this->editUserEmail);
        $displayName = trim($userFirstName.' '.$userLastName) ?: $userName;
        $persona = is_array($userData['persona'] ?? null) ? $userData['persona'] : [];
        $persona['first_name'] = $userFirstName ?: null;
        $persona['last_name'] = $userLastName ?: null;
        $persona['display_name'] = $displayName;
        $userData['persona'] = $persona;
        $manualEdit = is_array($userData['manual_edit'] ?? null) ? $userData['manual_edit'] : [];
        $userData['manual_edit'] = array_merge($manualEdit, [
            'edited_at' => now()->toDateTimeString(),
            'preserve_for_ai_generation' => true,
        ]);

        $syntheticUser->forceFill([
            'name' => $userName,
            'first_name' => $userFirstName ?: null,
            'last_name' => $userLastName ?: null,
            'username' => $userUsername,
            'email' => $userEmail,
            'email_domain' => $this->domainFromEmail($userEmail) ?: $syntheticUser->email_domain,
            'status' => $this->editUserStatus,
            'email_verified_at' => trim($this->editUserEmailVerifiedAt) !== ''
                ? \Carbon\Carbon::parse($this->editUserEmailVerifiedAt)
                : null,
            'data' => $userData,
        ])->saveQuietly();

        $rating->refresh();
        $data = $rating->data ?? [];
        $data['planning']['synthetic_user_profile'] = $syntheticUser->fresh()->publicProfile();
        $rating->forceFill([
            'synthetic_rating_user_id' => $syntheticUser->id,
            'data' => $data,
        ])->saveQuietly();

        $this->fillRatingEditForm();
        session()->flash('success', 'Geplante Bewertung wurde gespeichert.');
    }

    private function fillRatingEditForm(): void
    {
        $rating = $this->selectedRating();

        if (! $rating) {
            $this->resetRatingEditForm();

            return;
        }

        $syntheticUser = $rating->syntheticUser;

        $this->editScheduledFor = $rating->scheduled_for?->format('Y-m-d\TH:i') ?? '';
        $this->editRatingScore = $rating->rating_score !== null ? (string) $rating->rating_score : '';
        $this->editAnswersJson = $this->encodeEditorJson($rating->answers ?? []);
        $this->editAttachmentsJson = $this->encodeEditorJson($rating->attachments ?? []);
        $this->editAdminReviewJson = $this->encodeEditorJson($rating->admin_review ?? []);
        $this->editDataJson = $this->encodeEditorJson($rating->data ?? []);
        $this->editUserName = (string) ($syntheticUser?->name ?? '');
        $this->editUserFirstName = (string) ($syntheticUser?->first_name ?? '');
        $this->editUserLastName = (string) ($syntheticUser?->last_name ?? '');
        $this->editUserUsername = (string) ($syntheticUser?->username ?? '');
        $this->editUserEmail = (string) ($syntheticUser?->email ?? '');
        $this->editUserStatus = (bool) ($syntheticUser?->status ?? true);
        $this->editUserEmailVerifiedAt = $syntheticUser?->email_verified_at?->format('Y-m-d\TH:i') ?? '';
        $this->editUserDataJson = $this->encodeEditorJson($syntheticUser?->data ?? []);
    }

    private function resetRatingEditForm(): void
    {
        $this->editScheduledFor = '';
        $this->editRatingScore = '';
        $this->editAnswersJson = '';
        $this->editAttachmentsJson = '';
        $this->editAdminReviewJson = '';
        $this->editDataJson = '';
        $this->editUserName = '';
        $this->editUserFirstName = '';
        $this->editUserLastName = '';
        $this->editUserUsername = '';
        $this->editUserEmail = '';
        $this->editUserStatus = true;
        $this->editUserEmailVerifiedAt = '';
        $this->editUserDataJson = '';
    }

    private function encodeEditorJson(mixed $value): string
    {
        return json_encode($value ?: [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private function decodeEditorJson(string $value, string $label): ?array
    {
        $value = trim($value);

        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            session()->flash('error', $label.' enthaelt kein gueltiges JSON-Objekt.');

            return null;
        }

        return $decoded;
    }

    private function domainFromEmail(string $email): ?string
    {
        if (! str_contains($email, '@')) {
            return null;
        }

        return strtolower(trim(substr(strrchr($email, '@') ?: '', 1))) ?: null;
    }

    abstract protected function ratingDetailQuery(): Builder;
}
