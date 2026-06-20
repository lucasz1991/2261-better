<?php

namespace Tests\Unit;

use App\Models\ClaimRating;
use PHPUnit\Framework\TestCase;

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
}
