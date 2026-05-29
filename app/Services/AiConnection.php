<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiConnection
{
    private string $apiUrl = 'https://openrouter.ai/api/v1/chat/completions';
    private string $apiKey;
    private string $aiModel;
    private string $refererUrl;
    private int $maxRetries = 3;
    private int $timeout = 120;

    public function __construct()
    {
        // Load OpenRouter settings from database
        $settings = Setting::getValue('openrouter', 'config') ?? [];
        $settings = is_array($settings) ? $settings : [];
        
        $this->apiUrl = $settings['api_url'] ?? env('OPENROUTER_API_URL', $this->apiUrl);
        $this->apiKey = $settings['api_key'] ?? env('OPENROUTER_API_KEY');
        $this->aiModel = $settings['model'] ?? env('OPENROUTER_MODEL', 'openrouter/auto');
        $this->refererUrl = $settings['referer_url'] ?? env('APP_URL', 'http://localhost');

        if (!$this->apiKey) {
            throw new \Exception('OpenRouter API Key ist nicht konfiguriert. Bitte in Einstellungen setzen.');
        }
    }

    /**
     * Generiere Versicherungsdetail-Bewertung mit AI
     */
    public function generateInsuranceDetailEvaluation(array $requestData): array
    {
        Log::info('generateInsuranceDetailEvaluation Run');

        $trainContent = $requestData['trainContent'];
        $reviews = json_encode($requestData['data']);
        $possibleTags = $requestData['possibleTags'];

        $defaultResponse = [
            'average_fairness' => null,
            'average_regulation_speed' => null,
            'average_customer_service' => null,
            'average_transparency' => null,
            'tags' => '',
            'comment' => '',
        ];

        $userPrompt = <<<TEXT
            possibleTags: {$possibleTags}
            reviews: {$reviews}
            TEXT;

        try {
            $response = $this->callOpenRouter($trainContent, $userPrompt);

            if (!isset($response['average_fairness'], $response['average_regulation_speed'], 
                      $response['average_customer_service'], $response['average_transparency'], 
                      $response['comment'], $response['tags'])) {
                throw new \Exception("Missing required keys in AI response");
            }

            return [
                'average_fairness' => floatval($response['average_fairness']),
                'average_regulation_speed' => floatval($response['average_regulation_speed']),
                'average_customer_service' => floatval($response['average_customer_service']),
                'average_transparency' => floatval($response['average_transparency']),
                'tags' => $response['tags'],
                'comment' => $this->cleanText($response['comment']),
            ];
        } catch (\Exception $e) {
            Log::error('generateInsuranceDetailEvaluation failed: ' . $e->getMessage());
            return $defaultResponse;
        }
    }

    /**
     * Bewerte eine einzelne Text-Frage
     */
    public function getAnswerSingleTextQuestion(array $requestData): array
    {
        Log::info('getAnswerSingleTextQuestion Run');

        $questionTitle = $requestData['questionTitle'];
        $questionText = $requestData['questionText'];
        $customerAnswer = $requestData['customerAnswer'];
        $trainContent = $requestData['trainContent'];

        $userPrompt = <<<TEXT
            Fragetitel: {$questionTitle}
            Fragetext: {$questionText}
            Antwort: {$customerAnswer}
            TEXT;

        try {
            $response = $this->callOpenRouter($trainContent, $userPrompt);

            if (!isset($response['score'], $response['comment'])) {
                throw new \Exception("Missing required keys (score, comment) in AI response");
            }

            return [
                'score' => floatval($response['score']),
                'comment' => $this->cleanText($response['comment']),
            ];
        } catch (\Exception $e) {
            Log::error('getAnswerSingleTextQuestion failed: ' . $e->getMessage());
            return ['score' => 0, 'comment' => ''];
        }
    }

    /**
     * Berechne den Gesamtscore
     */
    public function getOverAllScore(array $requestData): array
    {
        Log::info('getOverAllScore Run');

        $answers = json_encode($requestData['answers']);
        $attachments = json_encode($requestData['attachments']);
        $trainContent = $requestData['trainContent'];
        $possibleTags = $requestData['possibleTags'];

        $userPrompt = <<<TEXT
            possibleTags: {$possibleTags}
            attachments: {$attachments}
            answers: {$answers}
            TEXT;

        try {
            $response = $this->callOpenRouter($trainContent, $userPrompt);

            $requiredKeys = ['overall_score', 'comment', 'regulation_speed', 'customer_service', 'fairness', 'transparency', 'tags'];
            foreach ($requiredKeys as $key) {
                if (!isset($response[$key])) {
                    throw new \Exception("Missing required key: {$key}");
                }
            }

            return [
                'overall_score' => floatval($response['overall_score']),
                'regulation_speed' => floatval($response['regulation_speed']),
                'customer_service' => floatval($response['customer_service']),
                'fairness' => floatval($response['fairness']),
                'transparency' => floatval($response['transparency']),
                'tags' => $response['tags'],
                'aiResultComment' => $this->cleanText($response['comment']),
            ];
        } catch (\Exception $e) {
            Log::error('getOverAllScore failed: ' . $e->getMessage());
            return [
                'overall_score' => 0,
                'regulation_speed' => 0,
                'customer_service' => 0,
                'fairness' => 0,
                'transparency' => 0,
                'tags' => '',
                'aiResultComment' => '',
            ];
        }
    }

    /**
     * Rufe die OpenRouter API auf mit Retry-Logik
     */
    private function callOpenRouter(string $systemContent, string $userContent): array
    {
        for ($attempt = 0; $attempt < $this->maxRetries; $attempt++) {
            try {
                $response = Http::timeout($this->timeout)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $this->apiKey,
                        'HTTP-Referer' => $this->refererUrl,
                        'Content-Type' => 'application/json',
                    ])
                    ->post($this->apiUrl, [
                        'model' => $this->aiModel,
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => trim(preg_replace('/\s+/', ' ', $systemContent)),
                            ],
                            [
                                'role' => 'user',
                                'content' => $userContent,
                            ]
                        ],
                    ]);

                Log::info('OpenRouter Response: ' . $response->status());

                if ($response->failed()) {
                    throw new \Exception("HTTP Error: " . $response->status() . " - " . $response->body());
                }

                $botMessage = $response->json()['choices'][0]['message']['content'] ?? '';

                if (!$botMessage) {
                    throw new \Exception("No content in AI response");
                }

                Log::info("Raw AI Message: " . substr($botMessage, 0, 200) . "...");

                return $this->parseJsonResponse($botMessage);

            } catch (\Exception $e) {
                Log::error("Attempt {$attempt} failed: " . $e->getMessage(), [
                    'exception' => $e,
                    'attempt' => $attempt + 1,
                ]);

                if ($attempt === $this->maxRetries - 1) {
                    throw $e;
                }
            }
        }

        throw new \Exception("Max retries exceeded");
    }

    /**
     * Parse JSON Response mit Fehlerbehandlung
     */
    private function parseJsonResponse(string $response): array
    {
        // Entferne Markdown Code Blöcke
        $cleaned = preg_replace('/^```(json)?|```$/m', '', trim($response));

        // Versuche zu decodieren
        $decoded = json_decode($cleaned, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // Fallback: Versuche escaped JSON zu parsen
        $decoded = $this->parsePossiblyEscapedJson($cleaned);

        if (is_array($decoded)) {
            return $decoded;
        }

        throw new \Exception("Failed to parse JSON response: " . json_last_error_msg());
    }

    /**
     * Parse möglicherweise escaped JSON
     */
    private function parsePossiblyEscapedJson(string $raw): mixed
    {
        // Entferne führende und abschließende Anführungszeichen
        $clean = trim($raw, "\"");

        // Entferne Escape-Zeichen
        $clean = stripslashes($clean);

        // Versuche zu decodieren
        $json = json_decode($clean, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('JSON parsing failed', [
                'input' => substr($raw, 0, 200),
                'cleaned' => substr($clean, 0, 200),
                'error' => json_last_error_msg(),
            ]);
            return null;
        }

        return $json;
    }

    /**
     * Bereinige Text von bestimmten Zeichen
     */
    private function cleanText(string $text): string
    {
        // Entferne asiatische Zeichen
        return preg_replace('/[\p{Han}\p{Hiragana}\p{Katakana}\p{Thai}]/u', '', $text);
    }
}
