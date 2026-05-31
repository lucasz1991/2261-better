<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\Rating\RatingDistributionCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class RatingDistributionAnalyzer
{
    /**
     * Analysiere die echten Bewertungen und berechne die optimale Verteilung
     * 
     * @return array Berechnete Gewichte für Arten und Unterarten
     */
    public function analyzeRealRatings(): array
    {
        try {
            Log::info('Starting RatingDistributionAnalyzer');

            // Abrufen der Bewertungshäufigkeit und Qualität pro Unterart
            $subtypeStats = $this->getSubtypeStatistics();
            $hourStats = $this->getHourlyStatistics();
            $hourWeights = $this->calculateHourWeights($hourStats);
            $scoreStats = $this->getScoreStatistics();
            $scoreWeights = $this->calculateScoreWeights($scoreStats['buckets'] ?? []);
            $userStats = $this->getUserStatistics();
            
            if (empty($subtypeStats)) {
                Log::warning('No published ratings found for analysis');
                return [
                    'type_weights' => [],
                    'subtype_weights' => [],
                    'hour_weights' => $hourWeights,
                    'hour_stats' => $hourStats,
                    'score_weights' => $scoreWeights,
                    'score_stats' => $scoreStats,
                    'user_stats' => $userStats,
                    'stats' => [],
                    'timestamp' => now(),
                    'total_ratings_analyzed' => 0,
                ];
            }

            // Berechne Unterart-Gewichte basierend auf Anzahl und Qualität
            $subtypeWeights = $this->calculateSubtypeWeights($subtypeStats);

            // Berechne Typ-Gewichte als Summe der Unterarten
            $typeWeights = $this->calculateTypeWeights($subtypeWeights);

            $result = [
                'type_weights' => $typeWeights,
                'subtype_weights' => $subtypeWeights,
                'hour_weights' => $hourWeights,
                'hour_stats' => $hourStats,
                'score_weights' => $scoreWeights,
                'score_stats' => $scoreStats,
                'user_stats' => $userStats,
                'stats' => $subtypeStats,
                'timestamp' => now(),
                'total_ratings_analyzed' => array_sum(
                    array_map(fn($subs) => array_sum(array_column($subs, 'count')), $subtypeStats)
                ),
            ];

            Log::info('RatingDistributionAnalyzer completed', $result);

            return $result;
        } catch (\Exception $e) {
            Log::error('RatingDistributionAnalyzer failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    /**
     * Analysiere echte Bewertungen und setze die aktiven Verteilungswerte.
     */
    public function analyzeAndApplyToSettings(?int $dailyTarget = null): array
    {
        $analysis = $this->analyzeRealRatings();

        $this->saveAnalysisToSettings($analysis);
        $this->applyAnalysisToGenerationSettings($analysis, $dailyTarget);

        return $analysis;
    }

    /**
     * Speichere die berechneten Gewichte in die Settings
     */
    public function saveAnalysisToSettings(array $analysis): void
    {
        try {
            \App\Models\Setting::setValue('rating_generation', 'analysis', [
                'type_weights' => $analysis['type_weights'],
                'subtype_weights' => $analysis['subtype_weights'],
                'hour_weights' => $analysis['hour_weights'] ?? [],
                'hour_stats' => $analysis['hour_stats'] ?? [],
                'score_weights' => $analysis['score_weights'] ?? [],
                'score_stats' => $analysis['score_stats'] ?? [],
                'user_stats' => $analysis['user_stats'] ?? [],
                'stats' => $analysis['stats'],
                'analyzed_at' => $analysis['timestamp']->toIso8601String(),
                'total_ratings' => $analysis['total_ratings_analyzed'],
            ]);

            Log::info('Saved rating analysis to settings', [
                'types_counted' => count($analysis['type_weights']),
                'subtypes_counted' => array_sum(
                    array_map(fn($subs) => count($subs), $analysis['subtype_weights'])
                ),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to save analysis to settings: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Uebernimmt Analyseergebnisse in die aktiven Bewertungs-Einstellungen.
     */
    public function applyAnalysisToGenerationSettings(array $analysis, ?int $dailyTarget = null): void
    {
        $currentSettings = Setting::getValueUncached('rating_generation', 'settings');
        $currentSettings = is_array($currentSettings) ? $currentSettings : [];

        Setting::setValue('rating_generation', 'settings', [
            'daily_target' => $dailyTarget ?? (int) ($currentSettings['daily_target'] ?? 10),
            'type_weights' => $this->mergeTypeWeights($analysis['type_weights'] ?? []),
            'subtype_weights' => $this->mergeSubtypeWeights($analysis['subtype_weights'] ?? []),
            'hour_weights' => $this->mergeHourWeights($analysis['hour_weights'] ?? []),
            'score_weights' => $this->mergeScoreWeights($analysis['score_weights'] ?? []),
        ]);
    }

    /**
     * Hole Statistiken über die echten Bewertungen pro Unterart
     * 
     * Gruppiere nach:
     * - insurance_type_id
     * - insurance_subtype_id
     * - Zaehle die Bewertungen
     * - Berechne Durchschnittsbewertung
     */
    private function getSubtypeStatistics(): array
    {
        $connection = $this->analyticsConnection();

        // Query aus regulierungs-check-base
        $query = DB::connection($connection)
            ->table('claim_ratings')
            ->select(
                'insurance_type_id',
                'insurance_subtype_id',
                DB::raw('COUNT(*) as count'),
                DB::raw('AVG(rating_score) as avg_score'),
                DB::raw('MIN(rating_score) as min_score'),
                DB::raw('MAX(rating_score) as max_score'),
                DB::raw('STDDEV(rating_score) as std_dev')
            )
            ->whereIn('status', [
                'rated',
                'approved',
                'published',
            ])
            ->where('is_public', true)
            ->whereNotNull('insurance_type_id')
            ->whereNotNull('insurance_subtype_id')
            ->whereNotNull('rating_score')
            ->groupBy('insurance_type_id', 'insurance_subtype_id')
            ->get();

        // Strukturiere die Ergebnisse
        $stats = [];
        foreach ($query as $row) {
            $typeId = (int) $row->insurance_type_id;
            $subtypeId = (int) $row->insurance_subtype_id;

            if (!isset($stats[$typeId])) {
                $stats[$typeId] = [];
            }

            $stats[$typeId][$subtypeId] = [
                'count' => (int) $row->count,
                'avg_score' => (float) ($row->avg_score ?? 0),
                'min_score' => (float) ($row->min_score ?? 0),
                'max_score' => (float) ($row->max_score ?? 0),
                'std_dev' => (float) ($row->std_dev ?? 0),
            ];
        }

        return $stats;
    }

    /**
     * Berechne Gewichte für jede Unterart basierend auf:
     * 1. Anzahl der Bewertungen (Beliebtheit)
     * 2. Durchschnittliche Bewertung (Qualität/Zufriedenheit)
     * 3. Konsistenz (Inverse von Standardabweichung)
     */
    /**
     * Ermittelt, zu welchen Stunden echte Bewertungen angelegt wurden.
     *
     * @return array<int, array{count: int, percent: float}>
     */
    private function getHourlyStatistics(): array
    {
        $connection = $this->analyticsConnection();

        $rows = DB::connection($connection)
            ->table('claim_ratings')
            ->select(
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('COUNT(*) as count')
            )
            ->whereIn('status', [
                'rated',
                'approved',
                'published',
            ])
            ->where('is_public', true)
            ->whereNotNull('rating_score')
            ->whereNotNull('created_at')
            ->groupBy(DB::raw('HOUR(created_at)'))
            ->orderBy(DB::raw('HOUR(created_at)'))
            ->get();

        $stats = [];
        $total = max(1, (int) $rows->sum('count'));

        foreach ($rows as $row) {
            $hour = (int) $row->hour;
            $count = (int) $row->count;

            $stats[$hour] = [
                'count' => $count,
                'percent' => round(($count / $total) * 100, 2),
            ];
        }

        return $stats;
    }

    private function calculateSubtypeWeights(array $stats): array
    {
        $subtypeWeights = [];

        // Normalisierungsfaktoren
        $maxCount = max(
            array_merge(...array_map(
                fn($subs) => array_column($subs, 'count'),
                $stats
            ))
        );

        foreach ($stats as $typeId => $subtypes) {
            $subtypeWeights[$typeId] = [];

            foreach ($subtypes as $subtypeId => $data) {
                // Gewichte zusammensetzen:
                // 40% Anzahl der Bewertungen (Relevanz)
                // 40% Durchschnittsbewertung (Zufriedenheit)
                // 20% Konsistenz (1 / (1 + std_dev))

                $countFactor = ($data['count'] / $maxCount) * 40;
                $scoreFactor = ($data['avg_score'] / 5.0) * 40; // Annahme: 5 = max score
                $consistencyFactor = (1 / (1 + ($data['std_dev'] ?? 1))) * 20;

                $weight = $countFactor + $scoreFactor + $consistencyFactor;

                // Runde auf 1 Dezimalstelle für bessere Lesbarkeit
                $subtypeWeights[$typeId][$subtypeId] = round($weight, 2);
            }
        }

        return $subtypeWeights;
    }

    /**
     * Berechne Gewichte für jede Versicherungsart als Summe ihrer Unterarten
     */
    private function calculateTypeWeights(array $subtypeWeights): array
    {
        $typeWeights = [];

        foreach ($subtypeWeights as $typeId => $subtypes) {
            $typeWeights[$typeId] = round(
                array_sum($subtypes),
                2
            );
        }

        return $typeWeights;
    }

    /**
     * @param array<int, array{count: int, percent: float}> $hourStats
     * @return array<int, float>
     */
    private function calculateHourWeights(array $hourStats): array
    {
        $weights = RatingDistributionCatalog::defaultHourWeights();

        if ($hourStats === []) {
            return $weights;
        }

        $maxCount = max(1, max(array_column($hourStats, 'count')));

        foreach ($hourStats as $hour => $data) {
            $weights[(int) $hour] = round(((int) $data['count'] / $maxCount) * 100, 2);
        }

        return $weights;
    }

    /**
     * Ermittelt, wie gut oder schlecht reale Bewertungen am Ende bewertet wurden.
     *
     * @return array<string, mixed>
     */
    private function getScoreStatistics(): array
    {
        $connection = $this->analyticsConnection();
        $scores = DB::connection($connection)
            ->table('claim_ratings')
            ->whereIn('status', [
                'rated',
                'approved',
                'published',
            ])
            ->where('is_public', true)
            ->whereNotNull('rating_score')
            ->pluck('rating_score')
            ->map(fn (mixed $score): float => $this->normalizeScore((float) $score))
            ->values();

        $buckets = [];

        foreach (RatingDistributionCatalog::scoreBuckets() as $key => $bucket) {
            $buckets[$key] = [
                'label' => $bucket['label'],
                'min' => $bucket['min'],
                'max' => $bucket['max'],
                'count' => 0,
                'percent' => 0.0,
            ];
        }

        foreach ($scores as $score) {
            $bucketKey = $this->scoreBucketKey($score);

            if ($bucketKey !== null) {
                $buckets[$bucketKey]['count']++;
            }
        }

        $total = max(1, $scores->count());

        foreach ($buckets as $key => $bucket) {
            $buckets[$key]['percent'] = round(((int) $bucket['count'] / $total) * 100, 2);
        }

        return [
            'total' => $scores->count(),
            'average' => $scores->isNotEmpty() ? round($scores->avg(), 3) : null,
            'min' => $scores->isNotEmpty() ? round($scores->min(), 3) : null,
            'max' => $scores->isNotEmpty() ? round($scores->max(), 3) : null,
            'buckets' => $buckets,
        ];
    }

    /**
     * Ermittelt aggregierte Benutzer-Muster echter Bewertungen ohne einzelne Personen
     * oder vollstaendige E-Mail-Adressen zu speichern.
     *
     * @return array<string, mixed>
     */
    private function getUserStatistics(): array
    {
        $connection = $this->analyticsConnection();

        if (! Schema::connection($connection)->hasTable('users')) {
            return [
                'available' => false,
                'reason' => 'Die Base-Datenbank enthaelt keine users-Tabelle.',
                'source' => $this->userAnalysisSource(),
            ];
        }

        $select = [
            'ratings.id as rating_id',
            'ratings.user_id',
        ];

        foreach (['email', 'email_verified_at', 'role', 'status', 'created_at'] as $column) {
            $select[] = Schema::connection($connection)->hasColumn('users', $column)
                ? "users.{$column}"
                : DB::raw("NULL as {$column}");
        }

        $rows = DB::connection($connection)
            ->table('claim_ratings as ratings')
            ->leftJoin('users', 'users.id', '=', 'ratings.user_id')
            ->whereIn('ratings.status', [
                'rated',
                'approved',
                'published',
            ])
            ->where('ratings.is_public', true)
            ->select($select)
            ->get();

        $total = $rows->count();
        $withUser = $rows->filter(fn (object $row): bool => $row->user_id !== null)->count();
        $withoutUser = max(0, $total - $withUser);
        $uniqueUsers = $rows
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->count();

        $withEmail = $rows->filter(fn (object $row): bool => is_string($row->email ?? null) && trim((string) $row->email) !== '');
        $verifiedEmail = $rows->filter(fn (object $row): bool => ! empty($row->email_verified_at))->count();

        $domains = $withEmail
            ->map(fn (object $row): ?string => $this->emailDomain($row->email ?? null))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(10)
            ->map(fn (int $count, string $domain): array => [
                'domain' => $domain,
                'count' => $count,
                'percent' => $withEmail->count() > 0 ? round(($count / $withEmail->count()) * 100, 2) : 0.0,
            ])
            ->values()
            ->all();

        $roles = $rows
            ->map(fn (object $row): string => filled($row->role ?? null) ? (string) $row->role : 'ohne Rolle')
            ->countBy()
            ->sortDesc()
            ->map(fn (int $count, string $role): array => [
                'role' => $role,
                'count' => $count,
                'percent' => $total > 0 ? round(($count / $total) * 100, 2) : 0.0,
            ])
            ->values()
            ->all();

        $statuses = $rows
            ->map(function (object $row): string {
                if ($row->status === null) {
                    return 'unbekannt';
                }

                return filter_var($row->status, FILTER_VALIDATE_BOOLEAN) ? 'aktiv' : 'inaktiv';
            })
            ->countBy()
            ->sortDesc()
            ->map(fn (int $count, string $status): array => [
                'status' => $status,
                'count' => $count,
                'percent' => $total > 0 ? round(($count / $total) * 100, 2) : 0.0,
            ])
            ->values()
            ->all();

        return [
            'available' => true,
            'source' => $this->userAnalysisSource(),
            'total_ratings' => $total,
            'ratings_with_user' => $withUser,
            'ratings_without_user' => $withoutUser,
            'ratings_with_user_percent' => $total > 0 ? round(($withUser / $total) * 100, 2) : 0.0,
            'unique_users' => $uniqueUsers,
            'ratings_with_email' => $withEmail->count(),
            'verified_email_count' => $verifiedEmail,
            'verified_email_percent' => $withUser > 0 ? round(($verifiedEmail / $withUser) * 100, 2) : 0.0,
            'email_domains' => $domains,
            'roles' => $roles,
            'statuses' => $statuses,
            'privacy_note' => 'Es werden nur Domains und Zaehler gespeichert, keine echten E-Mail-Adressen oder Namen.',
        ];
    }

    /**
     * @param array<string, array{count?: int}> $scoreStats
     * @return array<string, float>
     */
    private function calculateScoreWeights(array $scoreStats): array
    {
        $weights = RatingDistributionCatalog::defaultScoreWeights();

        if ($scoreStats === []) {
            return $weights;
        }

        $maxCount = max(1, max(array_map(fn (array $bucket): int => (int) ($bucket['count'] ?? 0), $scoreStats)));

        foreach ($scoreStats as $key => $bucket) {
            if (array_key_exists($key, $weights)) {
                $weights[$key] = round(((int) ($bucket['count'] ?? 0) / $maxCount) * 100, 2);
            }
        }

        return $weights;
    }

    /**
     * Hole die letzte Analyse
     */
    public function getLastAnalysis(): ?array
    {
        return Setting::getValue('rating_generation', 'analysis');
    }

    /**
     * Hole detaillierte Statistiken für einen Typ oder alle
     */
    public function getDetailedStats(?int $typeId = null): array
    {
        $analysis = $this->getLastAnalysis();

        if (!$analysis) {
            return [];
        }

        if ($typeId === null) {
            return $analysis['stats'] ?? [];
        }

        return $analysis['stats'][$typeId] ?? [];
    }

    /**
     * Liefert ein kompaktes, anonymes Analyseprofil fuer die AI-Generierung.
     *
     * @return array<string, mixed>
     */
    public function generationProfileFor(int $typeId, int $subtypeId, ?int $scheduledHour = null): array
    {
        $analysis = $this->getLastAnalysis();

        if (! is_array($analysis)) {
            return [
                'available' => false,
                'reason' => 'Keine gespeicherte Bewertungsanalyse vorhanden.',
            ];
        }

        $stats = is_array($analysis['stats'] ?? null) ? $analysis['stats'] : [];
        $typeStats = $this->valueByKey($stats, $typeId, []);
        $typeStats = is_array($typeStats) ? $typeStats : [];
        $subtypeStats = $this->valueByKey($typeStats, $subtypeId, []);
        $subtypeStats = is_array($subtypeStats) ? $subtypeStats : [];

        $typeWeights = is_array($analysis['type_weights'] ?? null) ? $analysis['type_weights'] : [];
        $subtypeWeights = is_array($analysis['subtype_weights'] ?? null) ? $analysis['subtype_weights'] : [];
        $typeSubtypeWeights = $this->valueByKey($subtypeWeights, $typeId, []);
        $typeSubtypeWeights = is_array($typeSubtypeWeights) ? $typeSubtypeWeights : [];

        $hourStats = is_array($analysis['hour_stats'] ?? null) ? $analysis['hour_stats'] : [];
        $hourWeights = is_array($analysis['hour_weights'] ?? null) ? $analysis['hour_weights'] : [];
        $scoreStats = is_array($analysis['score_stats'] ?? null) ? $analysis['score_stats'] : [];
        $scoreWeights = is_array($analysis['score_weights'] ?? null) ? $analysis['score_weights'] : [];
        $userStats = is_array($analysis['user_stats'] ?? null) ? $analysis['user_stats'] : [];

        $scheduledHourStats = $scheduledHour !== null
            ? $this->valueByKey($hourStats, $scheduledHour, null)
            : null;

        return [
            'available' => true,
            'analyzed_at' => $analysis['analyzed_at'] ?? null,
            'total_ratings' => (int) ($analysis['total_ratings'] ?? $analysis['total_ratings_analyzed'] ?? 0),
            'selected_pair' => [
                'insurance_type_id' => $typeId,
                'insurance_subtype_id' => $subtypeId,
                'sample_count' => (int) ($subtypeStats['count'] ?? 0),
                'avg_score' => isset($subtypeStats['avg_score']) ? round((float) $subtypeStats['avg_score'], 2) : null,
                'min_score' => isset($subtypeStats['min_score']) ? round((float) $subtypeStats['min_score'], 2) : null,
                'max_score' => isset($subtypeStats['max_score']) ? round((float) $subtypeStats['max_score'], 2) : null,
                'std_dev' => isset($subtypeStats['std_dev']) ? round((float) $subtypeStats['std_dev'], 2) : null,
                'type_weight' => $this->toNullableFloat($this->valueByKey($typeWeights, $typeId, null)),
                'subtype_weight' => $this->toNullableFloat($this->valueByKey($typeSubtypeWeights, $subtypeId, null)),
            ],
            'scheduled_hour' => [
                'hour' => $scheduledHour,
                'observed_count' => is_array($scheduledHourStats) ? (int) ($scheduledHourStats['count'] ?? 0) : null,
                'observed_percent' => is_array($scheduledHourStats) ? (float) ($scheduledHourStats['percent'] ?? 0) : null,
                'weight' => $scheduledHour !== null ? $this->toNullableFloat($this->valueByKey($hourWeights, $scheduledHour, null)) : null,
            ],
            'top_observed_hours' => $this->topHours($hourStats),
            'score_distribution' => [
                'stats' => $scoreStats,
                'weights' => $this->mergeScoreWeights($scoreWeights),
                'selected_pair_average' => isset($subtypeStats['avg_score']) ? round((float) $subtypeStats['avg_score'], 2) : null,
            ],
            'user_patterns' => [
                'available' => (bool) ($userStats['available'] ?? false),
                'source' => $userStats['source'] ?? [],
                'ratings_with_user_percent' => $userStats['ratings_with_user_percent'] ?? null,
                'verified_email_percent' => $userStats['verified_email_percent'] ?? null,
                'top_email_domains' => array_slice($userStats['email_domains'] ?? [], 0, 5),
                'roles' => $userStats['roles'] ?? [],
                'privacy_note' => 'Nur aggregierte Benutzer-Muster verwenden, keine echten Namen oder E-Mail-Adressen uebernehmen.',
            ],
            'instructions' => [
                'Nutze nur diese aggregierten Muster, keine echten Einzelfaelle.',
                'Antworten muessen als interne synthetische Testdaten erkennbar bleiben.',
                'Score-, Zeit- und Mengenmuster duerfen Orientierung geben, aber keine echten Erfahrungsberichte kopieren.',
            ],
        ];
    }

    private function analyticsConnection(): string
    {
        $connection = env('ANALYTICS_DB_CONNECTION', 'mysql_analytics');
        $settings = Setting::getValue('database', 'config') ?? [];

        if (! is_array($settings) || empty($settings['database'])) {
            return $connection;
        }

        $base = config("database.connections.{$connection}", config('database.connections.mysql'));

        config()->set("database.connections.{$connection}", array_merge($base, [
            'driver' => 'mysql',
            'host' => $settings['host'] ?? '127.0.0.1',
            'port' => (string) ($settings['port'] ?? '3306'),
            'database' => $settings['database'],
            'username' => $settings['username'] ?? 'root',
            'password' => $settings['password'] ?? '',
        ]));

        DB::purge($connection);

        return $connection;
    }

    /**
     * @param array<int|string, mixed> $weights
     * @return array<int, float>
     */
    private function mergeTypeWeights(array $weights): array
    {
        $merged = RatingDistributionCatalog::defaultTypeWeights();

        foreach ($merged as $typeId => $default) {
            $merged[$typeId] = $this->toWeight($weights[$typeId] ?? $weights[(string) $typeId] ?? $default);
        }

        return $merged;
    }

    /**
     * @param array<int|string, mixed> $weights
     * @return array<int, array<int, float>>
     */
    private function mergeSubtypeWeights(array $weights): array
    {
        $merged = RatingDistributionCatalog::defaultSubtypeWeights();

        foreach ($merged as $typeId => $subtypes) {
            $typeWeights = $weights[$typeId] ?? $weights[(string) $typeId] ?? [];
            $typeWeights = is_array($typeWeights) ? $typeWeights : [];

            foreach ($subtypes as $subtypeId => $default) {
                $merged[$typeId][$subtypeId] = $this->toWeight(
                    $typeWeights[$subtypeId]
                    ?? $typeWeights[(string) $subtypeId]
                    ?? $default
                );
            }
        }

        return $merged;
    }

    /**
     * @param array<int|string, mixed> $weights
     * @return array<int, float>
     */
    private function mergeHourWeights(array $weights): array
    {
        $merged = RatingDistributionCatalog::defaultHourWeights();

        foreach ($merged as $hour => $default) {
            $merged[$hour] = $this->toWeight($weights[$hour] ?? $weights[(string) $hour] ?? $default);
        }

        return $merged;
    }

    /**
     * @param array<string, mixed> $weights
     * @return array<string, float>
     */
    private function mergeScoreWeights(array $weights): array
    {
        $merged = RatingDistributionCatalog::defaultScoreWeights();

        foreach ($merged as $key => $default) {
            $merged[$key] = $this->toWeight($weights[$key] ?? $default);
        }

        return $merged;
    }

    private function toWeight(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return max(0.0, (float) str_replace(',', '.', (string) $value));
    }

    private function toNullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) str_replace(',', '.', (string) $value), 2);
    }

    private function valueByKey(array $array, int|string $key, mixed $default = null): mixed
    {
        return $array[$key] ?? $array[(string) $key] ?? $default;
    }

    private function normalizeScore(float $score): float
    {
        if ($score > 1.0) {
            $score = $score / 5;
        }

        return max(0.0, min(0.99, $score));
    }

    private function scoreBucketKey(float $score): ?string
    {
        foreach (RatingDistributionCatalog::scoreBuckets() as $key => $bucket) {
            if ($score >= (float) $bucket['min'] && $score <= (float) $bucket['max']) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function userAnalysisSource(): array
    {
        $databaseSettings = Setting::getValue('database', 'config');
        $databaseSettings = is_array($databaseSettings) ? $databaseSettings : [];

        return [
            'base_database' => (string) ($databaseSettings['database'] ?? env('ANALYTICS_DB_DATABASE', 'regulierungs-check')),
            'rating_user_link' => 'claim_ratings.user_id -> users.id',
            'email_pattern' => 'Nur Domain aus users.email, keine vollstaendige Adresse.',
            'visibility_filter' => 'claim_ratings.is_public = true und Status rated/approved/published',
        ];
    }

    private function emailDomain(mixed $email): ?string
    {
        if (! is_string($email) || ! str_contains($email, '@')) {
            return null;
        }

        $domain = strtolower(trim(substr(strrchr($email, '@') ?: '', 1)));

        return $domain !== '' ? $domain : null;
    }

    /**
     * @return array<int, array{hour: int, count: int, percent: float}>
     */
    private function topHours(array $hourStats): array
    {
        uasort($hourStats, fn (mixed $left, mixed $right): int => $this->hourCount($right) <=> $this->hourCount($left));

        return collect($hourStats)
            ->take(8)
            ->map(function (mixed $data, int|string $hour): array {
                $data = is_array($data) ? $data : [];

                return [
                    'hour' => (int) $hour,
                    'count' => (int) ($data['count'] ?? 0),
                    'percent' => round((float) ($data['percent'] ?? 0), 2),
                ];
            })
            ->values()
            ->all();
    }

    private function hourCount(mixed $data): int
    {
        return is_array($data) ? (int) ($data['count'] ?? 0) : 0;
    }
}
