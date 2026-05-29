<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AiEvaluationController extends Controller
{
    private AiConnection $aiService;

    public function __construct(AiConnection $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Bewerte Versicherungsdetails mit AI
     *
     * Request:
     * {
     *   "trainContent": "System-Anweisungen...",
     *   "data": [...],
     *   "possibleTags": "tag1, tag2, tag3"
     * }
     */
    public function evaluateInsuranceDetails(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'trainContent' => 'required|string',
                'data' => 'required|array',
                'possibleTags' => 'required|string',
            ]);

            $result = $this->aiService->generateInsuranceDetailEvaluation($validated);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('evaluateInsuranceDetails error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bewerte eine einzelne Text-Frage
     *
     * Request:
     * {
     *   "questionTitle": "Frage-Titel",
     *   "questionText": "Frage-Text",
     *   "customerAnswer": "Antwort des Kunden",
     *   "trainContent": "System-Anweisungen..."
     * }
     */
    public function evaluateTextQuestion(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'questionTitle' => 'required|string',
                'questionText' => 'required|string',
                'customerAnswer' => 'required|string',
                'trainContent' => 'required|string',
            ]);

            $result = $this->aiService->getAnswerSingleTextQuestion($validated);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('evaluateTextQuestion error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Berechne den Gesamtscore
     *
     * Request:
     * {
     *   "answers": [...],
     *   "attachments": [...],
     *   "trainContent": "System-Anweisungen...",
     *   "possibleTags": "tag1, tag2, tag3"
     * }
     */
    public function calculateOverallScore(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'answers' => 'required|array',
                'attachments' => 'required|array',
                'trainContent' => 'required|string',
                'possibleTags' => 'required|string',
            ]);

            $result = $this->aiService->getOverAllScore($validated);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('calculateOverallScore error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
