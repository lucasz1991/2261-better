# RatingDistributionAnalyzer - Dokumentation

## 🎯 Zweck

Der `RatingDistributionAnalyzer` Service analysiert die **echten Bewertungen** aus der regulierungs-check Anwendung und berechnet automatisch die optimale Verteilung der Gewichte für Versicherungsarten und -unterarten.

## 🔧 Was wird analysiert?

Der Service ermittelt für jede Kombination aus **Art + Unterart**:

1. **Anzahl der Bewertungen** (Beliebtheit/Relevanz)
2. **Durchschnittliche Bewertungsqualität** (Rating-Score)
3. **Konsistenz** (Standardabweichung/Variabilität)
4. **Typische Uhrzeiten** (Verteilung nach `created_at`-Stunde)

### Gewichtsformel

```
Gewicht = (40% × Anzahl) + (40% × Qualität) + (20% × Konsistenz)
```

**Bedeutung:**
- 40% Anzahl: Unterarten mit mehr Bewertungen sollten häufiger generiert werden
- 40% Qualität: Unterarten mit besseren Bewertungen sollten häufiger generiert werden  
- 20% Konsistenz: Unterarten mit konsistenten Bewertungen sind zuverlässiger

## 📊 Workflow

### 1. Analyse starten
```php
$analyzer = new RatingDistributionAnalyzer();
$analysis = $analyzer->analyzeRealRatings();
```

**Rückgabe:**
```php
[
    'type_weights' => [
        1 => 45.5,      // Art 1: Summe aller Unterart-Gewichte
        2 => 62.3,      // Art 2
    ],
    'subtype_weights' => [
        1 => [
            10 => 15.2,  // Art 1, Unterart 10
            11 => 30.3,  // Art 1, Unterart 11
        ],
        2 => [
            20 => 28.5,
            21 => 33.8,
        ],
    ],
    'hour_weights' => [
        9 => 55.4,
        18 => 100.0,
    ],
    'hour_stats' => [
        18 => ['count' => 120, 'percent' => 14.2],
    ],
    'stats' => [...],   // Detaillierte Statistiken pro Type/Subtype
    'timestamp' => Carbon,
    'total_ratings_analyzed' => 1523,
]
```

Zusätzlich wird die Stundenverteilung aller passenden Bewertungen gespeichert:

```php
[
    9 => ['count' => 84, 'percent' => 9.8],
    18 => ['count' => 120, 'percent' => 14.2],
]
```

### 2. In Settings speichern
```php
$analyzer->saveAnalysisToSettings($analysis);
$analyzer->applyAnalysisToGenerationSettings($analysis);
```

Dies speichert die Ergebnisse in der `settings` Tabelle unter:
- `type = 'rating_generation'`
- `key = 'analysis'`

Die aktive Verteilung wird unter `type = 'rating_generation'`, `key = 'settings'` gesetzt.

### 3. Im Admin-Panel anwenden
- Gehe zu `/admin/config` → Tab "Bewertungs-Einstellungen"
- Klick "Analyse starten und setzen"
- Service analysiert die echten Bewertungen
- Die berechneten Gewichte werden direkt als aktive Settings gespeichert

## 🔐 Datenbankverbindung

Der Service greift auf die **regulierungs-check-base Datenbank** zu.

**Konfiguration in `.env`:**
```env
# Haupt-App DB
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=2261-better

# Analytics/Source DB (regulierungs-check-base)
ANALYTICS_DB_CONNECTION=mysql_analytics
ANALYTICS_DB_HOST=127.0.0.1
ANALYTICS_DB_PORT=3306
ANALYTICS_DB_DATABASE=regulierungs-check
ANALYTICS_DB_USERNAME=root
ANALYTICS_DB_PASSWORD=
```

**in `config/database.php` konfigurieren:**
```php
'mysql_analytics' => [
    'driver' => 'mysql',
    'host' => env('ANALYTICS_DB_HOST', '127.0.0.1'),
    'port' => env('ANALYTICS_DB_PORT', 3306),
    'database' => env('ANALYTICS_DB_DATABASE', 'regulierungs-check'),
    'username' => env('ANALYTICS_DB_USERNAME', 'root'),
    'password' => env('ANALYTICS_DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
],
```

Oder nutze `2261-better`'s Datenbankeinstellungen - dort kannst du auch die Verbindung zur regulierungs-check-base DB konfigurieren.

## 📈 Statistiken der Analyseergebnisse

Jede Unterart wird mit folgenden Metriken analysiert:

```php
[
    'count' => 125,           // Anzahl Bewertungen
    'avg_score' => 4.2,       // Durchschnitt (0-5)
    'min_score' => 2.1,       // Minimum
    'max_score' => 5.0,       // Maximum
    'std_dev' => 0.87,        // Standardabweichung (Konsistenz)
]
```

## 🚀 Integration im Code

### Im AdminConfig Component
```php
use App\Services\RatingDistributionAnalyzer;

public function runAnalysis(): void
{
    $analyzer = new RatingDistributionAnalyzer();
    $analysis = $analyzer->analyzeRealRatings();
    $analyzer->saveAnalysisToSettings($analysis);
}

public function applyAnalysisResults(): void
{
    // Lädt die Ergebnisse und wendet sie auf die UI an
}
```

### Direkt im Code
```php
$analyzer = new RatingDistributionAnalyzer();

// Starte Analyse
$analysis = $analyzer->analyzeRealRatings();
$analyzer->saveAnalysisToSettings($analysis);

// Hole letzte Analyse
$lastAnalysis = $analyzer->getLastAnalysis();

// Detaillierte Stats für einen Typ
$stats = $analyzer->getDetailedStats(1); // Type ID 1
```

## 🔍 Beispielausgabe

**Input: 10 Bewertungen**
```php
// ClaimRating Tabelle:
- insurance_type_id: 1
- insurance_subtype_id: 10
- rating_score: 4.2
- status: 'published'
- is_public: true

- insurance_type_id: 1
- insurance_subtype_id: 10
- rating_score: 3.8
- status: 'published'
- is_public: true

// ... 8 weitere Bewertungen
```

**Ausgabe nach Analyse:**
```php
'subtype_weights' => [
    1 => [  // Art 1
        10 => 42.5,  // Unterart 10 (häufig bewertet, gute Qualität)
        11 => 18.3,  // Unterart 11 (weniger bewertet)
    ]
]
```

## ⚙️ Konfigurierbare Parameter

Der Service kann erweitert werden um folgende Parameter anzupassen:

```php
class RatingDistributionAnalyzer
{
    // Gewichtsverteilung (aktuell 40-40-20)
    private const WEIGHT_COUNT = 0.40;
    private const WEIGHT_SCORE = 0.40;
    private const WEIGHT_CONSISTENCY = 0.20;

    // Bewertungen filtern nach Status
    private const ALLOWED_STATUSES = [
        'rated',
        'approved', 
        'published',
    ];

    private const PUBLISHED_ONLY = true; // is_public = true
}
```

## 🐛 Fehlerbehandlung

**Keine Bewertungen gefunden:**
```
Warning: No published ratings found for analysis
Returns: [] (leere Gewichte)
```

**Datenbankverbindungsfehler:**
```
Error: Failed to connect to analytics database
Exception wird geloggt in storage/logs/laravel.log
```

## 📝 Logs

Alle Vorgänge werden geloggt:

```bash
# Im Terminal anschauen
tail -f storage/logs/laravel.log | grep -i "RatingDistributionAnalyzer"
```

**Beispiel Logs:**
```
[2026-05-29 14:30:00] local.INFO: Starting RatingDistributionAnalyzer
[2026-05-29 14:30:02] local.INFO: RatingDistributionAnalyzer completed
  {"total_ratings_analyzed":1523,"timestamp":"2026-05-29T14:30:02Z"}
[2026-05-29 14:30:03] local.INFO: Saved rating analysis to settings
  {"types_counted":5,"subtypes_counted":23}
```

## 🔄 Häufiger Ablauf

1. **Manuelle Analyse triggern** (Admin-Panel)
   - Admin klickt "Analyse starten"
   - Service analysiert aktuelle Bewertungen
   - Ergebnisse werden in Settings gespeichert
   - Admin klickt "Anwenden"
   - Neue Gewichte werden in die Felder geladen
   - Admin klickt "Speichern"

2. **Automatisierte Analyse** (Job/Scheduler - Optional)
   ```php
   // In: app/Console/Kernel.php
   protected function schedule(Schedule $schedule)
   {
       $schedule->call(function() {
           $analyzer = new RatingDistributionAnalyzer();
           $analysis = $analyzer->analyzeRealRatings();
           $analyzer->saveAnalysisToSettings($analysis);
       })->daily()->at('02:00');
   }
   ```

## 📊 Best Practices

### ✅ Empfohlen
- Analysiere regelmäßig (z.B. täglich nachts)
- Überprüfe die Ergebnisse vor dem Anwenden
- Aktualisiere die Gewichte mindestens monatlich
- Archiviere alte Analyseergebnisse

### ❌ Nicht empfohlen
- Ignoriere Warnungen im Analyse-Log
- Wende Ergebnisse blind an ohne zu überprüfen
- Analysiere zu häufig (kostet Performance)

## 🔗 Verwandte Dateien

- [AdminConfig.php](../app/Livewire/AdminConfig.php) - Integration
- [admin-config.blade.php](../resources/views/livewire/admin-config.blade.php) - UI
- [Setting Model](../app/Models/Setting.php) - Persistierung
- [SETTINGS_SYSTEM.md](./SETTINGS_SYSTEM.md) - Settings-Übersicht

## 💡 Tipps

### Spezifische Unterarten ignorieren
Falls eine Unterart schlechte Qualität hat, kannst du sie vor dem Speichern manuell anpassen:
1. Analysiere
2. Ergebnisse erscheinen
3. Ändere manuell die Gewichte für bestimmte Unterarten
4. Speichern

### Gewichte manuell anpassen
Die berechneten Gewichte sind nicht in Stein gemeißelt:
- Nutze die Analyse als Basis
- Passe manuell an, falls nötig
- Z.B. für Marketing, Testabdeckung, etc.

## 📞 Support

Bei Fragen oder Fehlern:
1. Prüfe die Logs: `storage/logs/laravel.log`
2. Überprüfe die Datenbankverbindung zur regulierungs-check-base
3. Stelle sicher, dass beide DBs erreichbar sind

---

**Zuletzt aktualisiert**: 2026-05-29
