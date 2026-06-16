<?php

namespace App\Livewire\Admin;

use App\Jobs\GenerateSyntheticClaimRating;
use App\Livewire\Admin\Concerns\ShowsClaimRatingModal;
use App\Models\ClaimRating;
use App\Models\Setting;
use App\Services\BaseClaimRatingPublisher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;

class PlannedClaimRatings extends Component
{
    use WithPagination;
    use ShowsClaimRatingModal;

    private const AI_VARIATION_SETTING_TYPE = 'claim_rating_ai';
    private const AI_VARIATION_SETTING_KEY = 'variation_settings';

    public string $search = '';
    public string $executionFilter = 'all';
    public string $sortField = 'scheduled_for';
    public string $sortDirection = 'asc';
    public array $aiVariationSettings = [];

    protected array $queryString = [
        'search' => ['except' => ''],
        'executionFilter' => ['except' => 'all'],
    ];

    public function mount(): void
    {
        $this->aiVariationSettings = $this->storedAiVariationSettings();
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
        $rating = null;

        try {
            $rating = ClaimRating::query()
                ->whereKey($ratingId)
                ->firstOrFail();

            app(BaseClaimRatingPublisher::class)->retract($rating);

            Log::info('Synthetic rating execution rollback completed.', [
                'claim_rating_id' => $rating->id,
                'base_claim_rating_id' => $rating->base_claim_rating_id,
            ]);

            session()->flash('success', 'Ausfuehrung wurde rueckgaengig gemacht. Die Base-ID wurde entfernt und die Bewertung kann erneut ausgefuehrt werden.');
        } catch (\Throwable $exception) {
            Log::error('Synthetic rating execution rollback failed.', [
                'claim_rating_id' => $rating?->id ?? $ratingId,
                'base_claim_rating_id' => $rating?->base_claim_rating_id,
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


    public function saveAiVariationSettings(): void
    {
        $settings = $this->normalizedAiVariationSettings($this->aiVariationSettings);

        Setting::setValue(self::AI_VARIATION_SETTING_TYPE, self::AI_VARIATION_SETTING_KEY, $settings);

        $this->aiVariationSettings = $settings;

        session()->flash('success', 'AI-Variationen wurden gespeichert. Neue AI-Vorbereitungen nutzen diese Werte.');
    }

    public function resetAiVariationSettings(): void
    {
        $settings = $this->aiVariationDefaults();

        Setting::setValue(self::AI_VARIATION_SETTING_TYPE, self::AI_VARIATION_SETTING_KEY, $settings);

        $this->aiVariationSettings = $settings;

        session()->flash('success', 'AI-Variationen wurden auf realistische Standardwerte zurueckgesetzt.');
    }

    /**
     * @return array<string, mixed>
     */
    private function storedAiVariationSettings(): array
    {
        $stored = Setting::getValue(self::AI_VARIATION_SETTING_TYPE, self::AI_VARIATION_SETTING_KEY);

        return $this->normalizedAiVariationSettings(is_array($stored) ? $stored : []);
    }

    /**
     * @return array<string, mixed>
     */
    private function aiVariationDefaults(): array
    {
        return [
            'min_duration_days' => 14,
            'max_duration_days' => 210,
            'closed_ended_min_offset_days' => 3,
            'closed_ended_max_offset_days' => 60,
            'duration_mix' => [
                'short' => 20,
                'normal' => 60,
                'long' => 20,
            ],
            'regulation_type_weights' => [
                'vollzahlung' => 28,
                'teilzahlung' => 42,
                'ablehnung' => 20,
                'austehend' => 10,
            ],
            'regulation_ai_override_percent' => 20,
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function normalizedAiVariationSettings(array $settings): array
    {
        $defaults = $this->aiVariationDefaults();
        $durationMix = is_array($settings['duration_mix'] ?? null) ? $settings['duration_mix'] : [];
        $regulationWeights = is_array($settings['regulation_type_weights'] ?? null) ? $settings['regulation_type_weights'] : [];

        $minDuration = $this->boundedInt($settings['min_duration_days'] ?? $defaults['min_duration_days'], 7, 90, $defaults['min_duration_days']);
        $maxDuration = $this->boundedInt($settings['max_duration_days'] ?? $defaults['max_duration_days'], $minDuration + 14, 365, $defaults['max_duration_days']);
        $endedMinOffset = $this->boundedInt($settings['closed_ended_min_offset_days'] ?? $defaults['closed_ended_min_offset_days'], 0, 30, $defaults['closed_ended_min_offset_days']);
        $endedMaxOffset = $this->boundedInt($settings['closed_ended_max_offset_days'] ?? $defaults['closed_ended_max_offset_days'], $endedMinOffset + 1, 120, $defaults['closed_ended_max_offset_days']);

        return [
            'min_duration_days' => $minDuration,
            'max_duration_days' => $maxDuration,
            'closed_ended_min_offset_days' => $endedMinOffset,
            'closed_ended_max_offset_days' => $endedMaxOffset,
            'duration_mix' => [
                'short' => $this->boundedInt($durationMix['short'] ?? $defaults['duration_mix']['short'], 0, 100, $defaults['duration_mix']['short']),
                'normal' => $this->boundedInt($durationMix['normal'] ?? $defaults['duration_mix']['normal'], 0, 100, $defaults['duration_mix']['normal']),
                'long' => $this->boundedInt($durationMix['long'] ?? $defaults['duration_mix']['long'], 0, 100, $defaults['duration_mix']['long']),
            ],
            'regulation_type_weights' => [
                'vollzahlung' => $this->boundedInt($regulationWeights['vollzahlung'] ?? $defaults['regulation_type_weights']['vollzahlung'], 0, 100, $defaults['regulation_type_weights']['vollzahlung']),
                'teilzahlung' => $this->boundedInt($regulationWeights['teilzahlung'] ?? $defaults['regulation_type_weights']['teilzahlung'], 0, 100, $defaults['regulation_type_weights']['teilzahlung']),
                'ablehnung' => $this->boundedInt($regulationWeights['ablehnung'] ?? $defaults['regulation_type_weights']['ablehnung'], 0, 100, $defaults['regulation_type_weights']['ablehnung']),
                'austehend' => $this->boundedInt($regulationWeights['austehend'] ?? $defaults['regulation_type_weights']['austehend'], 0, 100, $defaults['regulation_type_weights']['austehend']),
            ],
            'regulation_ai_override_percent' => $this->boundedInt($settings['regulation_ai_override_percent'] ?? $defaults['regulation_ai_override_percent'], 0, 100, $defaults['regulation_ai_override_percent']),
        ];
    }

    private function boundedInt(mixed $value, int $min, int $max, int $fallback): int
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        return max($min, min($max, (int) $value));
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
            'aiVariationDefaults' => $this->aiVariationDefaults(),
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
