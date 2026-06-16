<?php

namespace App\Jobs;

use App\Models\ClaimRating;
use App\Models\Setting;
use App\Models\SyntheticRatingUser;
use App\Support\Database\RegCheckDatabase;
use App\Support\Rating\RatingDistributionCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PlanSyntheticClaimRatings implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(
        public ?string $date = null,
        public ?int $targetCount = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        $formFillingSettings = $this->formFillingSettings();
        $disabledReason = $this->generationDisabledReason($formFillingSettings);
        $report = [
            'ok' => false,
            'reason' => null,
            'form_filling' => $formFillingSettings,
            'target_date' => null,
            'target_count' => 0,
            'already_planned' => 0,
            'remaining' => 0,
            'created_count' => 0,
            'skipped_count' => 0,
            'eligible_pairs' => 0,
            'weight_fallback' => false,
            'created' => [],
            'skipped' => [],
        ];

        if ($disabledReason) {
            Log::info('Synthetic rating planning skipped because form filling is disabled.');
            $report['reason'] = $disabledReason;

            return $report;
        }

        $settings = $this->ratingSettings();
        $targetDate = CarbonImmutable::parse($this->date ?: now()->toDateString())->startOfDay();
        $targetCount = max(0, $this->targetCount ?? (int) ($settings['daily_target'] ?? 0));
        $report['target_date'] = $targetDate->toDateString();
        $report['target_count'] = $targetCount;

        if ($targetCount <= 0) {
            $report['reason'] = 'daily_target ist 0 oder kleiner.';

            return $report;
        }

        $alreadyPlanned = ClaimRating::query()
            ->whereDate('scheduled_for', $targetDate->toDateString())
            ->where('data->synthetic', true)
            ->count();

        $remaining = max(0, $targetCount - $alreadyPlanned);
        $report['already_planned'] = $alreadyPlanned;
        $report['remaining'] = $remaining;

        if ($remaining <= 0) {
            $report['ok'] = true;
            $report['reason'] = 'Tagesziel ist bereits erreicht.';

            return $report;
        }

        $connection = RegCheckDatabase::connectionName();
        $report['connection'] = $connection;
        $eligiblePairs = $this->eligiblePairs($connection, $settings);
        $report['eligible_pairs'] = count($eligiblePairs['pairs']);
        $report['weight_fallback'] = $eligiblePairs['weight_fallback'];

        if ($eligiblePairs['pairs'] === []) {
            $report['reason'] = 'Keine aktiven Typ-/Untertyp-Kombinationen in der Base-Datenbank gefunden.';

            return $report;
        }

        for ($i = 0; $i < $remaining; $i++) {
            $pair = $this->weightedRandomPair($eligiblePairs['pairs']);
            $typeId = $pair['type_id'] ?? null;
            $subtypeId = $pair['subtype_id'] ?? null;

            if (! $typeId || ! $subtypeId) {
                Log::warning('Synthetic rating planning skipped one slot because no type/subtype weight is available.');
                $report['skipped'][] = [
                    'slot' => $i + 1,
                    'type_id' => $typeId,
                    'subtype_id' => $subtypeId,
                    'reason' => 'Keine positive Typ-/Untertyp-Gewichtung verfuegbar.',
                ];
                continue;
            }

            try {
                $baseContext = $this->resolveBaseContext($connection, $typeId, $subtypeId, $settings);
            } catch (\Throwable $exception) {
                Log::warning('Synthetic rating planning skipped one slot because base context could not be resolved.', [
                    'type_id' => $typeId,
                    'subtype_id' => $subtypeId,
                    'message' => $exception->getMessage(),
                ]);
                $report['skipped'][] = [
                    'slot' => $i + 1,
                    'type_id' => $typeId,
                    'subtype_id' => $subtypeId,
                    'reason' => $exception->getMessage(),
                ];
                continue;
            }

            $scheduledFor = $this->scheduledTime($targetDate, $settings['hour_weights'] ?? []);
            $targetScoreProfile = $this->targetScoreProfile($settings['score_weights'] ?? []);
            $syntheticUser = $this->createSyntheticUser();
            $syntheticUserProfile = $syntheticUser->publicProfile();

            $claimRating = ClaimRating::create([
                'synthetic_rating_user_id' => $syntheticUser->id,
                'insurance_type_id' => $typeId,
                'insurance_subtype_id' => $subtypeId,
                'insurance_id' => $baseContext['insurance']['id'],
                'rating_questionnaire_versions_id' => $baseContext['questionnaire_version']['id'] ?? null,
                'answers' => null,
                'status' => ClaimRating::STATUS_SCHEDULED,
                'scheduled_for' => $scheduledFor,
                'attachments' => $this->defaultAttachments($baseContext),
                'rating_score' => null,
                'moderator_comment' => 'Interner synthetischer Testdatensatz. Nicht als echte Bewertung verwenden.',
                'is_public' => false,
                'admin_review' => [
                    'synthetic' => true,
                    'do_not_publish' => true,
                ],
                'data' => [
                    'synthetic' => true,
                    'synthetic_kind' => 'claim_rating',
                    'purpose' => 'internal_testing',
                    'do_not_publish' => true,
                    'planning' => [
                        'planned_at' => now()->toDateTimeString(),
                        'planned_for' => $scheduledFor->toDateTimeString(),
                        'planned_by' => self::class,
                        'target_score_profile' => $targetScoreProfile,
                        'synthetic_user_profile' => $syntheticUserProfile,
                    ],
                    'base_context' => $baseContext,
                ],
                'verification_hash' => (string) Str::uuid(),
            ]);

            $report['created'][] = [
                'id' => $claimRating->id,
                'scheduled_for' => $scheduledFor->format('Y-m-d H:i:s'),
                'type_id' => $typeId,
                'type_name' => $baseContext['insurance_type']['name'] ?? null,
                'subtype_id' => $subtypeId,
                'subtype_name' => $baseContext['insurance_subtype']['name'] ?? null,
                'insurance_id' => $baseContext['insurance']['id'] ?? null,
                'insurance_name' => $baseContext['insurance']['name'] ?? null,
                'questionnaire_version_id' => $baseContext['questionnaire_version']['id'] ?? null,
                'target_score_label' => $targetScoreProfile['label'] ?? null,
                'synthetic_rating_user_id' => $syntheticUser->id,
                'synthetic_user_email' => $syntheticUserProfile['email'] ?? null,
            ];
        }

        $report['created_count'] = count($report['created']);
        $report['skipped_count'] = count($report['skipped']);
        $report['ok'] = $report['created_count'] > 0 || $report['skipped_count'] === 0;
        $report['reason'] = $report['created_count'] > 0
            ? 'Planung abgeschlossen.'
            : 'Keine Bewertung konnte geplant werden.';

        return $report;
    }

    /**
     * @return array{pairs: array<int, array<string, mixed>>, weight_fallback: bool}
     */
    private function eligiblePairs(string $connection, array $settings): array
    {
        $rows = DB::connection($connection)
            ->table('insurance_type_insurance_subtype as itis')
            ->join('insurance_types as types', 'types.id', '=', 'itis.insurance_type_id')
            ->join('insurance_subtypes as subtypes', 'subtypes.id', '=', 'itis.insurance_subtype_id')
            ->where('types.is_active', true)
            ->where('subtypes.is_active', true)
            ->select([
                'types.id as type_id',
                'types.name as type_name',
                'subtypes.id as subtype_id',
                'subtypes.name as subtype_name',
            ])
            ->get();

        $pairs = [];

        foreach ($rows as $row) {
            $typeId = (int) $row->type_id;
            $subtypeId = (int) $row->subtype_id;
            $typeWeight = $this->weightFrom($settings['type_weights'] ?? [], $typeId);
            $subtypeWeights = $settings['subtype_weights'][$typeId]
                ?? $settings['subtype_weights'][(string) $typeId]
                ?? [];
            $subtypeWeight = $this->weightFrom(is_array($subtypeWeights) ? $subtypeWeights : [], $subtypeId);
            $weight = $typeWeight * $subtypeWeight;

            if ($weight > 0) {
                $pairs[] = [
                    'type_id' => $typeId,
                    'type_name' => (string) $row->type_name,
                    'subtype_id' => $subtypeId,
                    'subtype_name' => (string) $row->subtype_name,
                    'weight' => $weight,
                ];
            }
        }

        if ($pairs !== []) {
            return [
                'pairs' => $pairs,
                'weight_fallback' => false,
            ];
        }

        return [
            'pairs' => $rows
                ->map(fn (object $row): array => [
                    'type_id' => (int) $row->type_id,
                    'type_name' => (string) $row->type_name,
                    'subtype_id' => (int) $row->subtype_id,
                    'subtype_name' => (string) $row->subtype_name,
                    'weight' => 1.0,
                ])
                ->all(),
            'weight_fallback' => true,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $pairs
     * @return array<string, mixed>
     */
    private function weightedRandomPair(array $pairs): array
    {
        $total = array_sum(array_map(fn (array $pair): float => (float) ($pair['weight'] ?? 0), $pairs));

        if ($total <= 0) {
            return $pairs[array_rand($pairs)] ?? [];
        }

        $target = lcg_value() * $total;
        $cursor = 0.0;

        foreach ($pairs as $pair) {
            $cursor += (float) ($pair['weight'] ?? 0);

            if ($target <= $cursor) {
                return $pair;
            }
        }

        return $pairs[array_key_last($pairs)] ?? [];
    }

    /**
     * @param array<int|string, mixed> $weights
     */
    private function weightFrom(array $weights, int $id): float
    {
        return max(0.0, (float) ($weights[$id] ?? $weights[(string) $id] ?? 0));
    }

    /**
     * @return array<string, mixed>
     */
    private function formFillingSettings(): array
    {
        $settings = Setting::getValue('form_filling', 'config') ?? [];

        return is_array($settings) ? $settings : [];
    }

    private function generationDisabledReason(array $settings): ?string
    {
        if (! (bool) ($settings['enabled'] ?? false)) {
            return 'Formular-/Generierungs-Einstellung ist deaktiviert.';
        }

        if (($settings['mode'] ?? 'disabled') === 'disabled') {
            return 'Formular-/Generierungs-Modus steht auf disabled.';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function ratingSettings(): array
    {
        $settings = Setting::getValue('rating_generation', 'settings') ?? [];

        return is_array($settings) ? $settings : [];
    }

    /**
     * @param array<int|string, mixed> $weights
     */
    private function weightedRandom(array $weights): ?int
    {
        $normalized = [];

        foreach ($weights as $id => $weight) {
            $weight = max(0.0, (float) $weight);

            if ($weight > 0) {
                $normalized[(int) $id] = $weight;
            }
        }

        if ($normalized === []) {
            return null;
        }

        $target = lcg_value() * array_sum($normalized);
        $cursor = 0.0;

        foreach ($normalized as $id => $weight) {
            $cursor += $weight;

            if ($target <= $cursor) {
                return $id;
            }
        }

        return (int) array_key_last($normalized);
    }

    /**
     * @param array<int|string, mixed> $hourWeights
     */
    private function scheduledTime(CarbonImmutable $targetDate, array $hourWeights): CarbonImmutable
    {
        $hour = $this->weightedRandom($hourWeights) ?? random_int(8, 20);
        $scheduledFor = $targetDate->setTime($hour, random_int(0, 59), random_int(0, 59));

        if ($scheduledFor->lessThan(now()->addMinutes(5))) {
            return CarbonImmutable::instance(now()->addMinutes(random_int(5, 90)));
        }

        return $scheduledFor;
    }

    /**
     * @param array<string, mixed> $scoreWeights
     * @return array<string, mixed>
     */
    private function targetScoreProfile(array $scoreWeights): array
    {
        $bucketKey = $this->weightedRandomScoreBucket($scoreWeights) ?? 'average';
        $bucket = RatingDistributionCatalog::scoreBuckets()[$bucketKey] ?? RatingDistributionCatalog::scoreBuckets()['average'];
        $min = (float) $bucket['min'];
        $max = (float) $bucket['max'];
        $target = $min + (lcg_value() * max(0.0, $max - $min));

        return [
            'bucket' => $bucketKey,
            'label' => $bucket['label'],
            'min_score' => $min,
            'max_score' => $max,
            'target_score' => round($target, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createSyntheticUser(): SyntheticRatingUser
    {
        return SyntheticRatingUser::createForClaimRating();
    }

    /**
     * @param array<string, mixed> $scoreWeights
     */
    private function weightedRandomScoreBucket(array $scoreWeights): ?string
    {
        $weights = [];

        foreach (RatingDistributionCatalog::scoreBuckets() as $key => $bucket) {
            $weight = max(0.0, (float) ($scoreWeights[$key] ?? $bucket['default_weight']));

            if ($weight > 0) {
                $weights[$key] = $weight;
            }
        }

        if ($weights === []) {
            return null;
        }

        $target = lcg_value() * array_sum($weights);
        $cursor = 0.0;

        foreach ($weights as $key => $weight) {
            $cursor += $weight;

            if ($target <= $cursor) {
                return $key;
            }
        }

        return (string) array_key_last($weights);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveBaseContext(string $connection, int $typeId, int $subtypeId, array $settings): array
    {
        $type = DB::connection($connection)
            ->table('insurance_types')
            ->where('id', $typeId)
            ->first(['id', 'name']);

        $subtype = DB::connection($connection)
            ->table('insurance_subtypes')
            ->where('id', $subtypeId)
            ->first(['id', 'name', 'average_rating_speed']);

        if (! $type || ! $subtype) {
            throw new \RuntimeException('Insurance type or subtype not found in base database.');
        }

        $insurances = DB::connection($connection)
            ->table('insurances')
            ->join('insurance_insurance_type as iit', 'iit.insurance_id', '=', 'insurances.id')
            ->join('insurance_type_insurance_subtype as itis', 'itis.insurance_type_id', '=', 'iit.insurance_type_id')
            ->where('iit.insurance_type_id', $typeId)
            ->where('itis.insurance_subtype_id', $subtypeId)
            ->where('insurances.is_active', true)
            ->get(['insurances.id', 'insurances.name']);

        if ($insurances->isEmpty()) {
            $insurances = DB::connection($connection)
                ->table('insurances')
                ->join('insurance_insurance_type as iit', 'iit.insurance_id', '=', 'insurances.id')
                ->where('iit.insurance_type_id', $typeId)
                ->where('insurances.is_active', true)
                ->get(['insurances.id', 'insurances.name']);
        }

        if ($insurances->isEmpty()) {
            throw new \RuntimeException('No active insurance found for selected type/subtype.');
        }

        $insurance = $this->weightedRandomInsurance($insurances->all(), $settings['provider_weights'] ?? []);

        $questionnaireVersion = DB::connection($connection)
            ->table('rating_questionnaire_versions')
            ->where('insurance_subtype_id', $subtypeId)
            ->orderByDesc('is_active')
            ->orderByDesc('version_number')
            ->orderByDesc('id')
            ->first(['id', 'version_number', 'snapshot']);

        return [
            'insurance_type' => [
                'id' => (int) $type->id,
                'name' => (string) $type->name,
            ],
            'insurance_subtype' => [
                'id' => (int) $subtype->id,
                'name' => (string) $subtype->name,
                'average_rating_speed' => $subtype->average_rating_speed !== null ? (float) $subtype->average_rating_speed : 30,
            ],
            'insurance' => [
                'id' => (int) $insurance->id,
                'name' => (string) $insurance->name,
            ],
            'questionnaire_version' => $questionnaireVersion ? [
                'id' => (int) $questionnaireVersion->id,
                'version_number' => (int) $questionnaireVersion->version_number,
                'snapshot' => $this->decodeJson($questionnaireVersion->snapshot),
            ] : null,
        ];
    }

    /**
     * @param array<int, object> $insurances
     * @param array<int|string, mixed> $providerWeights
     */
    private function weightedRandomInsurance(array $insurances, array $providerWeights): object
    {
        $weighted = [];

        foreach ($insurances as $insurance) {
            $id = (int) $insurance->id;
            $weight = max(0.0, (float) ($providerWeights[$id] ?? $providerWeights[(string) $id] ?? 1.0));

            if ($weight > 0) {
                $weighted[] = [
                    'insurance' => $insurance,
                    'weight' => $weight,
                ];
            }
        }

        if ($weighted === []) {
            return $insurances[array_rand($insurances)];
        }

        $target = lcg_value() * array_sum(array_column($weighted, 'weight'));
        $cursor = 0.0;

        foreach ($weighted as $entry) {
            $cursor += (float) $entry['weight'];

            if ($target <= $cursor) {
                return $entry['insurance'];
            }
        }

        return $weighted[array_key_last($weighted)]['insurance'];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultAttachments(array $baseContext): array
    {
        return [
            'scorings' => [
                'regulation_speed' => null,
                'customer_service' => null,
                'fairness' => null,
                'transparency' => null,
                'overall_satisfaction' => null,
                'questions' => [],
            ],
            'eval_details' => [
                'insuranceSubtype_average_rating_speed' => $baseContext['insurance_subtype']['average_rating_speed'] ?? null,
                'insuranceSubtype_insurance_average_rating_speed' => null,
            ],
            'questionnaire_snapshot' => $baseContext['questionnaire_version']['snapshot'] ?? [],
        ];
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
