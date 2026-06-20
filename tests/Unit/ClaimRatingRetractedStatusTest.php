<?php

namespace Tests\Unit;

use App\Models\ClaimRating;
use Tests\TestCase;

class ClaimRatingRetractedStatusTest extends TestCase
{
    public function test_retracted_status_has_its_own_label(): void
    {
        $rating = new ClaimRating([
            'status' => ClaimRating::STATUS_RETRACTED,
        ]);

        $this->assertSame('Zurueckgerufen', $rating->status_label);
        $this->assertSame('Zurueckgerufen', $rating->execution_state_label);
        $this->assertTrue($rating->isRetracted());
    }

    public function test_legacy_retraction_flag_is_still_recognized(): void
    {
        $rating = new ClaimRating([
            'status' => ClaimRating::STATUS_SCHEDULED,
            'data' => [
                'execution_control' => [
                    'manual_only_after_retract' => true,
                ],
            ],
        ]);

        $this->assertTrue($rating->isRetracted());
        $this->assertSame('Zurueckgerufen', $rating->execution_state_label);
    }

    public function test_unexecuted_rating_without_base_link_can_be_deleted_from_plan(): void
    {
        $rating = new ClaimRating([
            'status' => ClaimRating::STATUS_SCHEDULED,
            'executed_at' => null,
            'base_claim_rating_id' => null,
            'base_user_id' => null,
        ]);

        $this->assertTrue($rating->canBeDeletedFromPlan());
    }

    public function test_executed_processing_or_base_linked_rating_cannot_be_deleted_from_plan(): void
    {
        $executed = new ClaimRating([
            'status' => ClaimRating::STATUS_RATED,
            'executed_at' => now(),
        ]);
        $processing = new ClaimRating([
            'status' => ClaimRating::STATUS_PROCESSING,
        ]);
        $baseLinked = new ClaimRating([
            'status' => ClaimRating::STATUS_SCHEDULED,
            'base_claim_rating_id' => 123,
        ]);

        $this->assertFalse($executed->canBeDeletedFromPlan());
        $this->assertFalse($processing->canBeDeletedFromPlan());
        $this->assertFalse($baseLinked->canBeDeletedFromPlan());
    }
}
