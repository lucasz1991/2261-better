<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\ShowsClaimRatingModal;
use App\Models\ClaimRating;
use App\Models\SyntheticRatingUser;
use App\Services\UsernameAllocator;
use App\Services\UsernameQualityAnalyzer;
use App\Support\Rating\SyntheticIdentityGenerator;
use App\Support\Rating\UsernameFingerprint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Session;
use Livewire\Component;
use Livewire\WithPagination;

class SyntheticUsers extends Component
{
    use ShowsClaimRatingModal;
    use WithPagination;

    #[Session(key: '2261.user_search')]
    public string $search = '';

    public string $quality = '';

    public string $linked = '';

    public string $sync = '';

    public string $sort = 'id';

    public int $perPage = 25;

    #[Locked]
    public ?string $previewId = null;

    #[Locked]
    public bool $previewRunning = false;

    public array $preview = [];

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

    private function previewCacheKey(): string
    {
        return '2261.username-preview.'.auth()->id().'.'.$this->previewId;
    }

    public function startPreview(): void
    {
        Gate::authorize('synthetic_users.quality');
        $this->cancelPreview();
        try {
            $history = app(UsernameAllocator::class)->history();
            $seen = array_fill_keys(array_map([UsernameFingerprint::class, 'key'], array_column($history, 'username')), true);
            $this->previewId = (string) Str::uuid();
            Cache::put($this->previewCacheKey(), [
                'history' => $history, 'seen' => $seen, 'legacy_seen' => $seen,
                'legacy_cores' => [], 'legacy_generated' => 0, 'legacy_failed' => 0,
                'cores' => [], 'families' => [], 'samples' => [], 'generated' => 0, 'failed' => 0,
                'elapsed' => 0.0, 'baseline_count' => count($history),
                'started_at' => now()->toDateTimeString(),
            ], now()->addHour());
            $this->previewRunning = true;
            $this->preview = ['generated' => 0, 'total' => 1000];
            $this->advancePreview();
        } catch (\Throwable $e) {
            $this->previewRunning = false;
            $this->addError('preview', 'Die Vorschau konnte nicht gestartet werden. Bitte Migrationen und Vergaberegister prüfen.');
            report($e);
        }
    }

    public function advancePreview(): void
    {
        Gate::authorize('synthetic_users.quality');
        if (! $this->previewRunning || ! $this->previewId) {
            return;
        }
        $lock = Cache::lock($this->previewCacheKey().'.lock', 60);
        if (! $lock->get()) {
            return;
        }
        try {
            $state = Cache::get($this->previewCacheKey());
            if (! is_array($state)) {
                $this->previewRunning = false;
                $this->addError('preview', 'Die Vorschau ist abgelaufen. Sie kann erneut gestartet werden.');

                return;
            }
            $generator = new SyntheticIdentityGenerator;
            $started = microtime(true);
            for ($i = 0; $i < 25 && $state['generated'] + $state['failed'] < 1000; $i++) {
                try {
                    $persona = $generator->persona([]);
                    try {
                        $legacy = $generator->legacyUsername($persona, fn ($name) => isset($state['legacy_seen'][UsernameFingerprint::key($name)]), Str::random(12));
                        $state['legacy_seen'][UsernameFingerprint::key($legacy)] = true;
                        $legacyCore = $generator->describeUsername($legacy)['core_key'] ?? null;
                        if ($legacyCore) {
                            $state['legacy_cores'][$legacyCore] = ($state['legacy_cores'][$legacyCore] ?? 0) + 1;
                        }
                        $state['legacy_generated']++;
                    } catch (\RuntimeException) {
                        $state['legacy_failed']++;
                    }
                    $candidate = $generator->usernameCandidate(
                        $persona, fn ($name) => isset($state['seen'][UsernameFingerprint::key($name)]),
                        Str::random(12), $state['history']
                    );
                    $username = $candidate['username'];
                    $state['seen'][UsernameFingerprint::key($username)] = true;
                    $state['history'][] = array_merge($candidate, ['created_at' => now()->toDateTimeString(), 'is_reserved' => false]);
                    $state['cores'][$candidate['core_key']] = ($state['cores'][$candidate['core_key']] ?? 0) + 1;
                    foreach ($candidate['family_keys'] as $family) {
                        $state['families'][$family] = ($state['families'][$family] ?? 0) + 1;
                    }
                    if (count($state['samples']) < 24) {
                        $state['samples'][] = $username;
                    }
                    $state['generated']++;
                } catch (\RuntimeException $e) {
                    $state['failed']++;
                    // Do not repeatedly spend the request budget on the same exhausted snapshot.
                    $state['stopped'] = true;
                    break;
                }
                if (microtime(true) - $started > 8) {
                    break;
                }
            }
            $state['elapsed'] += microtime(true) - $started;
            Cache::put($this->previewCacheKey(), $state, now()->addHour());
            $this->preview = [
                'generated' => $state['generated'], 'failed' => $state['failed'], 'total' => 1000,
                'distinct_cores' => count($state['cores']),
                'base_repeats' => $state['generated'] - count($state['cores']),
                'elapsed' => round($state['elapsed'], 2), 'samples' => $state['samples'],
                'baseline_count' => $state['baseline_count'], 'started_at' => $state['started_at'],
                'legacy_generated' => $state['legacy_generated'], 'legacy_failed' => $state['legacy_failed'],
                'legacy_distinct_cores' => count($state['legacy_cores']),
                'legacy_recognized' => array_sum($state['legacy_cores']),
                'legacy_base_repeats' => array_sum($state['legacy_cores']) - count($state['legacy_cores']),
            ];
            $this->previewRunning = ! ($state['stopped'] ?? false) && $state['generated'] + $state['failed'] < 1000;
        } finally {
            $lock->release();
        }
    }

    public function cancelPreview(): void
    {
        Gate::authorize('synthetic_users.quality');
        $this->previewRunning = false;
        if ($this->previewId) {
            Cache::forget($this->previewCacheKey());
        }
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
