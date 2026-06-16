<?php

namespace App\Livewire;

use App\Models\Setting;
use App\Services\RatingDistributionAnalyzer;
use App\Support\Rating\RatingDistributionCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class AdminConfig extends Component
{
    public int $dailyTarget = 10;

    /** @var array<int|string, float|int|string|null> */
    public array $typeWeights = [];

    /** @var array<int|string, array<int|string, float|int|string|null>> */
    public array $subtypeWeights = [];

    /** @var array<int|string, float|int|string|null> */
    public array $hourWeights = [];

    /** @var array<string, float|int|string|null> */
    public array $scoreWeights = [];

    /** @var array<int|string, float|int|string|null> */
    public array $providerWeights = [];

    /** @var array<int, array{id: int, name: string}> */
    public array $providerCatalog = [];

    /** @var array<int, bool> */
    public array $expandedTypeSubtypes = [];

    public string $syntheticUserNameMode = 'realistic';

    public string $openrouterApiUrl = 'https://openrouter.ai/api/v1/chat/completions';
    public string $openrouterApiKey = '';
    public string $openrouterModel = 'openrouter/auto';
    public string $openrouterRefererUrl = '';

    public string $dbHost = '';
    public string $dbPort = '';
    public string $dbDatabase = '';
    public string $dbUsername = '';
    public string $dbPassword = '';

    public bool $formFillEnabled = false;
    public string $formFillMode = 'internal_review';
    public int $formFillBatchSize = 5;
    public int $formFillPauseMinSeconds = 30;
    public int $formFillPauseMaxSeconds = 180;
    public bool $formFillStopBeforeSubmit = true;
    public bool $formFillUseSyntheticPersonData = true;
    public string $formFillSourcePath = '';

    public ?array $lastAnalysis = null;

    public function mount(): void
    {
        $this->loadSettings();
        $this->loadAnalysis();
    }

    public function loadSettings(): void
    {
        $ratingSettings = $this->storedSettings();

        $this->dailyTarget = (int) ($ratingSettings['daily_target'] ?? 10);
        $this->typeWeights = $this->mergeTypeWeights($ratingSettings['type_weights'] ?? []);
        $this->subtypeWeights = $this->mergeSubtypeWeights($ratingSettings['subtype_weights'] ?? []);
        $this->hourWeights = $this->mergeHourWeights($ratingSettings['hour_weights'] ?? []);
        $this->syntheticUserNameMode = 'realistic';

        $openrouterSettings = Setting::getValue('openrouter', 'config') ?? [];
        $openrouterSettings = is_array($openrouterSettings) ? $openrouterSettings : [];

        $this->openrouterApiUrl = (string) ($openrouterSettings['api_url'] ?? env('OPENROUTER_API_URL', 'https://openrouter.ai/api/v1/chat/completions'));
        $this->openrouterApiKey = (string) ($openrouterSettings['api_key'] ?? env('OPENROUTER_API_KEY', ''));
        $this->openrouterModel = (string) ($openrouterSettings['model'] ?? env('OPENROUTER_MODEL', 'openrouter/auto'));
        $this->openrouterRefererUrl = (string) ($openrouterSettings['referer_url'] ?? env('APP_URL', 'http://localhost'));

        $dbSettings = Setting::getValue('database', 'config') ?? [];
        $dbSettings = is_array($dbSettings) ? $dbSettings : [];

        $this->dbHost = (string) ($dbSettings['host'] ?? env('ANALYTICS_DB_HOST', env('DB_HOST', '127.0.0.1')));
        $this->dbPort = (string) ($dbSettings['port'] ?? env('ANALYTICS_DB_PORT', env('DB_PORT', '3306')));
        $this->dbDatabase = (string) ($dbSettings['database'] ?? env('ANALYTICS_DB_DATABASE', 'regulierungs-check'));
        $this->dbUsername = (string) ($dbSettings['username'] ?? env('ANALYTICS_DB_USERNAME', env('DB_USERNAME', 'root')));
        $this->dbPassword = (string) ($dbSettings['password'] ?? env('ANALYTICS_DB_PASSWORD', ''));
        $this->scoreWeights = $this->mergeScoreWeights($ratingSettings['score_weights'] ?? []);
        $this->providerCatalog = $this->loadProviderCatalog();
        $this->providerWeights = $this->mergeProviderWeights($ratingSettings['provider_weights'] ?? []);

        $formFillSettings = Setting::getValue('form_filling', 'config') ?? [];
        $formFillSettings = is_array($formFillSettings) ? $formFillSettings : [];

        $this->formFillEnabled = (bool) ($formFillSettings['enabled'] ?? false);
        $this->formFillMode = (string) ($formFillSettings['mode'] ?? 'internal_review');
        $this->formFillBatchSize = (int) ($formFillSettings['batch_size'] ?? 5);
        $this->formFillPauseMinSeconds = (int) ($formFillSettings['pause_min_seconds'] ?? 30);
        $this->formFillPauseMaxSeconds = (int) ($formFillSettings['pause_max_seconds'] ?? 180);
        $this->formFillStopBeforeSubmit = (bool) ($formFillSettings['stop_before_submit'] ?? true);
        $this->formFillUseSyntheticPersonData = (bool) ($formFillSettings['use_synthetic_person_data'] ?? true);
        $this->formFillSourcePath = (string) ($formFillSettings['source_path'] ?? '');
    }

    public function loadAnalysis(): void
    {
        try {
            $this->lastAnalysis = app(RatingDistributionAnalyzer::class)->getLastAnalysis();
        } catch (\Throwable $e) {
            Log::error('Error loading rating analysis: '.$e->getMessage());
            $this->lastAnalysis = null;
        }
    }

    public function save(): void
    {
        $this->validateSettings();
        $this->persistRatingSettings();
        $this->persistOpenRouterSettings();
        $this->persistDatabaseSettings();
        $this->persistFormFillSettings();

        session()->flash('success', 'Einstellungen wurden gespeichert.');
    }

    public function saveFormFillSettings(): void
    {
        $this->validateFormFillSettings();
        $this->persistFormFillSettings();

        session()->flash('success', 'Formular-Einstellungen wurden gespeichert.');
    }

    public function saveRatingSettings(): void
    {
        $this->validateRatingSettings();
        $this->persistRatingSettings();

        session()->flash('success', 'Bewertungs-Einstellungen wurden gespeichert.');
    }

    public function saveDatabaseSettings(): void
    {
        $this->validateDatabaseSettings();
        $this->persistDatabaseSettings();

        session()->flash('success', 'Datenbankeinstellungen wurden gespeichert.');
    }

    public function saveOpenRouterSettings(): void
    {
        $this->validateOpenRouterSettings();
        $this->persistOpenRouterSettings();

        session()->flash('success', 'OpenRouter-Einstellungen wurden gespeichert.');
    }

    public function testDatabaseConnection(): void
    {
        $this->validateDatabaseSettings();
        $this->persistDatabaseSettings();

        try {
            $connection = $this->configureAnalyticsConnection();
            DB::connection($connection)->getPdo();
            $ratingsCount = DB::connection($connection)->table('claim_ratings')->count();

            session()->flash('success', "Verbindung erfolgreich. {$ratingsCount} Bewertungen gefunden.");
        } catch (\Throwable $e) {
            session()->flash('error', 'Datenbankverbindung fehlgeschlagen: '.$e->getMessage());
        }
    }

    public function runAnalysis(): void
    {
        $this->validateSettings();
        $this->persistDatabaseSettings();

        try {
            $analysis = app(RatingDistributionAnalyzer::class)->analyzeAndApplyToSettings($this->dailyTarget);

            $this->loadSettings();
            $this->loadAnalysis();

            session()->flash(
                'success',
                sprintf('Analyse abgeschlossen. %d Bewertungen analysiert und Verteilung gespeichert.', $analysis['total_ratings_analyzed'] ?? 0)
            );
        } catch (\Throwable $e) {
            Log::error('Rating analysis failed: '.$e->getMessage(), ['exception' => $e]);
            session()->flash('error', 'Analyse fehlgeschlagen: '.$e->getMessage());
        }
    }

    public function applyAnalysisResults(): void
    {
        if (! $this->lastAnalysis) {
            session()->flash('error', 'Keine Analyseergebnisse vorhanden.');

            return;
        }

        try {
            app(RatingDistributionAnalyzer::class)->applyAnalysisToGenerationSettings($this->lastAnalysis, $this->dailyTarget);

            $this->loadSettings();
            session()->flash('success', 'Analyseergebnisse wurden als aktive Verteilung gespeichert.');
        } catch (\Throwable $e) {
            session()->flash('error', 'Analyseergebnisse konnten nicht angewendet werden: '.$e->getMessage());
        }
    }

    public function resetDistribution(): void
    {
        $this->typeWeights = RatingDistributionCatalog::defaultTypeWeights();
        $this->subtypeWeights = RatingDistributionCatalog::defaultSubtypeWeights();
        $this->hourWeights = RatingDistributionCatalog::defaultHourWeights();
        $this->scoreWeights = RatingDistributionCatalog::defaultScoreWeights();
        $this->providerWeights = $this->equalProviderWeights();
    }

    public function toggleTypeSubtypes(int $typeId): void
    {
        $this->expandedTypeSubtypes[$typeId] = ! (bool) ($this->expandedTypeSubtypes[$typeId] ?? false);
    }

    public function getTypeWeightTotalProperty(): float
    {
        return array_sum($this->numericWeights($this->typeWeights));
    }

    public function getSubtypeWeightTotalProperty(): float
    {
        $total = 0.0;

        foreach ($this->nestedNumericWeights($this->subtypeWeights) as $weights) {
            $total += array_sum($weights);
        }

        return $total;
    }

    public function getHourWeightTotalProperty(): float
    {
        return array_sum($this->numericWeights($this->hourWeights));
    }

    public function getScoreWeightTotalProperty(): float
    {
        return array_sum($this->scoreNumericWeights($this->scoreWeights));
    }

    public function getProviderWeightTotalProperty(): float
    {
        return array_sum($this->numericWeights($this->providerWeights));
    }

    public function getPeakHoursProperty(): string
    {
        $weights = $this->numericWeights($this->hourWeights);
        arsort($weights);

        $top = array_slice($weights, 0, 3, true);

        return collect($top)
            ->map(fn (float $weight, int $hour) => sprintf('%02d:00 (%s)', $hour, number_format($weight, 1, ',', '.')))
            ->implode(', ');
    }

    public function getAnalysisStatsProperty(): ?string
    {
        if (! $this->lastAnalysis) {
            return null;
        }

        $date = isset($this->lastAnalysis['analyzed_at'])
            ? \Carbon\Carbon::parse($this->lastAnalysis['analyzed_at'])->format('d.m.Y H:i')
            : 'unbekannt';

        return sprintf('%d Bewertungen analysiert am %s', $this->lastAnalysis['total_ratings'] ?? 0, $date);
    }

    /**
     * Prepare score buckets with all calculated values for the view
     */
    public function getFormattedScoreBucketsProperty(): array
    {
        $scoreStats = is_array($this->lastAnalysis['score_stats']['buckets'] ?? null) 
            ? $this->lastAnalysis['score_stats']['buckets'] 
            : [];

        $formatted = [];
        foreach (RatingDistributionCatalog::scoreBuckets() as $scoreKey => $bucketDef) {
            $stats = $scoreStats[$scoreKey] ?? [];
            $percent = (float) ($stats['percent'] ?? 0);
            
            $tone = match ($scoreKey) {
                'very_bad', 'bad' => 'border-rose-200 bg-rose-50 text-rose-800',
                'average' => 'border-amber-200 bg-amber-50 text-amber-800',
                default => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            };

            $formatted[$scoreKey] = [
                'key' => $scoreKey,
                'label' => $bucketDef['label'] ?? 'Unbekannt',
                'min' => $bucketDef['min'] ?? 0,
                'max' => $bucketDef['max'] ?? 0,
                'count' => (int) ($stats['count'] ?? 0),
                'percent' => $percent,
                'tone' => $tone,
                'weight' => $this->scoreWeights[$scoreKey] ?? $bucketDef['default_weight'],
            ];
        }

        return $formatted;
    }

    public function render()
    {
        return view('livewire.admin-config', [
            'catalog' => RatingDistributionCatalog::types(),
            'scoreBuckets' => RatingDistributionCatalog::scoreBuckets(),
            'formattedScoreBuckets' => $this->formattedScoreBuckets,
        ])->layout('layouts.master');
    }

    private function validateSettings(): void
    {
        $this->validate([
            'dailyTarget' => ['required', 'integer', 'min:0', 'max:500'],
            'typeWeights.*' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'subtypeWeights.*.*' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'hourWeights.*' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'scoreWeights.*' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'providerWeights.*' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'openrouterApiUrl' => ['required', 'url'],
            'openrouterApiKey' => ['nullable', 'string'],
            'openrouterModel' => ['required', 'string', 'min:1'],
            'openrouterRefererUrl' => ['nullable', 'url'],
            'dbHost' => ['required', 'string'],
            'dbPort' => ['required', 'integer', 'min:1', 'max:65535'],
            'dbDatabase' => ['required', 'string'],
            'dbUsername' => ['required', 'string'],
            'dbPassword' => ['nullable', 'string'],
            'formFillEnabled' => ['boolean'],
            'formFillMode' => ['required', 'in:internal_review,draft_only,disabled'],
            'formFillBatchSize' => ['required', 'integer', 'min:1', 'max:100'],
            'formFillPauseMinSeconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'formFillPauseMaxSeconds' => ['required', 'integer', 'gte:formFillPauseMinSeconds', 'max:86400'],
            'formFillStopBeforeSubmit' => ['boolean'],
            'formFillUseSyntheticPersonData' => ['boolean'],
            'formFillSourcePath' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function validateRatingSettings(): void
    {
        $this->validate([
            'dailyTarget' => ['required', 'integer', 'min:0', 'max:500'],
            'typeWeights.*' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'subtypeWeights.*.*' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'hourWeights.*' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'scoreWeights.*' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'providerWeights.*' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ]);
    }

    private function validateDatabaseSettings(): void
    {
        $this->validate([
            'dbHost' => ['required', 'string'],
            'dbPort' => ['required', 'integer', 'min:1', 'max:65535'],
            'dbDatabase' => ['required', 'string'],
            'dbUsername' => ['required', 'string'],
            'dbPassword' => ['nullable', 'string'],
        ]);
    }

    private function validateOpenRouterSettings(): void
    {
        $this->validate([
            'openrouterApiUrl' => ['required', 'url'],
            'openrouterApiKey' => ['nullable', 'string'],
            'openrouterModel' => ['required', 'string', 'min:1'],
            'openrouterRefererUrl' => ['nullable', 'url'],
        ]);
    }

    private function validateFormFillSettings(): void
    {
        $this->validate([
            'formFillEnabled' => ['boolean'],
            'formFillMode' => ['required', 'in:internal_review,draft_only,disabled'],
            'formFillBatchSize' => ['required', 'integer', 'min:1', 'max:100'],
            'formFillPauseMinSeconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'formFillPauseMaxSeconds' => ['required', 'integer', 'gte:formFillPauseMinSeconds', 'max:86400'],
            'formFillStopBeforeSubmit' => ['boolean'],
            'formFillUseSyntheticPersonData' => ['boolean'],
            'formFillSourcePath' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function persistRatingSettings(): void
    {
        Setting::setValue('rating_generation', 'settings', [
            'daily_target' => $this->dailyTarget,
            'type_weights' => $this->numericWeights($this->typeWeights),
            'subtype_weights' => $this->nestedNumericWeights($this->subtypeWeights),
            'hour_weights' => $this->numericWeights($this->hourWeights),
            'score_weights' => $this->scoreNumericWeights($this->scoreWeights),
            'provider_weights' => $this->numericWeights($this->providerWeights),
            'synthetic_user_name_mode' => 'realistic',
        ]);
    }

    private function persistOpenRouterSettings(): void
    {
        Setting::setValue('openrouter', 'config', [
            'api_url' => $this->openrouterApiUrl,
            'api_key' => $this->openrouterApiKey,
            'model' => $this->openrouterModel,
            'referer_url' => $this->openrouterRefererUrl,
        ]);
    }

    private function persistFormFillSettings(): void
    {
        Setting::setValue('form_filling', 'config', [
            'enabled' => $this->formFillEnabled,
            'mode' => $this->formFillMode,
            'batch_size' => $this->formFillBatchSize,
            'pause_min_seconds' => $this->formFillPauseMinSeconds,
            'pause_max_seconds' => $this->formFillPauseMaxSeconds,
            'stop_before_submit' => $this->formFillStopBeforeSubmit,
            'use_synthetic_person_data' => $this->formFillUseSyntheticPersonData,
            'source_path' => $this->formFillSourcePath,
        ]);
    }

    private function persistDatabaseSettings(): void
    {
        Setting::setValue('database', 'config', [
            'host' => $this->dbHost,
            'port' => $this->dbPort,
            'database' => $this->dbDatabase,
            'username' => $this->dbUsername,
            'password' => $this->dbPassword,
        ]);
    }

    private function configureAnalyticsConnection(): string
    {
        $connection = env('ANALYTICS_DB_CONNECTION', 'mysql_analytics');
        $base = config("database.connections.{$connection}", config('database.connections.mysql'));

        config()->set("database.connections.{$connection}", array_merge($base, [
            'driver' => 'mysql',
            'host' => $this->dbHost,
            'port' => $this->dbPort,
            'database' => $this->dbDatabase,
            'username' => $this->dbUsername,
            'password' => $this->dbPassword,
        ]));

        DB::purge($connection);

        return $connection;
    }

    private function storedSettings(): array
    {
        $settings = Setting::getValueUncached('rating_generation', 'settings');

        if (is_string($settings)) {
            $decoded = json_decode($settings, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($settings) ? $settings : [];
    }

    /**
     * @param array<int|string, mixed> $storedWeights
     * @return array<int, float>
     */
    private function mergeTypeWeights(array $storedWeights): array
    {
        $weights = RatingDistributionCatalog::defaultTypeWeights();

        foreach ($weights as $typeId => $default) {
            $weights[$typeId] = $this->toWeight($storedWeights[$typeId] ?? $storedWeights[(string) $typeId] ?? $default);
        }

        return $weights;
    }

    /**
     * @param array<int|string, mixed> $storedWeights
     * @return array<int, array<int, float>>
     */
    private function mergeSubtypeWeights(array $storedWeights): array
    {
        $weights = RatingDistributionCatalog::defaultSubtypeWeights();

        foreach ($weights as $typeId => $subtypes) {
            $typeStoredWeights = $storedWeights[$typeId] ?? $storedWeights[(string) $typeId] ?? [];
            $typeStoredWeights = is_array($typeStoredWeights) ? $typeStoredWeights : [];

            foreach ($subtypes as $subtypeId => $default) {
                $weights[$typeId][$subtypeId] = $this->toWeight(
                    $typeStoredWeights[$subtypeId]
                    ?? $typeStoredWeights[(string) $subtypeId]
                    ?? $default
                );
            }
        }

        return $weights;
    }

    /**
     * @param array<int|string, mixed> $storedWeights
     * @return array<int, float>
     */
    private function mergeHourWeights(array $storedWeights): array
    {
        $weights = RatingDistributionCatalog::defaultHourWeights();

        foreach ($weights as $hour => $default) {
            $weights[$hour] = $this->toWeight($storedWeights[$hour] ?? $storedWeights[(string) $hour] ?? $default);
        }

        return $weights;
    }

    /**
     * @param array<string, mixed> $storedWeights
     * @return array<string, float>
     */
    private function mergeScoreWeights(array $storedWeights): array
    {
        $weights = RatingDistributionCatalog::defaultScoreWeights();

        foreach ($weights as $key => $default) {
            $weights[$key] = $this->toWeight($storedWeights[$key] ?? $default);
        }

        return $weights;
    }

    /**
     * @param array<int|string, mixed> $storedWeights
     * @return array<int, float>
     */
    private function mergeProviderWeights(array $storedWeights): array
    {
        $weights = $this->equalProviderWeights();

        foreach ($weights as $providerId => $default) {
            $weights[$providerId] = $this->toWeight($storedWeights[$providerId] ?? $storedWeights[(string) $providerId] ?? $default);
        }

        return $weights;
    }

    /**
     * @return array<int, float>
     */
    private function equalProviderWeights(): array
    {
        $weights = [];

        foreach ($this->providerCatalog as $provider) {
            $weights[(int) $provider['id']] = 1.0;
        }

        return $weights;
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function loadProviderCatalog(): array
    {
        try {
            $connection = $this->configureAnalyticsConnection();

            return DB::connection($connection)
                ->table('insurances')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (object $row): array => [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                ])
                ->all();
        } catch (\Throwable $exception) {
            Log::warning('Could not load provider catalog for rating settings.', [
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param array<int|string, mixed> $weights
     * @return array<int, float>
     */
    private function numericWeights(array $weights): array
    {
        $numeric = [];

        foreach ($weights as $id => $weight) {
            $numeric[(int) $id] = $this->toWeight($weight);
        }

        return $numeric;
    }

    /**
     * @param array<int|string, mixed> $weights
     * @return array<int, array<int, float>>
     */
    private function nestedNumericWeights(array $weights): array
    {
        $numeric = [];

        foreach ($weights as $typeId => $subtypes) {
            if (! is_array($subtypes)) {
                continue;
            }

            foreach ($subtypes as $subtypeId => $weight) {
                $numeric[(int) $typeId][(int) $subtypeId] = $this->toWeight($weight);
            }
        }

        return $numeric;
    }

    /**
     * @param array<string, mixed> $weights
     * @return array<string, float>
     */
    private function scoreNumericWeights(array $weights): array
    {
        $numeric = [];

        foreach (RatingDistributionCatalog::scoreBuckets() as $key => $bucket) {
            $numeric[$key] = $this->toWeight($weights[$key] ?? $bucket['default_weight']);
        }

        return $numeric;
    }

    private function toWeight(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return max(0.0, (float) str_replace(',', '.', (string) $value));
    }

    private function normalizeSyntheticUserNameMode(mixed $value): string
    {
        return 'realistic';
    }
}
