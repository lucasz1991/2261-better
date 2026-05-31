<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Jobs\ClaimRatingAIEval;
use App\Models\ClaimRating;
use Illuminate\Http\JsonResponse;

class ClaimRatingController extends Controller
{
    public static function evaluateScore(ClaimRating $claimRating): JsonResponse
    {
        ClaimRatingAIEval::dispatch($claimRating);

        return response()->json([
            'success' => true,
        ]);
    }
}
