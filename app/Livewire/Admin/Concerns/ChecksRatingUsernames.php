<?php

namespace App\Livewire\Admin\Concerns;

use App\Services\UsernameQualityReportStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

trait ChecksRatingUsernames
{
    #[Url(except: '')]
    public string $filterNameQuality = '';

    #[On('rating-name-quality-filter-changed')]
    public function selectNameQualityFilter(string $filter): void
    {
        Gate::authorize('synthetic_users.quality');
        abort_unless($filter === '' || array_key_exists($filter, $this->nameQualityOptions()), 422);
        $this->filterNameQuality = $filter;
        $this->updated('filterNameQuality');
    }

    #[On('rating-name-quality-updated')]
    public function nameQualityUpdated(): void
    {
        Gate::authorize('synthetic_users.quality');
        if ($this->filterNameQuality !== '') {
            $this->resetPage();
            if (property_exists($this, 'selectedRatingIds')) {
                $this->selectedRatingIds = [];
            }
        }
    }

    protected function nameQualityOptions(): array
    {
        return UsernameQualityReportStore::options();
    }

    protected function applyNameQualityFilter(Builder $query): Builder
    {
        if ($this->filterNameQuality === '') {
            return $query;
        }

        Gate::authorize('synthetic_users.quality');
        if (! array_key_exists($this->filterNameQuality, $this->nameQualityOptions())) {
            return $query->whereRaw('1 = 0');
        }
        $report = app(UsernameQualityReportStore::class)->report(true);
        $ids = $this->filterNameQuality === 'unknown'
            ? array_keys(array_filter($report['rows'] ?? [], fn ($row) => empty($row['core_key'])))
            : ($report[$this->filterNameQuality.'_ids'] ?? []);

        return $query->whereIntegerInRaw('claim_ratings.synthetic_rating_user_id', $ids);
    }

    protected function nameQualityViewData(iterable $ratings): array
    {
        $report = Gate::allows('synthetic_users.quality')
            ? app(UsernameQualityReportStore::class)->report($this->filterNameQuality !== '')
            : null;
        $badges = [];
        if ($report) {
            $groups = [];
            foreach (['duplicate', 'core', 'similar', 'family'] as $type) {
                $groups[$type] = array_fill_keys($report[$type.'_ids'] ?? [], true);
            }
            foreach ($ratings as $rating) {
                $userId = $rating->synthetic_rating_user_id;
                if (! $userId || ! isset($report['rows'][$userId])) {
                    continue;
                }
                $row = $report['rows'][$userId];
                foreach ($groups as $type => $members) {
                    if (! isset($members[$userId])) {
                        continue;
                    }
                    $label = match ($type) {
                        'duplicate' => 'Name mehrfach',
                        'core' => 'Gleiche Wortbasis',
                        'similar' => 'Ähnlicher Name',
                        'family' => 'Häufige Wortfamilie',
                    };
                    $title = match ($type) {
                        'duplicate' => 'Dieser normalisierte Benutzername ist mehreren Benutzern zugeordnet.',
                        'core' => 'Die gleiche Wortbasis wird von mehreren Benutzern verwendet; Zusätze zählen nicht als neue Basis.',
                        'similar' => 'Ähnliche Schreibweise zu @'.($report['rows'][$report['similar'][$userId] ?? 0]['username'] ?? 'einem weiteren Namen').'. Dies ist ein Prüfhinweis, keine identische Person.',
                        'family' => 'Diese Wortfamilie kommt unter den letzten 200 Benutzern häufiger vor.',
                    };
                    $badges[$userId][] = ['type' => $type, 'label' => $label, 'title' => $title];
                }
                if (! $row['core_key'] && $this->filterNameQuality === 'unknown') {
                    $badges[$userId][] = ['type' => 'unknown', 'label' => 'Basis unbekannt', 'title' => 'Für diesen historischen Namen konnte keine Wortbasis sicher erkannt werden.'];
                }
            }
        }

        return [
            'nameQualityOptions' => $this->nameQualityOptions(),
            'nameQualityReport' => $report,
            'nameQualityBadges' => $badges,
        ];
    }
}
