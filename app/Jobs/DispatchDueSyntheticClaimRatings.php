<?php

namespace App\Jobs;

use App\Models\ClaimRating;
use App\Models\Setting;
use App\Services\BaseClaimRatingPublisher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchDueSyntheticClaimRatings implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(public int $limit = 25)
    {
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
            'limit' => max(1, $this->limit),
            'due_count' => 0,
            'dispatched_count' => 0,
            'executed_count' => 0,
            'failed_count' => 0,
            'dispatched' => [],
        ];

        if ($disabledReason) {
            $report['reason'] = $disabledReason;

            return $report;
        }

        $query = ClaimRating::query()
            ->where('data->synthetic', true)
            ->whereNull('executed_at')
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())
            ->whereNotIn('status', [
                ClaimRating::STATUS_FAILED,
                ClaimRating::STATUS_PROCESSING,
            ]);

        $report['due_count'] = (clone $query)->count();

        $ratings = $query
            ->orderBy('scheduled_for')
            ->limit($report['limit'])
            ->get();

        foreach ($ratings as $rating) {
            try {
                if ($this->hasPreparedAiPayload($rating)) {
                    $this->markPreparedRatingExecuted($rating);

                    $report['executed_count']++;
                    $report['dispatched'][] = $this->ratingReportItem($rating->fresh(), 'prepared_marked_executed');

                    continue;
                }

                GenerateSyntheticClaimRating::dispatchSync($rating->fresh());

                $report['executed_count']++;
                $report['dispatched'][] = $this->ratingReportItem($rating->fresh(), 'generated_and_executed');
            } catch (\Throwable $exception) {
                $report['failed_count']++;
                $report['dispatched'][] = $this->ratingReportItem($rating->fresh(), 'generation_failed', $exception->getMessage());

                Log::error('Due synthetic claim rating execution failed.', [
                    'claim_rating_id' => $rating->id,
                    'message' => $exception->getMessage(),
                    'exception' => $exception,
                ]);
            }
        }

        $report['dispatched_count'] = count($report['dispatched']);

        if ($ratings->isNotEmpty()) {
            Log::info('Dispatched due synthetic claim ratings.', [
                'count' => $ratings->count(),
            ]);
        }

        $report['ok'] = true;
        $report['reason'] = match (true) {
            $report['failed_count'] > 0 => 'Faellige Bewertungen wurden mit Fehlern verarbeitet.',
            $report['dispatched_count'] > 0 => 'Faellige Bewertungen wurden verarbeitet.',
            default => 'Keine faelligen geplanten Bewertungen gefunden.',
        };

        return $report;
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

    private function hasPreparedAiPayload(ClaimRating $rating): bool
    {
        return filled(data_get($rating->data, 'ai_generation.generated_at'))
            && is_array($rating->answers)
            && $rating->answers !== [];
    }

    private function markPreparedRatingExecuted(ClaimRating $rating): void
    {
        $executedAt = now();
        $data = $rating->data ?? [];
        $data['ai_generation']['executed_from_prepared_at'] = $executedAt->toDateTimeString();

        $rating->forceFill([
            'status' => ClaimRating::STATUS_RATED,
            'execution_started_at' => $executedAt,
            'executed_at' => $executedAt,
            'last_execution_error' => null,
            'is_public' => false,
            'data' => $data,
        ])->saveQuietly();

        app(BaseClaimRatingPublisher::class)->publish($rating->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    private function ratingReportItem(ClaimRating $rating, string $action, ?string $error = null): array
    {
        return [
            'id' => $rating->id,
            'action' => $action,
            'error' => $error,
            'scheduled_for' => optional($rating->scheduled_for)->format('Y-m-d H:i:s'),
            'type_id' => $rating->insurance_type_id,
            'subtype_id' => $rating->insurance_subtype_id,
            'insurance_id' => $rating->insurance_id,
            'base_claim_rating_id' => $rating->base_claim_rating_id,
            'base_user_id' => $rating->base_user_id,
        ];
    }
}
