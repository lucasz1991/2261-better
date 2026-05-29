<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\Rating\RatingDistributionCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            
            if (empty($subtypeStats)) {
                Log::warning('No published ratings found for analysis');
                return [
                    'type_weights' => [],
                    'subtype_weights' => [],
                    'hour_weights' => $hourWeights,
                    'hour_stats' => $hourStats,
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

    private function toWeight(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return max(0.0, (float) str_replace(',', '.', (string) $value));
    }
}
