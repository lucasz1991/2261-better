<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\PreviewsRatingUsernames;
use App\Livewire\Admin\Concerns\ShowsClaimRatingModal;
use App\Models\ClaimRating;
use App\Models\SyntheticRatingUser;
use App\Services\UsernameQualityAnalyzer;
use App\Support\Rating\SyntheticIdentityGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Session;
use Livewire\Component;
use Livewire\WithPagination;

class SyntheticUsers extends Component
{
    use PreviewsRatingUsernames;
    use ShowsClaimRatingModal;
    use WithPagination;

    #[Session(key: '2261.user_search')]
    public string $search = '';

    public string $quality = '';

    public string $linked = '';

    public string $sync = '';

    public string $sort = 'id';

    public int $perPage = 25;

    protected array $queryString = [
        'quality' => ['except' => ''], 'linked' => ['except' => ''],
        'sync' => ['except' => ''],
        'sort' => ['except' => 'id'], 'perPage' => ['except' => 25],
    ];

    public function mount(): void
    {
        Gate::authorize('ratings.view');
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'quality', 'linked', 'sync', 'sort', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'quality', 'linked', 'sync', 'sort', 'perPage']);
        $this->resetPage();
    }

    private function qualityCacheKey(): string
    {
        return '2261.username-quality.'.auth()->id().'.'.sha1(
            SyntheticRatingUser::query()->count().'|'.SyntheticRatingUser::query()->max('updated_at')
        );
    }

    public function refreshQuality(): void
    {
        Gate::authorize('synthetic_users.quality');
        Cache::forget($this->qualityCacheKey());
    }

    protected function ratingDetailQuery(): Builder
    {
        return ClaimRating::query()->with('syntheticUser')->where('data->synthetic', true);
    }

    public function render()
    {
        Gate::authorize('ratings.view');
        $report = Gate::allows('synthetic_users.quality')
            ? Cache::remember($this->qualityCacheKey(), 300, fn () => app(UsernameQualityAnalyzer::class)->analyze())
            : null;
        $query = SyntheticRatingUser::query()->withCount('claimRatings')
            ->with(['claimRatings' => fn ($query) => $query->select('id', 'synthetic_rating_user_id', 'status', 'executed_at', 'data')->orderByDesc('id')]);
        $term = mb_substr(ltrim(trim($this->search), '@'), 0, 200);
        if ($term !== '') {
            $query->where(function (Builder $query) use ($term): void {
                foreach (['name', 'username', 'first_name', 'last_name', 'email'] as $field) {
                    $query->orWhere($field, 'like', '%'.$term.'%');
                }
                if (ctype_digit($term)) {
                    $query->orWhere('id', (int) $term)->orWhere('base_user_id', (int) $term);
                }
                $parts = preg_split('/\s+/u', $term) ?: [];
                if (count($parts) > 1) {
                    $query->orWhere(fn ($q) => $q->where('first_name', 'like', '%'.$parts[0].'%')->where('last_name', 'like', '%'.implode(' ', array_slice($parts, 1)).'%'));
                }
            });
        }
        if (in_array($this->linked, ['yes', 'no'], true)) {
            $this->linked === 'yes' ? $query->whereNotNull('base_user_id') : $query->whereNull('base_user_id');
        }
        if (in_array($this->sync, ['local', 'pending', 'synced', 'failed', 'conflict'], true)) {
            $query->where('data->edit_sync->status', $this->sync);
        }
        if (in_array($this->quality, ['duplicate', 'core', 'family', 'similar'], true)) {
            Gate::authorize('synthetic_users.quality');
            $query->whereIntegerInRaw('id', $report[$this->quality.'_ids'] ?? []);
        }
        $sort = in_array($this->sort, ['name', 'username', 'id', 'updated_at'], true) ? $this->sort : 'id';
        $users = $query->orderBy($sort, in_array($sort, ['id', 'updated_at']) ? 'desc' : 'asc')
            ->orderBy('id')->paginate(in_array($this->perPage, [25, 50, 100], true) ? $this->perPage : 25);

        return view('livewire.admin.synthetic-users', [
            'users' => $users, 'report' => $report,
            'catalog' => (new SyntheticIdentityGenerator)->catalogStatistics(),
            'selectedRating' => $this->selectedRating(),
        ])->layout('layouts.master');
    }
}
