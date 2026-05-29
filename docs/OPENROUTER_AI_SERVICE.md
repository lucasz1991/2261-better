# OpenRouter AI Service - Dokumentation

## Installation & Setup

### 1. Environment-Variablen konfigurieren

Füge folgende Variablen zu deiner `.env`-Datei hinzu:

```env
OPENROUTER_API_KEY=your_api_key_here
OPENROUTER_MODEL=your_model_here
# Beispiele für OpenRouter Models:
# - openrouter/auto (automatische Modellauswahl)
# - anthropic/claude-3-opus
# - openai/gpt-4
# - meta-llama/llama-2-70b-chat
```

Besuche [https://openrouter.ai](https://openrouter.ai) um einen API Key zu erhalten und verfügbare Modelle zu sehen.

### 2. Service laden

Der `AiConnection` Service wird automatisch via Dependency Injection geladen:

```php
use App\Services\AiConnection;

public function __construct(AiConnection $aiService)
{
    $this->aiService = $aiService;
}
```

## Verwendung

### Methode 1: Versicherungsdetails bewerten

```php
$result = $this->aiService->generateInsuranceDetailEvaluation([
    'trainContent' => 'System-Anweisungen für die AI...',
    'data' => [...],  // Array mit Bewertungsdaten
    'possibleTags' => 'tag1, tag2, tag3',
]);

// Returns:
// [
//     'average_fairness' => 4.5,
//     'average_regulation_speed' => 3.8,
//     'average_customer_service' => 4.2,
//     'average_transparency' => 4.0,
//     'tags' => 'tag1, tag2',
//     'comment' => 'Bewertungskommentar...',
// ]
```

### Methode 2: Einzelne Text-Frage bewerten

```php
$result = $this->aiService->getAnswerSingleTextQuestion([
    'questionTitle' => 'Ist der Kundenservice gut?',
    'questionText' => 'Bitte bewerten Sie den Kundenservice...',
    'customerAnswer' => 'Der Kundenservice war ausgezeichnet!',
    'trainContent' => 'System-Anweisungen für die AI...',
]);

// Returns:
// [
//     'score' => 4.8,
//     'comment' => 'Positive Bewertung erkannt...',
// ]
```

### Methode 3: Gesamtscore berechnen

```php
$result = $this->aiService->getOverAllScore([
    'answers' => [...],  // Array mit Antworten
    'attachments' => [...],  // Array mit Anhängen/Dokumenten
    'trainContent' => 'System-Anweisungen für die AI...',
    'possibleTags' => 'tag1, tag2, tag3',
]);

// Returns:
// [
//     'overall_score' => 4.3,
//     'regulation_speed' => 4.0,
//     'customer_service' => 4.5,
//     'fairness' => 4.2,
//     'transparency' => 4.1,
//     'tags' => 'tag1, tag2',
//     'aiResultComment' => 'Gesamtbewertung...',
// ]
```

## API Routes

Der `AiEvaluationController` stellt folgende Endpoints bereit:

```php
// routes/api.php
Route::post('/ai/evaluate-insurance', 'AiEvaluationController@evaluateInsuranceDetails');
Route::post('/ai/evaluate-question', 'AiEvaluationController@evaluateTextQuestion');
Route::post('/ai/overall-score', 'AiEvaluationController@calculateOverallScore');
```

### Beispiel API Call:

```bash
curl -X POST http://localhost:8000/api/ai/evaluate-question \
  -H "Content-Type: application/json" \
  -d '{
    "questionTitle": "Kundenservice Frage",
    "questionText": "Wie ist der Kundenservice?",
    "customerAnswer": "Sehr gut und hilfreich",
    "trainContent": "Bewerte die Antwort..."
  }'
```

## Fehlerbehandlung

Der Service implementiert automatische Retry-Logik (max. 3 Versuche) und gibt bei Fehlern default-Werte zurück:

```php
// Bei Fehler in getAnswerSingleTextQuestion
return ['score' => 0, 'comment' => ''];

// Bei Fehler in generateInsuranceDetailEvaluation
return [
    'average_fairness' => null,
    'average_regulation_speed' => null,
    'average_customer_service' => null,
    'average_transparency' => null,
    'tags' => '',
    'comment' => '',
];
```

Alle Fehler werden in den Laravel Logs dokumentiert für Debugging.

## Konfiguration

Die Konfiguration befindet sich in `config/services.php`:

```php
'openrouter' => [
    'api_key' => env('OPENROUTER_API_KEY'),
    'model' => env('OPENROUTER_MODEL', 'openrouter/auto'),
    'referer_url' => env('APP_URL', 'http://localhost'),
],
```

## Performance-Tipps

1. **Timeout**: Der Service nutzt einen 120-Sekunden Timeout pro Request
2. **Retry-Logik**: Automatisch 3 Versuche bei Fehlern
3. **Logging**: Ein- und Ausgaben werden geloggt (siehe `storage/logs/laravel.log`)

## Support

- OpenRouter Docs: https://openrouter.ai/docs
- API Status: https://status.openrouter.ai
