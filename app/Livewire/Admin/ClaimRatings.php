<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\ShowsClaimRatingModal;
use App\Models\ClaimRating;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class ClaimRatings extends Component
{
    use WithPagination;
    use ShowsClaimRatingModal;

    public string $search = '';
    public string $status = '';
    public string $sortField = 'executed_at';
    public string $sortDirection = 'desc';

    protected array $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
    ];

    public function mount(): void
    {
        Gate::authorize('ratings.view');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, ['id', 'base_claim_rating_id', 'base_user_id', 'synthetic_rating_user_id', 'rating_score', 'status', 'is_public', 'created_at', 'executed_at'], true)) {
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
        $this->reset(['search', 'status']);
        $this->resetPage();
    }

    public function render()
    {
        $ratings = ClaimRating::query()
            ->with('syntheticUser')
            ->whereNotNull('executed_at')
            ->search($this->search)
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(15);

        return view('livewire.admin.claim-ratings', [
            'ratings' => $ratings,
            'statusOptions' => ClaimRating::statusOptions(),
            'selectedRating' => $this->selectedRating(),
        ])->layout('layouts.master');
    }

    protected function ratingDetailQuery(): Builder
    {
        return ClaimRating::query()
            ->with('syntheticUser')
            ->whereNotNull('executed_at');
    }
}
