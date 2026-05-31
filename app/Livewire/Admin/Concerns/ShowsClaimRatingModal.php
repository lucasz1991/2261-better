<?php

namespace App\Livewire\Admin\Concerns;

use App\Models\ClaimRating;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

trait ShowsClaimRatingModal
{
    public bool $showRatingModal = false;
    public ?int $selectedRatingId = null;

    public function openRatingModal(int $ratingId): void
    {
        Gate::authorize('ratings.view');

        $this->selectedRatingId = $this->ratingDetailQuery()
            ->whereKey($ratingId)
            ->value('id');

        if (! $this->selectedRatingId) {
            abort(404);
        }

        $this->showRatingModal = true;
    }

    public function closeRatingModal(): void
    {
        $this->showRatingModal = false;
        $this->selectedRatingId = null;
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

    abstract protected function ratingDetailQuery(): Builder;
}
