<?php

namespace App\Livewire;

use App\Models\ClaimRating;
use App\Models\Setting;
use App\Models\SyntheticRatingUser;
use App\Services\BaseClaimRatingPublisher;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class AdminDashboard extends Component
{
    public int $totalRatings = 0;
    public int $linkedBaseRatings = 0;
    public int $linkedBaseUsers = 0;
    public int $publicRatings = 0;
    public int $pendingRatings = 0;
    public int $plannedRatings = 0;
    public int $dueRatings = 0;
    public int $preparedRatings = 0;
    public int $executedRatings = 0;
    public int $failedRatings = 0;
    public int $processingRatings = 0;
    public int $upcomingRatings = 0;
    public int $retractedRatings = 0;
    public ?float $averageScore = null;
    public ?string $nextScheduledFor = null;
    public ?string $nextScheduledAtIso = null;
    public string $syntheticUserNameMode = 'realistic';

    /** @var array<int, array<string, mixed>> */
    public array $recentRatings = [];

    /** @var array<int, array<string, mixed>> */
    public array $upcomingList = [];

    /** @var array<int, array<string, mixed>> */
    public array $statusCards = [];

    /** @var array<int, array<string, mixed>> */
    public array $configChecks = [];

    public function mount(): void
    {
        $this->loadStats();
    }

    public function retractAllSyntheticBaseData(): void
    {
        try {
            $report = app(BaseClaimRatingPublisher::class)->retractAll();
            $this->loadStats();

            if (! empty($report['failed'])) {
                session()->flash(
                    'error',
                    sprintf(
                        '%d Datensaetze wurden zurueckgerufen, %d sind fehlgeschlagen.',
                        $report['ratings_retracted'] ?? 0,
                        count($report['failed'])
                    )
                );

                return;
            }

            session()->flash(
                'success',
                sprintf(
                    '%d Bewertungen und %d verwaiste Testnutzer wurden aus der Base-Datenbank entfernt.',
                    $report['ratings_retracted'] ?? 0,
                    $report['orphan_users_deleted'] ?? 0
                )
            );
        } catch (\Throwable $exception) {
            session()->flash('error', 'Rueckruf fehlgeschlagen: '.$exception->getMessage());
        }
    }

    public function loadStats(): void
    {
        if (! Schema::hasTable('claim_ratings')) {
            return;
        }

        $this->totalRatings = ClaimRating::count();
        $this->linkedBaseRatings = ClaimRating::whereNotNull('base_claim_rating_id')->count();
        $this->linkedBaseUsers = Schema::hasTable('synthetic_rating_users')
            ? SyntheticRatingUser::whereNotNull('base_user_id')->count()
            : 0;
        $this->publicRatings = ClaimRating::where('is_public', true)->count();
        $this->pendingRatings = ClaimRating::where('status', ClaimRating::STATUS_PENDING)->count();
        $this->plannedRatings = ClaimRating::planned()
            ->whereNull('executed_at')
            ->withoutManualOnlyAfterRetract()
            ->count();
        $this->dueRatings = ClaimRating::query()
            ->whereNull('executed_at')
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())
            ->withoutManualOnlyAfterRetract()
            ->whereNotIn('status', [ClaimRating::STATUS_FAILED, ClaimRating::STATUS_PROCESSING])
            ->count();
        $this->preparedRatings = ClaimRating::query()
            ->whereNull('executed_at')
            ->whereNotNull('answers')
            ->withoutManualOnlyAfterRetract()
            ->count();
        $this->executedRatings = ClaimRating::whereNotNull('executed_at')->count();
        $this->failedRatings = ClaimRating::query()
            ->where(function ($query): void {
                $query
                    ->where('status', ClaimRating::STATUS_FAILED)
                    ->orWhereNotNull('last_execution_error');
            })
            ->count();
        $this->processingRatings = ClaimRating::where('status', ClaimRating::STATUS_PROCESSING)->count();
        $this->retractedRatings = ClaimRating::query()
            ->where(function ($query): void {
                $query
                    ->where('status', ClaimRating::STATUS_RETRACTED)
                    ->orWhere('data->execution_control->manual_only_after_retract', true);
            })
            ->count();
        $this->upcomingRatings = ClaimRating::query()
            ->whereNull('executed_at')
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '>', now())
            ->withoutManualOnlyAfterRetract()
            ->whereNotIn('status', [ClaimRating::STATUS_FAILED, ClaimRating::STATUS_PROCESSING])
            ->count();

        $averageScore = ClaimRating::whereNotNull('rating_score')->avg('rating_score');
        $this->averageScore = $averageScore !== null ? round((float) $averageScore, 2) : null;

        $nextRating = ClaimRating::query()
            ->whereNull('executed_at')
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '>', now())
            ->withoutManualOnlyAfterRetract()
            ->whereNotIn('status', [ClaimRating::STATUS_FAILED, ClaimRating::STATUS_PROCESSING])
            ->orderBy('scheduled_for')
            ->first();
        $this->nextScheduledFor = $nextRating?->scheduled_for?->format('d.m.Y H:i');
        $this->nextScheduledAtIso = $nextRating?->scheduled_for?->toIso8601String();

        $this->recentRatings = $this->recentRatings();
        $this->upcomingList = $this->upcomingRatings();
        $this->statusCards = $this->statusCards();
        $this->configChecks = $this->configChecks();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentRatings(): array
    {
        return ClaimRating::query()
            ->with('syntheticUser')
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get()
            ->map(fn (ClaimRating $rating): array => $this->ratingRow($rating))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function upcomingRatings(): array
    {
        return ClaimRating::query()
            ->with('syntheticUser')
            ->whereNull('executed_at')
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '>', now())
            ->withoutManualOnlyAfterRetract()
            ->whereNotIn('status', [ClaimRating::STATUS_FAILED, ClaimRating::STATUS_PROCESSING])
            ->orderBy('scheduled_for')
            ->limit(5)
            ->get()
            ->map(fn (ClaimRating $rating): array => $this->ratingRow($rating))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function ratingRow(ClaimRating $rating): array
    {
        $baseContext = data_get($rating->data, 'base_context', []);
        $syntheticUser = $rating->syntheticUser;

        return [
            'id' => $rating->id,
            'status' => $rating->status,
            'status_label' => $rating->status_label,
            'execution_state' => $rating->execution_state_label,
            'score' => $rating->rating_score !== null ? number_format((float) $rating->rating_score, 2, ',', '.') : null,
            'scheduled_for' => $rating->scheduled_for?->format('d.m.Y H:i'),
            'executed_at' => $rating->executed_at?->format('d.m.Y H:i'),
            'updated_at' => $rating->updated_at?->format('d.m.Y H:i'),
            'base_claim_rating_id' => $rating->base_claim_rating_id,
            'base_user_id' => $rating->base_user_id ?: $syntheticUser?->base_user_id,
            'user_name' => $syntheticUser?->name,
            'user_email' => $syntheticUser?->email,
            'insurance_name' => data_get($baseContext, 'insurance.name'),
            'type_name' => data_get($baseContext, 'insurance_type.name'),
            'subtype_name' => data_get($baseContext, 'insurance_subtype.name'),
            'has_error' => filled($rating->last_execution_error),
            'error' => $rating->last_execution_error,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function statusCards(): array
    {
        return [
            [
                'label' => 'Geplant',
                'value' => $this->plannedRatings,
                'detail' => $this->nextScheduledFor ? 'Naechster Lauf '.$this->nextScheduledFor : 'Kein Lauf geplant',
                'icon' => 'fa-calendar-alt',
                'tone' => 'blue',
            ],
            [
                'label' => 'AI vorbereitet',
                'value' => $this->preparedRatings,
                'detail' => 'Mit Antworten, noch nicht ausgefuehrt',
                'icon' => 'fa-magic',
                'tone' => 'violet',
            ],
            [
                'label' => 'Faellig',
                'value' => $this->dueRatings,
                'detail' => 'Kann jetzt ausgefuehrt werden',
                'icon' => 'fa-bolt',
                'tone' => $this->dueRatings > 0 ? 'amber' : 'slate',
            ],
            [
                'label' => 'Ausgefuehrt',
                'value' => $this->executedRatings,
                'detail' => $this->linkedBaseRatings.' mit Base-ID',
                'icon' => 'fa-check-circle',
                'tone' => 'emerald',
            ],
            [
                'label' => 'Zurueckgerufen',
                'value' => $this->retractedRatings,
                'detail' => 'Dauerhaft von neuen Laeufen ausgeschlossen',
                'icon' => 'fa-rotate-left',
                'tone' => 'slate',
            ],
            [
                'label' => 'Fehler',
                'value' => $this->failedRatings,
                'detail' => $this->processingRatings.' laufen aktuell',
                'icon' => 'fa-exclamation-triangle',
                'tone' => $this->failedRatings > 0 ? 'rose' : 'slate',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function configChecks(): array
    {
        $openRouter = Setting::getValue('openrouter', 'config') ?? [];
        $openRouter = is_array($openRouter) ? $openRouter : [];
        $database = Setting::getValue('database', 'config') ?? [];
        $database = is_array($database) ? $database : [];
        $ratingSettings = Setting::getValue('rating_generation', 'settings') ?? [];
        $ratingSettings = is_array($ratingSettings) ? $ratingSettings : [];
        $analysis = Setting::getValue('rating_generation', 'analysis') ?? [];
        $analysis = is_array($analysis) ? $analysis : [];
        $this->syntheticUserNameMode = 'realistic';

        return [
            [
                'label' => 'OpenRouter',
                'value' => filled($openRouter['api_key'] ?? null) ? 'Bereit' : 'API-Key fehlt',
                'ok' => filled($openRouter['api_key'] ?? null),
                'detail' => (string) ($openRouter['model'] ?? 'kein Modell'),
            ],
            [
                'label' => 'RegCheck DB',
                'value' => filled($database['database'] ?? null) ? 'Konfiguriert' : 'Nicht gesetzt',
                'ok' => filled($database['database'] ?? null),
                'detail' => (string) ($database['database'] ?? 'keine Datenbank'),
            ],
            [
                'label' => 'Analyse',
                'value' => filled($analysis['analyzed_at'] ?? null) ? 'Vorhanden' : 'Ausstehend',
                'ok' => filled($analysis['analyzed_at'] ?? null),
                'detail' => filled($analysis['analyzed_at'] ?? null)
                    ? \Carbon\Carbon::parse((string) $analysis['analyzed_at'])->format('d.m.Y H:i')
                    : 'Noch nicht berechnet',
            ],
            [
                'label' => 'Namensmodus',
                'value' => 'Realistische Namen',
                'ok' => true,
                'detail' => 'Gilt fuer neue Demo-Benutzer',
            ],
        ];
    }

    public function render()
    {
        return view('livewire.admin.dashboard')->layout('layouts.master');
    }
}
