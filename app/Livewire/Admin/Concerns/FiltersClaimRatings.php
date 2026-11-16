<?php

namespace App\Livewire\Admin\Concerns;

use App\Models\ClaimRating;
use App\Models\SyntheticRatingUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Url;

trait FiltersClaimRatings
{
    use ChecksRatingUsernames;

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $filterDateFrom = '';

    #[Url(except: '')]
    public string $filterDateTo = '';

    #[Url(except: 'created_at')]
    public string $filterDateField = 'created_at';

    #[Url(except: '')]
    public string $filterInsurance = '';

    #[Url(except: '')]
    public string $filterType = '';

    #[Url(except: '')]
    public string $filterSubtype = '';

    #[Url(except: '')]
    public string $filterScoreMin = '';

    #[Url(except: '')]
    public string $filterScoreMax = '';

    #[Url(except: '')]
    public string $filterVisibility = '';

    #[Url(except: '')]
    public string $filterBaseLink = '';

    #[Url(except: '')]
    public string $filterManual = '';

    #[Url(except: '')]
    public string $filterGenerator = '';

    #[Url(except: '')]
    public string $filterSync = '';

    #[Url(except: 25)]
    public int $perPage = 25;

    protected function initializeRatingSearch(): void
    {
        $this->search = (string) session()->get($this->ratingSearchSessionKey(), '');
    }

    public function updated(string $property): void
    {
        if ($property === 'search') {
            $this->search = mb_substr(trim($this->search), 0, 200);
            session()->put($this->ratingSearchSessionKey(), $this->search);
        }

        if (in_array($property, array_merge($this->ratingFilterProperties(), ['search', 'executionFilter', 'perPage']), true)) {
            $this->resetPage();

            if (property_exists($this, 'selectedRatingIds')) {
                $this->selectedRatingIds = [];
            }
        }
    }

    public function removeRatingFilter(string $property): void
    {
        Gate::authorize('ratings.view');

        if (in_array($property, array_merge($this->ratingFilterProperties(), ['search']), true)) {
            $this->reset($property);
            $this->updated($property);
        }
    }

    protected function resetRatingFilters(): void
    {
        $this->reset(array_merge($this->ratingFilterProperties(), ['search']));
        session()->forget($this->ratingSearchSessionKey());
        $this->resetPage();

        if (property_exists($this, 'selectedRatingIds')) {
            $this->selectedRatingIds = [];
        }
    }

    protected function ratingFilterProperties(): array
    {
        return [
            'status', 'filterDateFrom', 'filterDateTo', 'filterDateField',
            'filterInsurance', 'filterType', 'filterSubtype', 'filterScoreMin', 'filterScoreMax',
            'filterVisibility', 'filterBaseLink', 'filterManual', 'filterGenerator', 'filterSync', 'filterNameQuality',
        ];
    }

    protected function ratingFilterValues(): array
    {
        return [
            'status' => $this->status,
            'date_from' => $this->filterDateFrom,
            'date_to' => $this->filterDateTo,
            'date_field' => $this->filterDateField,
            'insurance_id' => $this->filterInsurance,
            'insurance_type_id' => $this->filterType,
            'insurance_subtype_id' => $this->filterSubtype,
            'score_min' => str_replace(',', '.', $this->filterScoreMin),
            'score_max' => str_replace(',', '.', $this->filterScoreMax),
            'visibility' => $this->filterVisibility,
            'base_link' => $this->filterBaseLink,
            'manual' => $this->filterManual,
            'generator' => $this->filterGenerator,
            'sync' => $this->filterSync,
            'name_quality' => $this->filterNameQuality,
        ];
    }

    protected function ratingFilterErrors(): array
    {
        $values = array_map(fn ($value) => $value === '' ? null : $value, $this->ratingFilterValues());
        $validator = Validator::make($values, [
            'status' => ['nullable', 'in:'.implode(',', array_keys(ClaimRating::statusOptions()))],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'date_field' => ['required', 'in:created_at,scheduled_for,executed_at,updated_at'],
            'insurance_id' => ['nullable', 'integer', 'min:1'],
            'insurance_type_id' => ['nullable', 'integer', 'min:1'],
            'insurance_subtype_id' => ['nullable', 'integer', 'min:1'],
            'score_min' => ['nullable', 'numeric', 'between:0,1'],
            'score_max' => ['nullable', 'numeric', 'between:0,1'],
            'visibility' => ['nullable', 'in:public,private'],
            'base_link' => ['nullable', 'in:linked,unlinked'],
            'manual' => ['nullable', 'in:yes,no'],
            'generator' => ['nullable', 'in:6,legacy'],
            'sync' => ['nullable', 'in:local,pending,synced,failed,conflict,unknown'],
            'name_quality' => ['nullable', 'in:'.implode(',', array_keys($this->nameQualityOptions()))],
        ]);

        if ($validator->fails()) {
            return ['Bitte die Filter prüfen: gültige Daten, positive IDs und einen Score zwischen 0 und 1 verwenden.'];
        }

        if ($this->filterDateFrom !== '' && $this->filterDateTo !== '' && $this->filterDateFrom > $this->filterDateTo) {
            return ['Das Von-Datum darf nicht nach dem Bis-Datum liegen.'];
        }

        if ($this->filterScoreMin !== '' && $this->filterScoreMax !== ''
            && (float) $values['score_min'] > (float) $values['score_max']) {
            return ['Der minimale Score darf nicht größer als der maximale Score sein.'];
        }

        return [];
    }

    protected function applyRatingFilters(Builder $query): Builder
    {
        if ($this->ratingFilterErrors() !== []) {
            return $query->whereRaw('1 = 0');
        }

        return $this->applyNameQualityFilter($query->search($this->search)->adminFilters($this->ratingFilterValues()));
    }

    protected function orderedRatingQuery(Builder $query, string $default): Builder
    {
        $allowed = ['id', 'base_claim_rating_id', 'base_user_id', 'synthetic_rating_user_id', 'rating_score',
            'status', 'is_public', 'created_at', 'updated_at', 'scheduled_for', 'executed_at', 'execution_attempts', 'name', 'username'];
        $field = in_array($this->sortField, $allowed, true) ? $this->sortField : $default;
        $direction = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        if (in_array($field, ['name', 'username'], true)) {
            $query->orderBy(SyntheticRatingUser::query()->select($field)
                ->whereColumn('synthetic_rating_users.id', 'claim_ratings.synthetic_rating_user_id')->limit(1), $direction);
        } else {
            if ($field === 'scheduled_for') {
                $query->orderByRaw('scheduled_for IS NULL');
            }

            $query->orderBy($query->qualifyColumn($field), $direction);
        }

        return $field === 'id' ? $query : $query->orderBy('claim_ratings.id', $direction);
    }

    protected function ratingPageSize(): int
    {
        return in_array($this->perPage, [15, 25, 50, 100], true) ? $this->perPage : 25;
    }

    protected function ratingFilterViewData(): array
    {
        $options = [];
        foreach (['insurance_id' => 'insurance', 'insurance_type_id' => 'insurance_type', 'insurance_subtype_id' => 'insurance_subtype'] as $column => $context) {
            $options[$column] = ClaimRating::query()
                ->select([$column, 'data->base_context->'.$context.'->name as filter_label'])
                ->whereNotNull($column)->distinct()->orderBy($column)->get()
                ->unique($column)->mapWithKeys(fn ($row) => [
                    (string) $row->{$column} => ($row->filter_label ?: '#'.$row->{$column}).' (#'.$row->{$column}.')',
                ])->all();
        }

        return [
            'ratingFilterOptions' => $options,
            'ratingFilterErrors' => $this->ratingFilterErrors(),
            'activeRatingFilters' => $this->activeRatingFilters(),
            'statusOptions' => ClaimRating::statusOptions(),
        ];
    }

    protected function activeRatingFilters(): array
    {
        $labels = [
            'search' => 'Suche', 'status' => 'Status', 'filterDateFrom' => 'Von', 'filterDateTo' => 'Bis',
            'filterInsurance' => 'Versicherer-ID', 'filterType' => 'Art-ID', 'filterSubtype' => 'Unterart-ID',
            'filterScoreMin' => 'Score ab', 'filterScoreMax' => 'Score bis', 'filterVisibility' => 'Sichtbarkeit',
            'filterBaseLink' => 'Base-Verknüpfung', 'filterManual' => 'Manuell geändert',
            'filterGenerator' => 'Generator', 'filterSync' => 'Übertragung', 'filterNameQuality' => 'Benutzernamen',
        ];
        $values = ['public' => 'öffentlich markiert', 'private' => 'nicht öffentlich', 'linked' => 'vorhanden',
            'unlinked' => 'fehlt', 'yes' => 'ja', 'no' => 'nein', 'legacy' => 'älter / unbekannt',
            'local' => 'nur lokal', 'pending' => 'ausstehend', 'synced' => 'synchronisiert',
            'failed' => 'fehlgeschlagen', 'conflict' => 'Konflikt', 'unknown' => 'nicht geprüft'];
        $chips = [];

        foreach ($labels as $property => $label) {
            if ($this->{$property} !== '') {
                $value = $property === 'filterNameQuality' ? ($this->nameQualityOptions()[$this->filterNameQuality] ?? $this->filterNameQuality) : ($property === 'status' ? (ClaimRating::statusOptions()[$this->status] ?? $this->status) : ($values[$this->{$property}] ?? $this->{$property}));
                $chips[$property] = $label.': '.$value;
            }
        }

        return $chips;
    }

    private function ratingSearchSessionKey(): string
    {
        return 'rating-search.'.str_replace('\\', '.', static::class).'.'.(auth()->id() ?? 'guest');
    }
}
