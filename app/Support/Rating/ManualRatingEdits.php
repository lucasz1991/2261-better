<?php

namespace App\Support\Rating;

use App\Models\ClaimRating;

final class ManualRatingEdits
{
    public static function protects(ClaimRating $rating, string $field): bool
    {
        $fields = data_get($rating->data, 'manual_edit.protected_fields', []);

        return is_array($fields) && in_array($field, $fields, true);
    }

    public static function answers(array $generated, ClaimRating $current): array
    {
        if (self::protects($current, 'regulation_comment')) {
            $generated['regulationComment'] = data_get($current->answers, 'regulationComment');
        }

        return $generated;
    }

    public static function apply(ClaimRating $generated, ClaimRating $current): void
    {
        $generated->answers = self::answers($generated->answers ?? [], $current);
        if (self::protects($current, 'summary')) {
            $attachments = $generated->attachments ?? [];
            data_set($attachments, 'scorings.ai_overall_comment', data_get($current->attachments, 'scorings.ai_overall_comment', ''));
            $generated->attachments = $attachments;
        }
        if (self::protects($current, 'rating_score')) {
            $generated->rating_score = $current->rating_score;
            $attachments = $generated->attachments ?? [];
            $target = data_get($current->data, 'planning.target_score_profile.target_score');
            if (is_numeric($target)) {
                data_set($attachments, 'scorings.target_score_delta', round((float) $current->rating_score - (float) $target, 2));
            }
            $generated->attachments = $attachments;
        }
    }
}
