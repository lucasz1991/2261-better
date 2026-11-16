<?php

namespace App\Livewire\Admin\Concerns;

use App\Services\UsernameAllocator;
use App\Support\Rating\SyntheticIdentityGenerator;
use App\Support\Rating\UsernameFingerprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

trait PreviewsRatingUsernames
{
    #[Locked]
    public ?string $previewId = null;

    #[Locked]
    public bool $previewRunning = false;

    #[Locked]
    public array $preview = [];

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
}
