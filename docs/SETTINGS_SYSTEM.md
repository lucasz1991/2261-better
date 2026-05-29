# OpenRouter & Einstellungssystem - 2261-Better

## 🎯 Überblick

Das Einstellungssystem ermöglicht die zentrale Verwaltung aller Anwendungskonfigurationen über eine intuitive UI mit Tabs:

- **Bewertungs-Einstellungen**: Rating-Verteilung und tägliche Ziele
- **OpenRouter AI**: KI-API Konfiguration
- **Datenbankeinstellungen**: Datenbankverbindung (optional)

## 📚 Architecture

### Datenbank-Struktur
```
settings
├── id
├── type (z.B. 'rating_generation', 'openrouter', 'database')
├── key (z.B. 'settings', 'config')
├── value (JSON Array)
└── timestamps
```

### Beispiel Daten

**OpenRouter Settings:**
```php
[
    'type' => 'openrouter',
    'key' => 'config',
    'value' => [
        'api_key' => 'sk-or-v1-...',
        'model' => 'openrouter/auto',
        'referer_url' => 'http://127.0.0.1:9000'
    ]
]
```

**Datenbankeinstellungen:**
```php
[
    'type' => 'database',
    'key' => 'config',
    'value' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => '2261-better',
        'username' => 'root',
        'password' => ''
    ]
]
```

## 🚀 Setup

### 1. Admin-Panel öffnen
- Gehe zu: `/admin/config`
- Oder über das Admin-Menü → Einstellungen

### 2. OpenRouter konfigurieren

**Tab: "OpenRouter AI"**

1. Besuche [openrouter.ai](https://openrouter.ai) und registriere dich
2. Erstelle einen API Key
3. Gib ein:
   - **API Key**: Dein OpenRouter API Key (sk-or-...)
   - **Modell**: z.B. `openrouter/auto` oder `anthropic/claude-3-opus`
   - **Referer URL**: Deine App-URL (wird automatisch gefüllt)
4. Klick "Speichern"

### 3. Datenbank konfigurieren (Optional)
**Tab: "Datenbankeinstellungen"**

Die Werte werden von der `.env` Datei geladen. Du kannst sie hier überschreiben:
- Host, Port, Database, Username, Password

**⚠️ Vorsicht**: Ändere diese nur wenn nötig und mit Backups!

## 💻 Verwendung im Code

### Service laden
```php
use App\Services\AiConnection;

class MyController extends Controller
{
    public function __construct(private AiConnection $aiService)
    {
    }

    public function evaluate()
    {
        // Settings werden automatisch von der DB geladen
        $result = $this->aiService->getAnswerSingleTextQuestion([
            'questionTitle' => 'Frage',
            'questionText' => 'Kontext',
            'customerAnswer' => 'Antwort',
            'trainContent' => 'System-Prompt',
        ]);

        return response()->json($result);
    }
}
```

### Settings direkt abrufen
```php
use App\Models\Setting;

// Abrufen (mit Cache)
$openrouterSettings = Setting::getValue('openrouter', 'config');
$apiKey = $openrouterSettings['api_key'] ?? null;

// Ohne Cache
$fresh = Setting::getValueUncached('openrouter', 'config');

// Speichern
Setting::setValue('openrouter', 'config', [
    'api_key' => 'new_key',
    'model' => 'new_model',
    'referer_url' => 'http://example.com',
]);
```

## 🔧 Konfiguration

### AdminConfig Component
**Datei**: [app/Livewire/AdminConfig.php](../app/Livewire/AdminConfig.php)

Eigenschaften für OpenRouter:
```php
public string $openrouterApiKey = '';
public string $openrouterModel = 'openrouter/auto';
public string $openrouterRefererUrl = '';
```

Eigenschaften für Datenbank:
```php
public string $dbHost = '';
public string $dbPort = '';
public string $dbDatabase = '';
public string $dbUsername = '';
public string $dbPassword = '';
```

### Service Configuration
**Datei**: [config/services.php](../config/services.php)

Fallback-Konfiguration (wird nur genutzt wenn DB nicht verfügbar):
```php
'openrouter' => [
    'api_key' => env('OPENROUTER_API_KEY'),
    'model' => env('OPENROUTER_MODEL', 'openrouter/auto'),
    'referer_url' => env('APP_URL', 'http://localhost'),
],
```

## 🔐 Sicherheit

### Cache
- Settings werden mit 1-Stunden-Cache gecacht
- Bei Änderungen wird der Cache automatisch invalidiert
- `getValue()` nutzt Cache (schneller)
- `getValueUncached()` lädt immer frisch

### Sensible Daten
- API Keys sollten NIE in der `.env` oder öffentlich gespeichert werden
- Datenbankpasswörter sind sensibel!
- Nutze starke Passwörter

### Berechtigungen
- Nur Benutzer mit `settings.manage` Permission können die Einstellungen ändern
- Definiert in `AdminConfig@mount()` via `Gate::authorize('settings.manage')`

## 🐛 Debugging

### Logs
Alle Fehler werden in `storage/logs/laravel.log` geloggt:
```bash
tail -f storage/logs/laravel.log | grep -E "(AiConnection|OpenRouter)"
```

### Settings in DB prüfen
```bash
php artisan tinker

# Alle Settings anzeigen
>>> Setting::all()

# OpenRouter Settings
>>> Setting::getValue('openrouter', 'config')

# Oder direkt in Datenbank
# SELECT * FROM settings WHERE type = 'openrouter';
```

## 📝 Validierung

Die Einstellungen werden validiert beim Speichern:

```php
'openrouterApiKey' => ['required', 'string', 'min:1'],
'openrouterModel' => ['required', 'string', 'min:1'],
'openrouterRefererUrl' => ['required', 'url'],
'dbHost' => ['required', 'string'],
'dbPort' => ['required', 'numeric', 'min:1', 'max:65535'],
'dbDatabase' => ['required', 'string'],
'dbUsername' => ['required', 'string'],
'dbPassword' => ['nullable', 'string'],
```

## 🔄 Migration zu DB-Settings

Falls du noch .env-Variablen hast:

```bash
# .env wird beim Starten geprüft und als Fallback genutzt
OPENROUTER_API_KEY=sk-or-v1-...
OPENROUTER_MODEL=openrouter/auto

# Aber speichere sie über das UI in die DB:
# 1. Gehe zu /admin/config
# 2. Tab "OpenRouter AI"
# 3. Fülle die Felder aus
# 4. Klick "Speichern"

# Danach kannst du die .env-Variablen entfernen
```

## 📖 Links

- [OpenRouter Dokumentation](https://openrouter.ai/docs)
- [Verfügbare Modelle](https://openrouter.ai/models)
- [API Preise](https://openrouter.ai/docs/quickstart)
- [Status-Seite](https://status.openrouter.ai)

## 🚨 Troubleshooting

### "OpenRouter API Key ist nicht konfiguriert"
- Gehe zu /admin/config
- Tab "OpenRouter AI"
- Gib einen gültigen API Key ein
- Speichern

### Settings werden nicht gespeichert
- Überprüfe die Datenbankverbindung
- Prüfe Fehler in `storage/logs/laravel.log`
- Stelle sicher, dass die `settings` Tabelle existiert

### AI-Anfragen schlagen fehl
```bash
# Logs prüfen
tail -f storage/logs/laravel.log

# OpenRouter Status prüfen
# Besuche: https://status.openrouter.ai
```

## 📞 Support

Für Fragen:
1. Prüfe die Logs: `storage/logs/laravel.log`
2. Überprüfe die Einstellungen im Admin-Panel
3. Stelle sicher, dass der OpenRouter API Key gültig ist
4. Kontaktiere den Entwickler

---

**Zuletzt aktualisiert**: 2026-05-29
