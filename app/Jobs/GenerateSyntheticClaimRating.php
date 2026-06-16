<?php

namespace App\Jobs;

use App\Models\ClaimRating;
use App\Models\Setting;
use App\Models\SyntheticRatingUser;
use App\Services\AiConnection;
use App\Services\BaseClaimRatingPublisher;
use App\Services\RatingDistributionAnalyzer;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateSyntheticClaimRating implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const AI_VARIATION_SETTING_TYPE = 'claim_rating_ai';
    private const AI_VARIATION_SETTING_KEY = 'variation_settings';

    public int $tries = 2;
    public int $timeout = 240;

    public function __construct(
        public ClaimRating $claimRating,
        public bool $markExecuted = true,
    ) {
    }

    public function handle(
        AiConnection $aiConnection,
        RatingDistributionAnalyzer $analyzer,
        BaseClaimRatingPublisher $basePublisher,
    ): void
    {
        $this->claimRating->refresh();

        if ($this->claimRating->executed_at) {
            return;
        }

        $state = [
            'status' => ClaimRating::STATUS_PROCESSING,
            'execution_attempts' => ($this->claimRating->execution_attempts ?? 0) + 1,
            'last_execution_error' => null,
        ];

        if ($this->markExecuted) {
            $state['execution_started_at'] = now();
        }

        $this->claimRating->forceFill($state)->saveQuietly();

        try {
            $baseContext = $this->claimRating->data['base_context'] ?? [];

            if (! is_array($baseContext) || $baseContext === []) {
                throw new \RuntimeException('Missing base context for synthetic rating generation.');
            }

            $this->ensureSyntheticUserForAiRun();

            $payload = $aiConnection->generateSyntheticClaimRatingPayload([
                'trainContent' => $this->generationPrompt(),
                'context' => $this->generationContext($baseContext, $analyzer),
            ]);

            if (! is_array($payload['answers'] ?? null) || $payload['answers'] === []) {
                throw new \RuntimeException('AI did not return usable answers.');
            }

            $answers = $this->normalizeAnswers($payload['answers'], $baseContext);
            $attachments = $this->claimRating->attachments ?? [];
            $data = $this->claimRating->data ?? [];

            $attachments['questionnaire_snapshot'] = $baseContext['questionnaire_version']['snapshot'] ?? [];
            $attachments['generation']['comment'] = $payload['generation_comment'] ?? '';
            $attachments['generation']['generated_at'] = now()->toDateTimeString();

            $data['ai_generation'] = [
                'generated_at' => now()->toDateTimeString(),
                'generator' => self::class,
                'prepared_only' => ! $this->markExecuted,
                'scheduled_for' => optional($this->claimRating->scheduled_for)->toDateTimeString(),
                'comment' => $payload['generation_comment'] ?? '',
            ];

            $this->claimRating->forceFill([
                'insurance_type_id' => $baseContext['insurance_type']['id'] ?? $this->claimRating->insurance_type_id,
                'insurance_subtype_id' => $baseContext['insurance_subtype']['id'] ?? $this->claimRating->insurance_subtype_id,
                'insurance_id' => $baseContext['insurance']['id'] ?? $this->claimRating->insurance_id,
                'rating_questionnaire_versions_id' => $baseContext['questionnaire_version']['id'] ?? $this->claimRating->rating_questionnaire_versions_id,
                'answers' => $answers,
                'attachments' => $attachments,
                'data' => $data,
                'status' => ClaimRating::STATUS_PROCESSING,
                'executed_at' => null,
                'is_public' => true,
                'moderator_comment' => null,
            ])->saveQuietly();

            ClaimRatingAIEval::dispatchSync($this->claimRating->fresh(), false, $this->markExecuted);

            if ($this->markExecuted) {
                $basePublisher->publish($this->claimRating->fresh());
            }
        } catch (\Throwable $exception) {
            $this->claimRating->forceFill([
                'status' => ClaimRating::STATUS_FAILED,
                'last_execution_error' => $exception->getMessage(),
            ])->saveQuietly();

            Log::error('Synthetic claim rating generation failed.', [
                'claim_rating_id' => $this->claimRating->id,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }

    private function ensureSyntheticUserForAiRun(): void
    {
        $syntheticUser = SyntheticRatingUser::ensureForClaimRating($this->claimRating);
        $this->claimRating->refresh();

        $data = $this->claimRating->data ?? [];
        $data['planning']['synthetic_user_profile'] = $syntheticUser->fresh()->publicProfile();

        $this->claimRating->forceFill([
            'synthetic_rating_user_id' => $syntheticUser->id,
            'data' => $data,
        ])->saveQuietly();

        $this->claimRating->refresh();
    }

    private function generationPrompt(): string
    {
        return <<<'TEXT'
Du erzeugst ausschliesslich interne synthetische Testdaten fuer ein Bewertungsformular.
Diese Daten duerfen nicht wie echte Kundenerfahrungen behandelt oder veroeffentlicht werden.

Erzeuge plausible, sachliche Formularantworten ohne personenbezogene Daten, ohne reale Fallnummern und ohne extreme oder diffamierende Behauptungen.
Nutze die uebergebenen Versicherungs- und Formular-Metadaten.
Wenn ein Ziel-Score-Profil uebergeben wird, sollen Tonalitaet, Regulierungsergebnis, Dauer und Detailantworten dazu passen.
Nutze die uebergebenen AI-Variationsvorgaben: variiere Regulierungstypen gemaess Gewichtung und waehle realistische Schadenregulierungsdauern.
Die Dauer darf nicht unrealistisch kurz sein; nutze mindestens die vorgegebene Mindestdauer und mische kurze, normale und lange Verlaeufe passend zum Versicherungskontext.
Alle Texte muessen auf Deutsch sein.

Antwort ausschliesslich als JSON:
{
  "answers": {
    "regulationType": "vollzahlung|teilzahlung|ablehnung|austehend",
    "regulationDetails": {
      "selected_values": ["Kurzer Grund"],
      "textarea_value": "Optionaler kurzer Zusatztext oder null"
    },
    "contractDetails": {
      "contract_coverage_amount": 10000,
      "contract_deductible_amount": 150,
      "claim_amount": 1200,
      "claim_settlement_amount": 1050,
      "textarea_value": "Optionaler kurzer Zusatztext oder null"
    },
    "selectedDates": {
      "started_at": "01.04.2026",
      "ended_at": "20.04.2026",
      "duration_days": 19
    },
    "variable": {
      "question_title": "Antwort passend zum Fragetyp"
    }
  },
  "generation_comment": "Kurz erklaeren, welche Art synthetischer Fall erzeugt wurde."
}
TEXT;
    }

    /**
     * @return array<string, mixed>
     */
    private function generationContext(array $baseContext, RatingDistributionAnalyzer $analyzer): array
    {
        $generationProfile = $analyzer->generationProfileFor(
            (int) ($baseContext['insurance_type']['id'] ?? $this->claimRating->insurance_type_id),
            (int) ($baseContext['insurance_subtype']['id'] ?? $this->claimRating->insurance_subtype_id),
            $this->claimRating->scheduled_for ? (int) $this->claimRating->scheduled_for->format('G') : null,
        );

        return [
            'safety' => [
                'synthetic' => true,
                'internal_only' => true,
                'do_not_publish' => true,
                'use_only_aggregate_patterns' => true,
            ],
            'insurance_type' => $baseContext['insurance_type'] ?? [],
            'insurance_subtype' => $baseContext['insurance_subtype'] ?? [],
            'insurance' => $baseContext['insurance'] ?? [],
            'scheduled_for' => optional($this->claimRating->scheduled_for)->toDateTimeString(),
            'target_score_profile' => $this->claimRating->data['planning']['target_score_profile'] ?? null,
            'synthetic_user_profile' => $this->syntheticUserProfile(),
            'observed_generation_profile' => $generationProfile,
            'questionnaire_snapshot' => $this->variableQuestions($baseContext),
            'allowed_regulation_types' => ['vollzahlung', 'teilzahlung', 'ablehnung', 'austehend'],
            'ai_variation_settings' => $this->variationSettings(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function syntheticUserProfile(): ?array
    {
        $syntheticUser = $this->claimRating->syntheticUser;

        if ($syntheticUser) {
            return $syntheticUser->publicProfile();
        }

        $profile = $this->claimRating->data['planning']['synthetic_user_profile'] ?? null;

        return is_array($profile) ? $profile : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function variableQuestions(array $baseContext): array
    {
        $snapshot = $baseContext['questionnaire_version']['snapshot'] ?? [];

        if (! is_array($snapshot)) {
            return [];
        }

        return collect($snapshot)
            ->filter(fn (mixed $question): bool => is_array($question) && ! empty($question['title']))
            ->map(fn (array $question): array => [
                'title' => $question['title'],
                'question_text' => $question['question_text'] ?? '',
                'type' => $question['type'] ?? 'text',
                'is_required' => (bool) ($question['is_required'] ?? true),
                'input_constraints' => $question['input_constraints'] ?? [],
                'meta' => $question['meta'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeAnswers(array $aiAnswers, array $baseContext): array
    {
        $regulationType = $this->normalizeRegulationType($aiAnswers['regulationType'] ?? null, $this->variationSettings());
        $closed = $regulationType !== 'austehend';
        $dates = $this->normalizeDates($aiAnswers['selectedDates'] ?? [], $closed);

        $answers = [
            'insuranceTypeId' => $baseContext['insurance_type']['id'] ?? $this->claimRating->insurance_type_id,
            'insuranceSubTypeId' => $baseContext['insurance_subtype']['id'] ?? $this->claimRating->insurance_subtype_id,
            'insuranceId' => $baseContext['insurance']['id'] ?? $this->claimRating->insurance_id,
            'thirdPartyInsurance' => false,
            'thirdPartyInsuranceHasContact' => false,
            'regulationType' => $regulationType,
            'regulationDetails' => $this->normalizeRegulationDetails($aiAnswers['regulationDetails'] ?? []),
            'regulationComment' => null,
            'contractDetails' => $this->normalizeContractDetails($aiAnswers['contractDetails'] ?? [], $regulationType),
            'selectedDates' => $dates,
            'is_closed' => $closed,
        ];

        $variableAnswers = is_array($aiAnswers['variable'] ?? null) ? $aiAnswers['variable'] : [];

        foreach ($this->variableQuestions($baseContext) as $question) {
            $title = (string) $question['title'];
            $answers[$title] = array_key_exists($title, $variableAnswers)
                ? $this->normalizeQuestionAnswer($question, $variableAnswers[$title])
                : $this->fallbackQuestionAnswer($question);
        }

        return $answers;
    }

    private function normalizeRegulationType(mixed $value, array $settings): string
    {
        $value = strtolower(trim((string) $value));
        $allowed = ['vollzahlung', 'teilzahlung', 'ablehnung', 'austehend'];
        $isAllowed = in_array($value, $allowed, true);
        $overridePercent = max(0, min(100, (int) ($settings['regulation_ai_override_percent'] ?? 20)));

        if (! $isAllowed) {
            return $this->weightedRegulationType($settings);
        }

        if ($overridePercent > 0 && random_int(1, 100) <= $overridePercent) {
            return $this->weightedRegulationType($settings);
        }

        return $value;
    }

    /**
     * @return array{started_at: string, ended_at: string|null}
     */
    private function normalizeDates(mixed $value, bool $closed): array
    {
        $value = is_array($value) ? $value : [];
        $settings = $this->variationSettings();
        $minDays = (int) ($settings['min_duration_days'] ?? 14);
        $maxDays = max($minDays + 7, (int) ($settings['max_duration_days'] ?? 210));
        $averageDays = max($minDays, (int) ($this->claimRating->data['base_context']['insurance_subtype']['average_rating_speed'] ?? 45));

        $durationDays = isset($value['duration_days']) && is_numeric($value['duration_days'])
            ? (int) $value['duration_days']
            : $this->realisticDurationDays($averageDays, $settings);
        $durationDays = max($minDays, min($maxDays, $durationDays));

        $scheduledFor = $this->claimRating->scheduled_for
            ? CarbonImmutable::instance($this->claimRating->scheduled_for)
            : CarbonImmutable::instance(now());

        $endedAt = null;

        if ($closed) {
            $offsetMin = max(0, (int) ($settings['closed_ended_min_offset_days'] ?? 3));
            $offsetMax = max($offsetMin + 1, (int) ($settings['closed_ended_max_offset_days'] ?? 60));
            $endedAt = $scheduledFor->subDays(random_int($offsetMin, $offsetMax));
        }

        $startedAt = ($endedAt ?: $scheduledFor)->subDays($durationDays);

        return [
            'started_at' => $startedAt->format('d.m.Y'),
            'ended_at' => $endedAt?->format('d.m.Y'),
        ];
    }


    /**
     * @return array<string, mixed>
     */
    private function variationSettings(): array
    {
        $defaults = [
            'min_duration_days' => 14,
            'max_duration_days' => 210,
            'closed_ended_min_offset_days' => 3,
            'closed_ended_max_offset_days' => 60,
            'duration_mix' => [
                'short' => 20,
                'normal' => 60,
                'long' => 20,
            ],
            'regulation_type_weights' => [
                'vollzahlung' => 28,
                'teilzahlung' => 42,
                'ablehnung' => 20,
                'austehend' => 10,
            ],
            'regulation_ai_override_percent' => 20,
        ];

        $stored = Setting::getValue(self::AI_VARIATION_SETTING_TYPE, self::AI_VARIATION_SETTING_KEY);
        $stored = is_array($stored) ? $stored : [];
        $durationMix = is_array($stored['duration_mix'] ?? null) ? $stored['duration_mix'] : [];
        $regulationWeights = is_array($stored['regulation_type_weights'] ?? null) ? $stored['regulation_type_weights'] : [];

        $minDuration = $this->boundedInt($stored['min_duration_days'] ?? $defaults['min_duration_days'], 7, 90, $defaults['min_duration_days']);
        $maxDuration = $this->boundedInt($stored['max_duration_days'] ?? $defaults['max_duration_days'], $minDuration + 14, 365, $defaults['max_duration_days']);
        $endedMinOffset = $this->boundedInt($stored['closed_ended_min_offset_days'] ?? $defaults['closed_ended_min_offset_days'], 0, 30, $defaults['closed_ended_min_offset_days']);
        $endedMaxOffset = $this->boundedInt($stored['closed_ended_max_offset_days'] ?? $defaults['closed_ended_max_offset_days'], $endedMinOffset + 1, 120, $defaults['closed_ended_max_offset_days']);

        return [
            'min_duration_days' => $minDuration,
            'max_duration_days' => $maxDuration,
            'closed_ended_min_offset_days' => $endedMinOffset,
            'closed_ended_max_offset_days' => $endedMaxOffset,
            'duration_mix' => [
                'short' => $this->boundedInt($durationMix['short'] ?? $defaults['duration_mix']['short'], 0, 100, $defaults['duration_mix']['short']),
                'normal' => $this->boundedInt($durationMix['normal'] ?? $defaults['duration_mix']['normal'], 0, 100, $defaults['duration_mix']['normal']),
                'long' => $this->boundedInt($durationMix['long'] ?? $defaults['duration_mix']['long'], 0, 100, $defaults['duration_mix']['long']),
            ],
            'regulation_type_weights' => [
                'vollzahlung' => $this->boundedInt($regulationWeights['vollzahlung'] ?? $defaults['regulation_type_weights']['vollzahlung'], 0, 100, $defaults['regulation_type_weights']['vollzahlung']),
                'teilzahlung' => $this->boundedInt($regulationWeights['teilzahlung'] ?? $defaults['regulation_type_weights']['teilzahlung'], 0, 100, $defaults['regulation_type_weights']['teilzahlung']),
                'ablehnung' => $this->boundedInt($regulationWeights['ablehnung'] ?? $defaults['regulation_type_weights']['ablehnung'], 0, 100, $defaults['regulation_type_weights']['ablehnung']),
                'austehend' => $this->boundedInt($regulationWeights['austehend'] ?? $defaults['regulation_type_weights']['austehend'], 0, 100, $defaults['regulation_type_weights']['austehend']),
            ],
            'regulation_ai_override_percent' => $this->boundedInt($stored['regulation_ai_override_percent'] ?? $defaults['regulation_ai_override_percent'], 0, 100, $defaults['regulation_ai_override_percent']),
        ];
    }

    private function boundedInt(mixed $value, int $min, int $max, int $fallback): int
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        return max($min, min($max, (int) $value));
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function weightedRegulationType(array $settings): string
    {
        $weights = is_array($settings['regulation_type_weights'] ?? null) ? $settings['regulation_type_weights'] : [];
        $allowed = ['vollzahlung', 'teilzahlung', 'ablehnung', 'austehend'];
        $total = 0;

        foreach ($allowed as $type) {
            $total += max(0, (int) ($weights[$type] ?? 0));
        }

        if ($total <= 0) {
            return 'teilzahlung';
        }

        $pick = random_int(1, $total);
        $running = 0;

        foreach ($allowed as $type) {
            $running += max(0, (int) ($weights[$type] ?? 0));

            if ($pick <= $running) {
                return $type;
            }
        }

        return 'teilzahlung';
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function realisticDurationDays(int $averageDays, array $settings): int
    {
        $minDays = (int) ($settings['min_duration_days'] ?? 14);
        $maxDays = max($minDays + 7, (int) ($settings['max_duration_days'] ?? 210));
        $mix = is_array($settings['duration_mix'] ?? null) ? $settings['duration_mix'] : [];
        $bucket = $this->weightedDurationBucket($mix);
        $averageDays = max($minDays, $averageDays);

        [$minFactor, $maxFactor] = match ($bucket) {
            'short' => [0.75, 1.05],
            'long' => [1.45, 3.00],
            default => [0.95, 1.60],
        };

        $low = max($minDays, (int) floor($averageDays * $minFactor));
        $high = min($maxDays, max($low + 1, (int) ceil($averageDays * $maxFactor)));

        return random_int($low, $high);
    }

    /**
     * @param array<string, mixed> $mix
     */
    private function weightedDurationBucket(array $mix): string
    {
        $weights = [
            'short' => max(0, (int) ($mix['short'] ?? 20)),
            'normal' => max(0, (int) ($mix['normal'] ?? 60)),
            'long' => max(0, (int) ($mix['long'] ?? 20)),
        ];
        $total = array_sum($weights);

        if ($total <= 0) {
            return 'normal';
        }

        $pick = random_int(1, $total);
        $running = 0;

        foreach ($weights as $bucket => $weight) {
            $running += $weight;

            if ($pick <= $running) {
                return $bucket;
            }
        }

        return 'normal';
    }

    /**
     * @return array{selected_values: array<int, string>, textarea_value: string|null}
     */
    private function normalizeRegulationDetails(mixed $value): array
    {
        $value = is_array($value) ? $value : [];
        $selectedValues = $value['selected_values'] ?? [];

        if (! is_array($selectedValues) || $selectedValues === []) {
            $selectedValues = ['Standardisierte interne Testangabe'];
        }

        return [
            'selected_values' => array_values(array_map('strval', $selectedValues)),
            'textarea_value' => isset($value['textarea_value']) ? trim((string) $value['textarea_value']) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeContractDetails(mixed $value, string $regulationType): array
    {
        $value = is_array($value) ? $value : [];
        $claimAmount = max(100, (float) ($value['claim_amount'] ?? random_int(300, 5000)));
        $deductible = max(0, (float) ($value['contract_deductible_amount'] ?? random_int(0, 500)));
        $settlement = match ($regulationType) {
            'vollzahlung' => max(0, $claimAmount - $deductible),
            'ablehnung', 'austehend' => 0,
            default => max(0, min($claimAmount, (float) ($value['claim_settlement_amount'] ?? $claimAmount * (random_int(35, 85) / 100)))),
        };

        return [
            'contract_coverage_amount' => (float) ($value['contract_coverage_amount'] ?? max($claimAmount, random_int(5000, 100000))),
            'contract_deductible_amount' => $deductible,
            'claim_amount' => $claimAmount,
            'claim_settlement_amount' => $settlement,
            'textarea_value' => isset($value['textarea_value']) ? trim((string) $value['textarea_value']) : null,
        ];
    }

    private function normalizeQuestionAnswer(array $question, mixed $value): mixed
    {
        return match ((string) ($question['type'] ?? 'text')) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'number' => is_numeric($value) ? +$value : random_int(1, 10),
            'rating' => max(1, min(5, (int) $value)),
            'textarea', 'text' => trim((string) $value) ?: $this->fallbackQuestionAnswer($question),
            default => is_scalar($value) ? $value : $this->fallbackQuestionAnswer($question),
        };
    }

    private function fallbackQuestionAnswer(array $question): mixed
    {
        return match ((string) ($question['type'] ?? 'text')) {
            'boolean' => (bool) random_int(0, 1),
            'number' => random_int(1, 10),
            'rating' => random_int(2, 5),
            'textarea' => 'Interne synthetische Testantwort ohne Bezug zu einem realen Schadenfall.',
            default => 'Interner Testwert',
        };
    }
}
