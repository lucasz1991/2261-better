<?php

namespace App\Livewire\Admin;

use App\Jobs\GenerateSyntheticClaimRating;
use App\Livewire\Admin\Concerns\ShowsClaimRatingModal;
use App\Models\ClaimRating;
use App\Services\BaseClaimRatingPublisher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;

class PlannedClaimRatings extends Component
{
    use WithPagination;
    use ShowsClaimRatingModal;

    public string $search = '';
    public string $executionFilter = 'all';
    public string $sortField = 'scheduled_for';
    public string $sortDirection = 'asc';

    protected array $queryString = [
        'search' => ['except' => ''],
        'executionFilter' => ['except' => 'all'],
    ];

    public function mount(): void
    {
        Gate::authorize('ratings.view');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedExecutionFilter(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, ['id', 'base_claim_rating_id', 'base_user_id', 'synthetic_rating_user_id', 'scheduled_for', 'executed_at', 'execution_attempts', 'status', 'rating_score', 'created_at'], true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = $field === 'scheduled_for' ? 'asc' : 'desc';
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'executionFilter']);
        $this->resetPage();
    }

    public function prepareWithAi(int $ratingId): void
    {
        Gate::authorize('ratings.view');

        $rating = ClaimRating::query()
            ->planned()
            ->whereKey($ratingId)
            ->firstOrFail();

        if ($rating->executed_at) {
            session()->flash('error', 'Diese Bewertung wurde bereits ausgefuehrt.');

            return;
        }

        try {
            $rating->forceFill([
                'execution_started_at' => null,
                'executed_at' => null,
                'last_execution_error' => null,
                'status' => ClaimRating::STATUS_SCHEDULED,
            ])->saveQuietly();

            GenerateSyntheticClaimRating::dispatchSync($rating->fresh(), false);

            session()->flash('success', 'AI-Inhalt wurde vorbereitet. Die Ausfuehrung bleibt beim geplanten Zeitpunkt.');
        } catch (\Throwable $exception) {
            Log::error('Manual synthetic rating AI preparation failed.', [
                'claim_rating_id' => $rating->id,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            session()->flash('error', 'AI-Vorbereitung fehlgeschlagen: '.$exception->getMessage());
        }
    }

    public function executeNow(int $ratingId): void
    {
        Gate::authorize('ratings.view');

        $rating = ClaimRating::query()
            ->planned()
            ->whereKey($ratingId)
            ->firstOrFail();

        if ($rating->status === ClaimRating::STATUS_PROCESSING) {
            session()->flash('error', 'Diese Bewertung wird gerade verarbeitet.');

            return;
        }

        try {
            $this->releaseManualOnlyAfterRetract($rating);
            $rating->refresh();

            if (! is_array($rating->answers) || $rating->answers === []) {
                GenerateSyntheticClaimRating::dispatchSync($rating->fresh());
                $rating->refresh();
            } else {
                $executedAt = now();

                $rating->forceFill([
                    'status' => ClaimRating::STATUS_RATED,
                    'execution_started_at' => $rating->execution_started_at ?? $executedAt,
                    'executed_at' => $rating->executed_at ?? $executedAt,
                    'last_execution_error' => null,
                    'is_public' => true,
                ])->saveQuietly();

                app(BaseClaimRatingPublisher::class)->publish($rating->fresh());
                $rating->refresh();
            }

            session()->flash('success', 'Bewertung wurde ausgefuehrt und als synthetischer Base-Datensatz #' . $rating->base_claim_rating_id . ' gespeichert.');
        } catch (\Throwable $exception) {
            Log::error('Manual synthetic rating execution failed.', [
                'claim_rating_id' => $rating->id,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            session()->flash('error', 'Ausfuehrung fehlgeschlagen: '.$exception->getMessage());
        }
    }

    public function undoExecution(int $ratingId): void
    {
        Gate::authorize('ratings.view');

        $rating = ClaimRating::query()
            ->planned()
            ->whereKey($ratingId)
            ->firstOrFail();

        try {
            app(BaseClaimRatingPublisher::class)->retract($rating);

            session()->flash('success', 'Ausfuehrung wurde rueckgaengig gemacht. Die Base-ID wurde entfernt und die Bewertung kann erneut ausgefuehrt werden.');
        } catch (\Throwable $exception) {
            Log::error('Synthetic rating execution rollback failed.', [
                'claim_rating_id' => $rating->id,
                'base_claim_rating_id' => $rating->base_claim_rating_id,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            session()->flash('error', 'Rueckgaengig machen fehlgeschlagen: '.$exception->getMessage());
        }
    }

    private function releaseManualOnlyAfterRetract(ClaimRating $rating): void
    {
        if (! $rating->isManualOnlyAfterRetract()) {
            return;
        }

        $data = $rating->data ?? [];
        $data['execution_control'] = array_merge($data['execution_control'] ?? [], [
            'manual_only_after_retract' => false,
            'manual_released_at' => now()->toDateTimeString(),
            'manual_released_by' => static::class,
        ]);

        $rating->forceFill([
            'data' => $data,
            'last_execution_error' => null,
            'status' => ClaimRating::STATUS_SCHEDULED,
        ])->saveQuietly();
    }

    public function render()
    {
        $ratings = $this->plannedQuery()
            ->search($this->search)
            ->when($this->executionFilter !== 'all', fn (Builder $query) => $this->applyExecutionFilter($query, $this->executionFilter))
            ->orderByRaw($this->sortField === 'scheduled_for' ? 'scheduled_for IS NULL, scheduled_for '.$this->sortDirection : $this->sortField.' '.$this->sortDirection)
            ->paginate(15);

        return view('livewire.admin.planned-claim-ratings', [
            'ratings' => $ratings,
            'stats' => $this->stats(),
            'filters' => $this->filters(),
            'selectedRating' => $this->selectedRating(),
        ])->layout('layouts.master');
    }

    protected function ratingDetailQuery(): Builder
    {
        return $this->plannedQuery();
    }

    private function plannedQuery(): Builder
    {
        return ClaimRating::query()
            ->with('syntheticUser')
            ->planned();
    }

    private function applyExecutionFilter(Builder $query, string $filter): Builder
    {
        return match ($filter) {
            'upcoming' => $query
                ->whereNull('executed_at')
                ->where('scheduled_for', '>', now())
                ->withoutManualOnlyAfterRetract()
                ->whereNotIn('status', [ClaimRating::STATUS_FAILED, ClaimRating::STATUS_PROCESSING]),
            'due' => $query
                ->whereNull('executed_at')
                ->whereNotNull('scheduled_for')
                ->where('scheduled_for', '<=', now())
                ->withoutManualOnlyAfterRetract()
                ->whereNotIn('status', [ClaimRating::STATUS_FAILED, ClaimRating::STATUS_PROCESSING]),
            'processing' => $query->where('status', ClaimRating::STATUS_PROCESSING),
            'executed' => $query->whereNotNull('executed_at'),
            'failed' => $query->where(function (Builder $query) {
                $query
                    ->where('status', ClaimRating::STATUS_FAILED)
                    ->orWhereNotNull('last_execution_error');
            }),
            default => $query,
        };
    }

    /**
     * @return array<string, string>
     */
    private function filters(): array
    {
        return [
            'all' => 'Alle geplanten',
            'upcoming' => 'Anstehend',
            'due' => 'Faellig',
            'processing' => 'In Ausfuehrung',
            'executed' => 'Ausgefuehrt',
            'failed' => 'Fehlgeschlagen',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stats(): array
    {
        $planned = $this->plannedQuery();

        $nextRating = (clone $planned)
            ->whereNull('executed_at')
            ->where('scheduled_for', '>', now())
            ->withoutManualOnlyAfterRetract()
            ->orderBy('scheduled_for')
            ->first();

        return [
            'total' => (clone $planned)->count(),
            'due' => (clone $planned)
                ->whereNull('executed_at')
                ->whereNotNull('scheduled_for')
                ->where('scheduled_for', '<=', now())
                ->withoutManualOnlyAfterRetract()
                ->whereNotIn('status', [ClaimRating::STATUS_FAILED, ClaimRating::STATUS_PROCESSING])
                ->count(),
            'upcoming' => (clone $planned)
                ->whereNull('executed_at')
                ->where('scheduled_for', '>', now())
                ->withoutManualOnlyAfterRetract()
                ->whereNotIn('status', [ClaimRating::STATUS_FAILED, ClaimRating::STATUS_PROCESSING])
                ->count(),
            'executed' => (clone $planned)->whereNotNull('executed_at')->count(),
            'failed' => (clone $planned)
                ->where(function (Builder $query) {
                    $query
                        ->where('status', ClaimRating::STATUS_FAILED)
                        ->orWhereNotNull('last_execution_error');
                })
                ->count(),
            'next_scheduled_for' => $nextRating?->scheduled_for,
        ];
    }
}
