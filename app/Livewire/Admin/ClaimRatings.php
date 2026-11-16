<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\FiltersClaimRatings;
use App\Livewire\Admin\Concerns\ShowsClaimRatingModal;
use App\Models\ClaimRating;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class ClaimRatings extends Component
{
    use FiltersClaimRatings;
    use ShowsClaimRatingModal;
    use WithPagination;

    public string $search = '';

    public string $sortField = 'executed_at';

    public string $sortDirection = 'desc';

    protected array $queryString = [
        'sortField' => ['except' => 'executed_at'],
        'sortDirection' => ['except' => 'desc'],
    ];

    public function mount(): void
    {
        Gate::authorize('ratings.view');
        $this->initializeRatingSearch();
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, ['id', 'base_claim_rating_id', 'base_user_id', 'synthetic_rating_user_id', 'rating_score', 'status', 'is_public', 'created_at', 'executed_at', 'updated_at', 'name', 'username'], true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function clearFilters(): void
    {
        $this->resetRatingFilters();
    }

    public function render()
    {
        Gate::authorize('ratings.view');
        $ratings = $this->orderedRatingQuery($this->applyRatingFilters($this->ratingDetailQuery()), 'executed_at')
            ->paginate($this->ratingPageSize());

        return view('livewire.admin.claim-ratings', array_merge($this->ratingFilterViewData(), $this->nameQualityViewData($ratings), [
            'ratings' => $ratings,
            'statusOptions' => ClaimRating::statusOptions(),
            'selectedRating' => $this->selectedRating(),
        ]))->layout('layouts.master');
    }

    protected function ratingDetailQuery(): Builder
    {
        return ClaimRating::query()
            ->with('syntheticUser')
            ->whereNotNull('executed_at');
    }
}
