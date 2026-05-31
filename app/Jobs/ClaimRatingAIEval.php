<?php

namespace App\Jobs;

use App\Http\Controllers\Customer\ClaimRating\AIEvalController;
use App\Models\ClaimRating;
use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ClaimRatingAIEval implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 180;

    public function __construct(
        public ClaimRating $claimRating,
        public bool $isAdminReanalysis = false,
        public bool $markExecuted = true,
    ) {
    }

    public function handle(): void
    {
        $this->claimRating->refresh();

        $answers = $this->claimRating->answers ?? [];
        $attachments = $this->claimRating->attachments ?? [];
        $data = $this->claimRating->data ?? [];

        $averageRatingSpeed = $this->resolveAverageRatingSpeed($attachments, $data);
        $actualDays = $this->resolveActualDays($answers, $data);
        $daysDifference = $actualDays !== null && $actualDays > $averageRatingSpeed
            ? $actualDays - $averageRatingSpeed
            : null;

        $attachments['eval_details']['insuranceSubtype_average_rating_speed'] = $averageRatingSpeed;
        $attachments['eval_details']['actualDays'] = $actualDays;
        $attachments['eval_details']['days_difference'] = $daysDifference;

        $variableQuestionScore = $this->evaluateQuestionSnapshot($answers, $attachments, $data);

        if ($variableQuestionScore !== null) {
            $attachments['scorings']['variable_questions'] = round($variableQuestionScore, 2);
        }

        $attachments['scorings']['regulation_speed'] = $this->calculateRegulationSpeedScore($actualDays, $averageRatingSpeed);

        $overallScore = AIEvalController::getOverAllScore($answers, $attachments);

        $attachments['scorings']['regulation_speed'] = $this->scoreValue($overallScore['regulation_speed'] ?? 0);
        $attachments['scorings']['customer_service'] = $this->scoreValue($overallScore['customer_service'] ?? 0);
        $attachments['scorings']['fairness'] = $this->scoreValue($overallScore['fairness'] ?? 0);
        $attachments['scorings']['transparency'] = $this->scoreValue($overallScore['transparency'] ?? 0);
        $attachments['scorings']['ai_overall_comment'] = (string) ($overallScore['aiResultComment'] ?? $overallScore['comment'] ?? '');

        $adminReview = $this->claimRating->admin_review ?? [];
        $adminReview['ai_evaluated_at'] = now()->toDateTimeString();
        $adminReview['ai_reanalysis'] = $this->isAdminReanalysis;
        $targetScoreProfile = $data['planning']['target_score_profile'] ?? null;

        $this->claimRating->attachments = $attachments;
        $this->claimRating->admin_review = $adminReview;
        $this->claimRating->tag_ids = $this->parseTags($overallScore['tags'] ?? []);
        $this->claimRating->rating_score = round($this->scoreValue($overallScore['overall_score'] ?? 0), 2);

        if (is_array($targetScoreProfile)) {
            $attachments['scorings']['target_score_profile'] = $targetScoreProfile;
            $attachments['scorings']['target_score_delta'] = isset($targetScoreProfile['target_score'])
                ? round($this->claimRating->rating_score - (float) $targetScoreProfile['target_score'], 2)
                : null;
            $this->claimRating->attachments = $attachments;
        }

        $this->claimRating->status = ClaimRating::STATUS_RATED;
        $this->claimRating->is_public = (bool) ($data['synthetic'] ?? false);

        if ($this->markExecuted && ($data['synthetic'] ?? false) === true && ! $this->claimRating->executed_at) {
            $this->claimRating->executed_at = now();
        }

        $this->claimRating->saveQuietly();

        Log::info('AI evaluation completed for local ClaimRating.', [
            'claim_rating_id' => $this->claimRating->id,
            'base_claim_rating_id' => $this->claimRating->base_claim_rating_id,
        ]);
    }

    private function resolveAverageRatingSpeed(array $attachments, array $data): int
    {
        $value = $attachments['eval_details']['insuranceSubtype_average_rating_speed']
            ?? $data['insuranceSubtype_average_rating_speed']
            ?? $data['insurance_subtype']['average_rating_speed']
            ?? $data['insuranceSubtype']['average_rating_speed']
            ?? 30;

        return max(1, (int) $value);
    }

    private function resolveActualDays(array $answers, array $data): ?int
    {
        $dateData = $answers['selectedDates']
            ?? $answers['selected_dates']
            ?? $data['selectedDates']
            ?? $data['selected_dates']
            ?? [];

        $startedAt = $dateData['started_at'] ?? $data['started_at'] ?? null;
        $endedAt = $dateData['ended_at'] ?? $data['ended_at'] ?? null;

        if (! $startedAt) {
            return null;
        }

        try {
            $start = new DateTimeImmutable((string) $startedAt);
            $end = $endedAt ? new DateTimeImmutable((string) $endedAt) : new DateTimeImmutable();

            return $start->diff($end)->days;
        } catch (\Throwable) {
            return null;
        }
    }

    private function calculateRegulationSpeedScore(?int $actualDays, int $averageRatingSpeed): float
    {
        if ($actualDays === null || $actualDays <= $averageRatingSpeed) {
            return 0.99;
        }

        return $this->scoreValue(0.99 - (($actualDays - $averageRatingSpeed) / $averageRatingSpeed));
    }

    private function evaluateQuestionSnapshot(array $answers, array &$attachments, array $data): ?float
    {
        $snapshot = $this->resolveQuestionSnapshot($attachments, $data);

        if ($snapshot === []) {
            return null;
        }

        $weightedScore = 0.0;
        $questionCount = 0;

        foreach ($snapshot as $question) {
            if (! is_array($question)) {
                continue;
            }

            $title = (string) ($question['title'] ?? '');

            if ($title === '' || ! array_key_exists($title, $answers)) {
                continue;
            }

            $calculatedScore = $this->calculateQuestionScore($question, $answers[$title]);

            if ($calculatedScore === null) {
                continue;
            }

            $weight = (float) ($question['pivot']['weight'] ?? $question['weight'] ?? 1);
            $attachments['scorings']['questions'][$title] = array_merge([
                'question_title' => $title,
                'question_text' => (string) ($question['question_text'] ?? ''),
                'question_weight' => $weight,
                'answer' => $answers[$title],
            ], $calculatedScore['meta']);

            $weightedScore += $calculatedScore['score'] * $weight;
            $questionCount++;
        }

        return $questionCount > 0 ? $weightedScore / $questionCount : null;
    }

    /**
     * @return array<int, mixed>
     */
    private function resolveQuestionSnapshot(array $attachments, array $data): array
    {
        $snapshot = $attachments['questionnaire_snapshot']
            ?? $attachments['questionnaireVersionSnapshot']
            ?? $data['questionnaire_snapshot']
            ?? $data['questionnaireVersionSnapshot']
            ?? $data['questionnaire_version']['snapshot']
            ?? $data['questionnaireVersion']['snapshot']
            ?? [];

        return is_array($snapshot) ? $snapshot : [];
    }

    /**
     * @return array{score: float, meta: array<string, mixed>}|null
     */
    private function calculateQuestionScore(array $question, mixed $value): ?array
    {
        return match ((string) ($question['type'] ?? '')) {
            'rating' => [
                'score' => $this->scoreValue(((float) $value) / 5),
                'meta' => [
                    'type' => 'calc',
                    'score' => $this->scoreValue(((float) $value) / 5),
                ],
            ],
            'textarea' => $this->calculateTextareaScore($question, $value),
            default => null,
        };
    }

    /**
     * @return array{score: float, meta: array<string, mixed>}|null
     */
    private function calculateTextareaScore(array $question, mixed $value): ?array
    {
        $answer = trim((string) $value);

        if (strlen($answer) <= 3) {
            return null;
        }

        $result = AIEvalController::getScoreForTextarea($question, $answer);
        $score = $this->scoreValue($result['score'] ?? 0);

        return [
            'score' => $score,
            'meta' => [
                'type' => 'ai',
                'ai_score' => $score,
                'ai_comment' => (string) ($result['comment'] ?? ''),
            ],
        ];
    }

    /**
     * @return array<int, int|string>
     */
    private function parseTags(mixed $tags): array
    {
        if (is_string($tags)) {
            $tags = array_filter(array_map('trim', explode(',', $tags)));
        }

        if (! is_array($tags)) {
            return [];
        }

        return array_slice(array_values(array_filter($tags, fn (mixed $tag): bool => $tag !== '' && $tag !== null)), 0, 3);
    }

    private function scoreValue(mixed $value): float
    {
        return max(0.0, min(0.99, (float) $value));
    }
}
