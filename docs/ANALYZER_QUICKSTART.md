# RatingDistributionAnalyzer - Quick Start

## ⚡ 30 Sekunden Setup

### 1. DB-Verbindung konfigurieren

Öffne die Einstellungen unter `/admin/config` → Tab **"Datenbankeinstellungen"**

Gib die regulierungs-check-base Verbindungsdaten ein:
- **Host**: 127.0.0.1 (oder deine IP)
- **Port**: 3306
- **Datenbankname**: `regulierungs-check`
- **Benutzername**: `root` (oder dein User)
- **Passwort**: (falls vorhanden)

👉 **Speichern**

### 2. Analyse ausführen

Gehe zu `/admin/config` → Tab **"Bewertungs-Einstellungen"**

Du siehst einen blauen Button **"Analyse starten"** → Klick!

Der Service wird:
- ✅ Connect zur regulierungs-check-base DB
- ✅ Alle echten Bewertungen analysieren
- ✅ Gewichte berechnen
- ✅ Ergebnisse speichern

**Fertig!** Nach wenigen Sekunden siehst du:
```
"Analyse abgeschlossen! 523 Bewertungen analysiert."
```

### 3. Results anwenden

Du siehst jetzt ein grünes Panel mit den Ergebnissen:
```
Analyseergebnisse verfügbar
523 Bewertungen analysiert am 29.05.2026 14:30
```

👉 **Anwenden** Button klicken

Die berechnet Gewichte aus der Analyse werden in die Felder geladen.

### 4. Speichern

👉 **Speichern** Button am oberen rechts

Die Gewichte sind jetzt aktiv!

---

## 🎓 Was passiert im Hintergrund?

### Datenfluss:
```
regulierungs-check-base
    ↓ (claim_ratings Tabelle)
RatingDistributionAnalyzer
    ↓ (analysiert & berechnet)
2261-better Settings DB
    ↓ (gespeichert)
AdminConfig UI
    ↓ (Benutzer klickt Anwenden)
Gewichte werden aktiv
```

### Was wird gemessen:
```
Für JEDE Unterart:
├─ Anzahl Bewertungen
├─ Durchschnittlicher Score
├─ Konsistenz (Standardabweichung)
└─ → Gewicht = 40% Anzahl + 40% Score + 20% Konsistenz
```

---

## 🔍 Beispiel

**Situation:**
- Unterart "Zahnversicherung": 450 Bewertungen, Schnitt 4.1
- Unterart "Augenpflege": 50 Bewertungen, Schnitt 4.9

**Nach Analyse:**
```
Zahnversicherung → Gewicht: 85.3
Augenpflege     → Gewicht: 28.7
```

**Grund:** Zahnversicherung wird viel häufiger bewertet
→ Wird auch häufiger in synthetischen Bewertungen generiert

---

## ❓ FAQ

**F: Wie oft sollte ich analysieren?**  
A: Täglich bis monatlich, je nach Bewertungsfrequenz. Oder nach großen Updates.

**F: Kann ich die Gewichte manuell ändern?**  
A: Ja! Nach "Anwenden" kannst du alle Gewichte noch manuell anpassen.

**F: Was wenn keine Bewertungen gefunden werden?**  
A: Überprüfe die DB-Verbindung und ob es Bewertungen mit Status "published" gibt.

**F: Können alte Analyseergebnisse geladen werden?**  
A: Nicht direkt. Die neueste Analyse überschreibt die alte. Falls nötig: Manuell in DB sichern.

---

## 🚨 Troubleshooting

### "Analyse fehlgeschlagen"
→ Prüfe DB-Verbindung im Tab "Datenbankeinstellungen"
→ Logs: `tail -f storage/logs/laravel.log`

### "Keine Bewertungen gefunden"
→ Stelle sicher: regulierungs-check hat Bewertungen mit Status "published"
→ Überprüf: `is_public = 1`

### "Datenbank nicht erreichbar"
→ Ping: `php artisan tinker → DB::connection('mysql_analytics')->getPDO()`
→ Host/Port/Login überprüfen

---

## 📊 Nächste Schritte

Nach der ersten Analyse:
1. ✅ Überprüfe die Gewichte (sind sie sinnvoll?)
2. ✅ Passe manuell an, falls nötig
3. ✅ Beobachte die generierten Bewertungen
4. ✅ Optimiere regelmäßig

---

**💡 Pro-Tip:** Nutze die Analyse regelmäßig, um deine Bewertungsdaten aktuell zu halten!
