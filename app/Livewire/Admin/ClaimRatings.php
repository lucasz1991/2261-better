<?php

namespace App\Livewire\Admin;

use App\Models\ClaimRating;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class ClaimRatings extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';
    public string $sortField = 'created_at';
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
        if (! in_array($field, ['id', 'base_claim_rating_id', 'rating_score', 'status', 'is_public', 'created_at'], true)) {
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
            ->search($this->search)
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(15);

        return view('livewire.admin.claim-ratings', [
            'ratings' => $ratings,
            'statusOptions' => ClaimRating::statusOptions(),
        ])->layout('layouts.master');
    }
}
