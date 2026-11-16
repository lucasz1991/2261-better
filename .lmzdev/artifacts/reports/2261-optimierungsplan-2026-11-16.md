# 2261: Analyse und Optimierungsplan

> **Umsetzungsstand 16.11.2026:** Die lokale Implementierung wurde nach Freigabe ergänzt. Der ursprüngliche Analyse- und Planungstext unten dokumentiert den Ausgangszustand. Den aktuellen Umfang, Messergebnisse, verbleibende Aktivierungsschritte und Prüfgrenzen beschreibt [2261-umsetzung-2026-11-16.md](2261-umsetzung-2026-11-16.md).

Stand: 16.11.2026, Planrevision 2 mit vertiefter Generatorplanung in Abschnitt 6. Gegenstand: `2261-better` sowie die zugehörigen Daten- und Anzeigewege in `regulierungs-check-base`. Auftrag: Analyse und Planung; keine Umsetzung an Anwendung oder Bestandsdaten.

## Ergebnis

Die ähnlichen Benutzernamen entstehen vor allem durch einen kleinen, stark gewichteten Wortbaukasten. Der Generator sichert unterschiedliche Zeichenfolgen ab, aber keine unterschiedlichen Wortbasen. Zusätzliche Ziffern, Initialen und Ortscodes erhöhen deshalb die technische Eindeutigkeit, ohne die sichtbare Wiederholung wesentlich zu reduzieren.

Suche und einfache Statusfilter existieren bereits. Sie müssen gezielt erweitert werden. Die Bearbeitung veröffentlichter Bewertungen ist momentan ausdrücklich gesperrt; zusätzlich fehlt die Aktualisierung bereits existierender Base-Benutzer. Ein bloßes Freischalten des Formulars würde daher keine zuverlässige Bearbeitung öffentlicher Namen ermöglichen.

Die gewünschte Erweiterung soll Name, Benutzername, Bewertung und öffentliche Darstellung nachvollziehbar ändern können. Dazu gehören ein eindeutiges Feldmapping, eine zuverlässige Synchronisierung und die Anzeige des tatsächlichen Übertragungszustands.

## 1. Nachgewiesene Ursachen

Alle Pfade ohne Projektpräfix beziehen sich auf `2261-better`; Zeilen entsprechen dem untersuchten Stand.

| Befund | Auswirkung | Codebeleg |
|---|---|---|
| 82 feste Aliaswörter erhalten zusammen 40 % Auswahlgewicht. | Einzelne Aliasbasen erscheinen sehr häufig. Viele enthalten ähnliche Wörter wie Wald, Feder, Zeit oder Funke. | `app/Support/Rating/SyntheticIdentityGenerator.php:414–450` |
| Die anderen Muster bestehen aus 28 Adjektiven und 34 Substantiven. 42 % verwenden Adjektiv/Substantiv, 18 % die umgekehrte Reihenfolge. | Nur 952 Wortpaare; Umstellen der Wörter schafft keine neue inhaltliche Basis. | Generator `:431–450` |
| Kontextzusätze und Trennzeichen werden separat gewichtet. | Unterschiedliche Namen können denselben Kern behalten. | Generator `:453–468`, `:781–808` |
| Die Kollisionsprüfung vergleicht nur den vollständigen Benutzernamen. | Ähnliche Wortbasen, häufige Namensfamilien und zeitliche Häufungen bleiben unberücksichtigt. | `app/Models/SyntheticRatingUser.php:50–53`; Generator `:683–702` |
| Nach mehreren Kollisionen werden Zahlen ergänzt; der spätere Fallback ergänzt weitere Zahlen. | Eindeutige Zeichenfolge, weiterhin ähnliche Namen. | Generator `:712–726`, `:803–808` |
| Ein gültiger Name muss einen bekannten Bestandteil des bisherigen Wortpools enthalten. | Neue Namensfamilien erfordern auch eine Anpassung der Validierungsstrategie. | Generator `:666–670`, `:940–955` |
| Bestehende Tests prüfen Eindeutigkeit und einige Schreibweisen, keine Konzentration auf Wortbasen. | Die Tests können erfolgreich sein, obwohl die Namen eintönig wirken. | `tests/Unit/SyntheticIdentityGeneratorTest.php:149–198` |

Es gibt keinen gefundenen Hinweis auf einen konstanten Zufalls-Seed als Hauptursache; die Auswahl verwendet `array_rand` und `random_int` (Generator `:1349–1370`). Eine Erweiterung der Vor- und Nachnamenlisten allein hilft nicht: Diese Pools sind vom Pseudonymbaukasten getrennt.

### Isolierte Stichprobe

Der tatsächliche PHP-Generator wurde über Composer-Autoload ohne Laravel-Bootstrap und ohne Datenbank ausgeführt. Ein In-Memory-Set übernahm die Prüfung exakter Eindeutigkeit.

| Generierte Benutzernamen | Exakt eindeutig | Unterschiedliche Wortbasen | Weitere Namen mit bereits verwendeter Basis |
|---:|---:|---:|---:|
| 300 | 300 | 222 | 78 |
| 1.000 | 1.000 | 567 | 433 |
| 5.000 | 5.000 | 1.021 | 3.979 |

Bei 5.000 Namen kam eine einzelne Aliasbasis bis zu 30-mal vor. Die Messung ignoriert Kontextanhänge, Trennzeichen und die Reihenfolge desselben Wortpaars; sie ordnet anhand des längsten passenden Alias beziehungsweise Wortpaars zu. Sie misst Bausteinwiederholung, keine umfassende sprachliche Ähnlichkeit. Die Ergebnisse sind stochastische Stichproben und ausdrücklich keine Produktionsstatistik.

### Zusätzliche Integritätslücken

- Die Repository-Migration legt `synthetic_rating_users.username` ohne Unique-Index an (`database/migrations/2026_05_31_000003_add_name_parts_to_synthetic_rating_users_table.php:24–25`). Ein Vorab-Existenzcheck allein verhindert keine parallelen Doppelvergaben. Das tatsächliche Live-Schema wurde nicht geprüft.
- Ein syntaktisch gültiger Benutzername aus einem älteren Planungsprofil wird ohne erneute Kollisionsprüfung übernommen (`SyntheticRatingUser.php:134–144`).
- Bereits materialisierte Benutzer werden wiederverwendet (`SyntheticRatingUser.php:106–117`). Eine Verbesserung des Generators beseitigt vorhandene Wiederholungen daher nicht automatisch.

## 2. Bestehende UI und Bearbeitungsgrenzen

| Bereich | Ist-Zustand | Geplante Änderung |
|---|---|---|
| Bewertungsübersicht | Suche, Status, Sortierung und Pagination vorhanden. Liste basiert auf `executed_at != null`. | Ausgeführt, in Base vorhanden, öffentlich sichtbar und synchronisiert als getrennte Zustände behandeln. |
| Geplante Bewertungen | Suche und Ausführungsfilter vorhanden. | Gemeinsame Such-/Filterlogik mit der Bewertungsübersicht. |
| Personensuche | Relation durchsucht nur `name` und `email`. | `username`, Vorname, Nachname, vollständigen Namen und relevante IDs ergänzen; bestehende Planungsprofile ohne Relation berücksichtigen. |
| Mehrfachauswahl | „Alle anstehenden“ ignoriert aktuelle Suche und Filter. | „Diese Seite“ und „Alle Treffer“ auf derselben gefilterten Query; globale Auswahl ausdrücklich separat benennen. |
| Veröffentlichte Bewertung | Editor sperrt Datensätze mit `executed_at` oder `base_claim_rating_id`. | Eigenen Bearbeitungsweg für veröffentlichte Datensätze ergänzen. |
| Benutzerdaten | `name` wird beim Speichern mit `username` überschrieben. | Anzeigename und Benutzername unabhängig bearbeitbar machen; automatische Gleichsetzung nur als ausdrücklich gewählte Bedienoption. |
| Base-Benutzer | Existierende Benutzer werden nur verknüpft, nicht aktualisiert. | Explizite, feldweise Aktualisierung bereits bestehender zugehöriger Benutzer. |

Belege: `app/Livewire/Admin/ClaimRatings.php:17–79`; `app/Livewire/Admin/PlannedClaimRatings.php:80–89`; `app/Models/ClaimRating.php:105–135`; `app/Livewire/Admin/Concerns/ShowsClaimRatingModal.php:60–146`; `app/Services/BaseClaimRatingPublisher.php:337–404`.

Die Gleichsetzung von `name` und `username` war eine dokumentierte Entscheidung vom 06.09.2026, kein zufälliger Tippfehler. Die geplante unabhängige Bearbeitung ist deshalb eine bewusste Weiterentwicklung dieses Vertrags. Vorhandene Werte bleiben beim Einführen unverändert; Neuanlagen dürfen zunächst weiterhin denselben Wert erhalten. Die zugehörigen bestehenden Tests müssen an die neue Bearbeitungssemantik angepasst werden.

## 3. Zielbild für Suche und Filter

Die bestehenden Ansichten bleiben erhalten und bekommen eine gemeinsame Filterleiste. Eine zusätzliche Benutzeransicht bündelt alle Bewertungen derselben Person.

| Element | Geplanter Inhalt |
|---|---|
| Suchfeld | Anzeigename, Benutzername, Vor-/Nachname, E-Mail, lokale Bewertungs-/Benutzer-ID, Base-ID, Versicherer; Suche im Bewertungstext über definierte Textfelder. |
| Status | Geplant, vorbereitet, läuft, ausgeführt, fehlgeschlagen, zurückgerufen; zusätzlich Veröffentlichung und Synchronisation getrennt. |
| Zeitraum | Explizite Auswahl „geplant am“, „ausgeführt am“, „erstellt am“, „geändert am“ mit Von/Bis und Tagesgrenzen in Europe/Berlin. |
| Fachliche Filter | Versicherer, Versicherungsart, Unterart, Bewertungsbereich. |
| Benutzerfilter | Benutzerzuordnung, Generatorversion, manuell bearbeitet, fehlende Base-Verknüpfung. |
| Qualitätsfilter | Exakte Dublette, gleiche Wortbasis, häufige Wortfamilie, lokale/Base-Abweichung. Ähnlichkeit ist ein Prüfhinweis. |
| Sortierung | Datum, Name, Benutzername, Bewertung, Status; stabile sekundäre Sortierung nach ID. |
| Bedienung | Trefferzahl, entfernbare Filterchips, „Zurücksetzen“, 25/50/100 Einträge, verständlicher Leerzustand, Ladezustand, Tastaturbedienung und mobile Filterfläche. |
| Auswahl | Anzahl und Umfang sichtbar; Filterwechsel setzt Auswahl zurück. Vor Ausführung werden IDs, Rechte und Bearbeitbarkeit erneut geprüft. |

Die Suche bleibt serverseitig, mit etwa 300 ms Eingabeverzögerung. Kombinierte Filter verwenden gemeinsame Scopes, erlaubte Sortierfelder und begrenzte Seitengrößen. Bei Filterwechsel wird auf Seite 1 zurückgesetzt. Unkritische Filter und Sortierung können in der URL erhalten bleiben; personenbezogene Freitextsuche sollte bevorzugt in der Sitzung bleiben. Geeignete Indizes erst anhand tatsächlicher Abfragepläne und Datenmenge ergänzen: Ein normaler Index beseitigt keine beliebige `%teilstring%`-Suche.

Die Benutzeransicht zeigt Anzeigename, Benutzername, Anzahl zugehöriger Bewertungen, Base-Zuordnung und Synchronisationszustand. „Benutzer bearbeiten“ ist sowohl dort als auch direkt aus einer Bewertung erreichbar.

## 4. Name und Benutzername eindeutig abbilden

| UI-Feld | Lokale Bedeutung | Ziel auf der öffentlichen Seite |
|---|---|---|
| Anzeigename | `synthetic_rating_users.name` | `users.name`; dieses Feld wird von der öffentlichen Anzeige tatsächlich verwendet. |
| Benutzername | `synthetic_rating_users.username` | Verbindliches Mapping auf das tatsächlich vorhandene Base-Profilfeld festlegen. Im Repository liegt `username` in `customers`, nicht im Grundschema von `users`. |
| Vorname / Nachname | Eigene Spalten und konsistente Persona-Metadaten | Vorhandene zugehörige Profilfelder gemäß geprüftem Base-Schema. |
| Öffentliche Vorschau | Tatsächlicher Anzeigename und aktuelle Sichtbarkeitsregeln | Muss die Base-Ausgabe einschließlich Anonymisierung wiedergeben. |

Belege für die öffentliche Anzeige: `regulierungs-check-base/resources/views/components/user/public-info.blade.php:32`; `regulierungs-check-base/resources/views/livewire/banner/homepage-claimratings-random-banner.blade.php:12–14`. Profilfelder: `regulierungs-check-base/database/migrations/2024_11_20_174208_create_customers_table.php:14–16`.

Vor Umsetzung ist eine lesende Schemaprüfung erforderlich. Der heutige Publisher entfernt fehlende Zielspalten still aus dem Payload (`BaseClaimRatingPublisher.php:330–334`); das darf bei einer zugesagten Bearbeitung nicht als erfolgreiche Speicherung erscheinen. Ein expliziter Profiladapter muss unterstützte Zielfelder und Zuordnung prüfen. Fremde Kundenprofile dürfen nicht automatisch übernommen werden. Falls für synthetische Benutzer ein erforderliches Profil fehlt, ist dessen kompatible Anlage einschließlich Pflichtfeldern und Nebenwirkungen separat zu implementieren und zu testen; zusätzliche konkurrierende Namensfelder sind zu vermeiden.

## 5. Veröffentlichte Bewertungen und Benutzer bearbeiten

Empfohlener Ablauf: Bewertung öffnen, „Bearbeiten“ wählen, Änderungen an Bewertung und/oder Benutzer eingeben, öffentliche Vorschau und betroffene Bewertungen sehen, mit Änderungsgrund speichern und Synchronisationszustand verfolgen.

- **Getrennte Bereiche:** „Bewertung“, „Benutzer“ und „Änderungsverlauf“. Strukturierte Felder statt frei editierbarer JSON-Blöcke für die normale Bearbeitung. Technische IDs, Herkunftsmarker und Verknüpfungen bleiben geschützt.
- **Bearbeitungsumfang:** Name, Benutzername, Vorname und Nachname zuerst; Bewertungsantworten/-text sowie fachlich zulässige Metadaten im selben Ablauf. Den Score aus den gültigen Antworten berechnen beziehungsweise eine erlaubte manuelle Abweichung ausdrücklich protokollieren.
- **Tatsächlich sichtbarer Bewertungstext:** Die öffentliche Karte verwendet `attachments.scorings.ai_overall_comment`, nicht unmittelbar den Text jeder Antwort (Base `resources/views/components/claim-rating/claim-rating-card.blade.php:4–17`). Antworten, freigegebene Zusammenfassung und Score müssen konsistent bleiben. Reine Namensänderungen dürfen keine neue AI-Auswertung auslösen.
- **Keine Neuveröffentlichung:** Bestehende IDs, ursprünglicher Veröffentlichungszeitpunkt und Zuordnung bleiben erhalten. Keine neue AI-Erzeugung oder neue Person beim bloßen Bearbeiten.
- **Auswirkung anzeigen:** Eine Benutzeränderung gilt für die Person und kann alle ihre veröffentlichten Bewertungen betreffen. Anzahl und Links vorher anzeigen; nur für einen einzelnen Eintrag gewünschte Anzeigekorrekturen benötigen ein ausdrücklich separates Datenmodell.
- **Validierung:** Feldlängen, Zeichenregeln, Eindeutigkeit, Datentypen, fachliche Bewertungsgrenzen und erlaubte Änderungen serverseitig prüfen. Die eigene verifizierte Benutzer-ID bei der Eindeutigkeitsprüfung ausnehmen.
- **Rechte und Zuordnung:** Eigene Berechtigungen für Lesen, Bewertungsänderung und Benutzeränderung an jeder Aktion. Bestehende rollenbezogene Unterschiede zwischen den Routen vereinheitlichen. Für synthetische Datensätze Herkunft und exakte lokale/Base-Verknüpfung prüfen. Echte oder fremde Konten brauchen einen eigenen autorisierten Verwaltungsweg.
- **Änderungsverlauf:** Bearbeiter, Zeitpunkt, Grund, erlaubte Vorher-/Nachher-Felder, betroffene IDs und Synchronisationsergebnis erfassen. Technische Geheimnisse und Authentifizierungsdaten ausschließen.
- **Gleichzeitige Bearbeitung:** Versionsvergleich beziehungsweise optimistische Sperre verhindert, dass ein älteres Formular neuere Änderungen überschreibt. Revisionen werden sowohl pro Bewertung als auch pro Benutzer geführt: Zwei unterschiedliche Bewertungen können denselben Benutzer betreffen. Betroffene Base-Zeilen werden innerhalb der Transaktion gesperrt und ihre Zuordnung dort erneut geprüft.

### Synchronisierung als eigener Änderungsweg

`publish()` ist kein geeigneter allgemeiner Editor: Der aktuelle Payload setzt unter anderem `status=rated`, `is_public=true` und einen neuen `verification_hash` (`BaseClaimRatingPublisher.php:289–323`). Ein separater Update-Service muss nur freigegebene, tatsächlich geänderte Felder aktualisieren und Moderation/Sichtbarkeit/Verifikation nach deren bestehendem Vertrag behandeln.

Empfohlen ist eine lokale Transaktion aus neuer Revision, angepassten lokalen Daten und dauerhaftem Übertragungsauftrag. Der Auftrag aktualisiert Base-Bewertung und zugehörige Base-Benutzerdaten gemeinsam innerhalb einer Base-Transaktion. Operation-ID und Revisionsnummer machen Wiederholungen sicher; ältere Aufträge dürfen neuere Revisionen nicht überschreiben. Ein Fehler zwischen Base-Commit und lokaler Bestätigung muss durch Wiederholung heilbar sein.

Die UI unterscheidet „lokal gespeichert, Übertragung ausstehend“, „synchronisiert“, „Konflikt“ und „Übertragung fehlgeschlagen“. Zwei getrennte Datenbankverbindungen bilden keine gemeinsame atomare Transaktion. Bereits vorhandene widersprüchliche Base-Änderungen werden anhand der zuletzt synchronisierten Revision beziehungsweise Feldwerte erkannt und nicht blind überschrieben.

Planungsprofile und Persona-Snapshots werden nach klarer Zuständigkeit mitgeführt. Bei wiederverwendeten Benutzern sind auch weitere abhängige Profile zu berücksichtigen. Herkunftsmetadaten bleiben erhalten. Die geprüften Base-Auswertungen berechnen Versicherungsdurchschnitt und Verteilung direkt aus sichtbaren Bewertungen (`app/Models/Insurance.php:244–270`); ein expliziter Bewertungs-Cache wurde in den untersuchten Anzeigewegen nicht gefunden. Eventuelle zusätzliche Deployment-Caches sind vor der Einführung zu prüfen.

Der aktuelle Publisher schreibt per Query Builder und umgeht damit die fachlichen Model-Events der Base. Diese können bei Antworten-/Attachment-Änderungen Neubewertungen und bei Datenänderungen Admin-Aufgaben auslösen (Base `app/Models/ClaimRating.php:72–90`). Der Update-Service muss diese Nebenwirkungen bewusst steuern. Da auch der Admin einen Bewertungseditor besitzt, muss die Konflikterkennung Änderungen aus beiden Verwaltungswegen berücksichtigen.

## 6. Ausführlicher Optimierungsplan für den Namensgenerator

Erweiterung auf Wunsch vom 16.11.2026. Dieser Abschnitt ersetzt die bisherige Kurzplanung. Sämtliche folgenden Größen und Gewichte sind vorgeschlagene Startwerte, keine bereits gemessenen Verbesserungen. Die übrigen Anforderungen an Suche und Bearbeitung bleiben Bestandteil desselben Plans.

### 6.1 Ziel und Geltungsbereich

Neue Benutzernamen sollen sich erkennbar häufiger in ihren eigentlichen Wörtern unterscheiden. Das betrifft zunächst die automatisch erzeugten Pseudonyme; Vorname, Nachname und E-Mail werden dabei nicht neu gewürfelt. Manuell bearbeitete Anzeigenamen bleiben erhalten. Wenn eine Neuanlage den Benutzernamen zugleich als Anzeigenamen erhält, profitiert die öffentliche Anzeige automatisch von der größeren Vielfalt.

Das System muss vier Dinge getrennt beherrschen: ausreichenden Wortvorrat, erkennbare Ähnlichkeit, Nutzungshistorie und sichere Vergabe. Ein größerer Zufallsbereich allein genügt nicht. Ebenso wenig reicht es, an dieselben Wörter immer neue Zahlen anzuhängen.

### 6.2 Was genau als ähnlich gilt

| Prüfebene | Beispiel, nur zur Erklärung | Geplante Behandlung |
|---|---|---|
| Exakte Kennung | Derselbe Benutzername ein zweites Mal; Groß-/Kleinschreibung zählt gemäß festem Normalisierungsvertrag nicht als Unterschied. | Im vereinbarten Namensraum immer verhindern, auch bei parallelen Aufträgen. |
| Dieselbe Wortbasis | `waldpfad82`, `waldpfad_hh`, `waldpfad.91` | Alle besitzen die Basis `waldpfad`; während der Wiederholungssperre keine weitere automatische Vergabe. |
| Umgestellte Wörter | `ruhiger.fuchs`, `fuchs_ruhiger` | Identische Wortkombination, keine zusätzliche Basis. |
| Sehr ähnliche Schreibweise | `waldpfad`, `waldpfadd` | Gegen die jüngsten Namen auf geringe Zeichenabweichung prüfen und auffällige Kandidaten verwerfen. |
| Gemeinsamer Wortbestandteil | `waldfeder`, `waldpfad`, `waldfreund` | Unterschiedliche Basen, aber gemeinsame Familie `wald`; bei Häufung deutlich seltener auswählen. |
| Gemeinsames breites Thema | Natur, Technik, Musik oder Sport | Nur Verteilung steuern; das Thema allein ist kein Dublettenfehler. |

Für jeden neuen Kandidaten werden deshalb mehrere Merkmale erzeugt:

- `username_key`: normalisierter vollständiger Benutzername; erlaubte Trennzeichen bleiben für exakte Eindeutigkeit erhalten. Die Regeln müssen zur Datenbank-Kollation passen.
- `core_key`: stabile Kennung der eigentlichen Basis; bekannte Anhänge und Trennzeichen zählen nicht dazu. Gleichwertige Wortreihenfolgen und Flexionsvarianten verwenden dieselbe Basiskennung.
- `token_keys` und `family_keys`: kuratierte Wort-/Familienkennungen, beispielsweise `wald`, `feder` oder `fuchs`; dazu eine getrennte, gröbere Themenzuordnung.
- `pattern_key`, Kontextart, Katalogversion und Generatorversion: erklären, wie der Name entstanden ist.

Die Merkmale kommen bei neuen Namen direkt aus dem Bauplan. Zahlen, Ortskürzel und kurze Buchstabenfolgen werden nicht nachträglich blind aus jedem Namen entfernt. Auch Familien werden nicht durch beliebige Teilzeichenfolgen erkannt: `echt` in `specht` wäre beispielsweise ein Fehlalarm. Bei alten Namen wird die Zuordnung als „sicher“, „geschätzt“ oder „unbekannt“ gekennzeichnet; unbekannte Basen bleiben in der Abdeckungsstatistik sichtbar.

### 6.3 Den Wortvorrat wirklich verbreitern

Der bisherige Baukasten bietet höchstens 82 + 28 × 34 = **1.034 kanonische Basen**, wenn Reihenfolge und Zusätze nicht als Vielfalt gezählt werden. Bei 10.000 Namen wären damit mindestens 8.966 Wiederholungen unvermeidbar. Schon die Halbierung dieser rechnerischen Untergrenze verlangt mindestens 5.517 nutzbare Basen.

Als Ausbauziel werden **mindestens 15.000 geprüfte, kanonisch unterschiedliche Basen** vorgesehen. Das ist ein zu erarbeitender Katalog, kein vorhandener Bestand. Gezählt werden nur grammatisch passende und nach Normalisierung weiterhin unterschiedliche Kombinationen; theoretische Multiplikationen und verschiedene Endziffern zählen nicht. Zusätzlich wird geprüft, wie viele Basen nach den personenbezogenen Ausschlussregeln für repräsentative Personas tatsächlich verfügbar bleiben.

| Mustergruppe | Vorgeschlagener Startanteil | Inhalt |
|---|---:|---|
| Kuratierte eigenständige Pseudonyme | 20 % | Mehrere hundert unterschiedliche Begriffe über viele Wortfamilien; nicht überwiegend Wald/Feder/Zeit. |
| Passendes Adjektiv und Substantiv | 35 % | Deutlich größere, grammatisch und inhaltlich geprüfte Pools; gleiche Wörter in umgekehrter Reihenfolge zählen zur selben Basis. |
| Zwei passende Wortbestandteile | 30 % | Freigegebene zusammengesetzte Begriffe aus verschiedenen Themenfeldern. |
| Tätigkeits- und Interessenbegriffe | 15 % | Geprüfte Kombinationen aus Alltag, Handwerk, Bewegung, Kultur und Technik. |

Die Anteile sind **weiche Auswahlziele**, keine starren Quoten. Ist eine Gruppe weitgehend ausgeschöpft, darf sie keine Wiederholungen erzwingen; verfügbare andere Gruppen übernehmen. Ein kleiner Aliasvorrat darf nicht erneut einen unverhältnismäßig großen Teil der Namen liefern.

Der Katalog erhält mindestens acht ausreichend besetzte Themenbereiche, Wortarten, zulässige Kombinationen und Familienkennungen. Unpassende Grammatik, unverständliche Wortketten und versehentliche Namensbestandteile werden vor Freigabe aussortiert. Ein dauerhaft gleicher Stil wie „positives Adjektiv + Naturwort“ soll dadurch seltener werden. Zur Laufzeit genügt eine lokale, versionierte Auswahl; eine externe KI-Abfrage pro Benutzer ist dafür nicht vorgesehen.

### 6.4 Nutzungshistorie und Wiederholungspausen

Es werden neue oder durch Umbenennung geänderte Benutzervergaben gezählt, nicht einzelne Bewertungen: Ein Benutzer mit fünf Bewertungen belegt einen Namen; dessen unveränderte Wiederverwendung ist keine neue Vergabe. Öffentliche Häufigkeiten werden zusätzlich separat gemessen, damit eine oft eingeblendete Person nicht fälschlich als fünf Generator-Dubletten gilt.

| Regel | Vorgeschlagener Startwert | Wirkung |
|---|---|---|
| Exakte Kennung | Gesamter verbindlich definierter Namensraum | Dauerhafte Eindeutigkeit; gelöschte/umbenannte Kennungen zunächst weiter reservieren. |
| Gleiche Basis | Letzte 1.000 verbindliche Vergaben plus offene Planreservierungen | Harte Sperre für neue automatische Vergaben; Anhänge umgehen sie nicht. |
| Gleiche Wortfamilie | Letzte 200 Vergaben | Häufige Bestandteile erhalten ein geringeres Auswahlgewicht. |
| Mittelfristige Häufung | Vergaben der letzten 30 Tage | Weitere Gewichtssenkung bei wiederkehrenden Basen und Familien. |
| Lebenslange Nutzung | Gesamte verfügbare Historie | Bisher unbenutzte Basen werden bevorzugt, danach selten verwendete. |
| Geplante gemeinsame Veröffentlichung | Geplanter Tag bzw. zusammen erzeugter Auftrag | Zusätzliche weiche Strafe bei Familienhäufung, weil Vergabe- und Veröffentlichungsreihenfolge abweichen können. |

Die Mengenfenster verhindern Wiederholungen auch bei schwankendem Tagesvolumen. Das Zeitfenster ergänzt sie, ohne bei hohem Volumen alle Basen dauerhaft zu blockieren. Familiengrenzen bleiben weich: Gemeinsame Wörter sind nicht generell verboten. Eine Warnschwelle von zunächst mehr als 5 % derselben Wortfamilie unter den letzten 200 Vergaben löst eine stärkere Gewichtssenkung aus; sie ist nach den Vergleichsläufen anzupassen.

Reservierte Namen aus demselben Planungslauf und anderen gleichzeitig laufenden Aufträgen müssen mitgezählt werden. Bereits verbindlich gespeicherte zukünftige Planidentitäten bleiben reserviert, bis sie materialisiert oder ausdrücklich verworfen werden. Die Zahl verfügbarer Basen wird vor großen Planläufen anhand der Vereinigungsmenge gesperrter Basen geprüft; Fensterbelegung und offene Reservierungen werden getrennt angezeigt, gemeinsame Basen nicht doppelt abgezogen. Ein Fenster von 1.000 bedeutet nicht, dass zusätzlich beliebig viele offene Planreservierungen möglich sind. Die harte Basisgarantie gilt für neue Metadaten und sicher erkannte historische Basen; unbekannte Altbasen bleiben eine ausdrücklich ausgewiesene Abdeckungslücke.

### 6.5 Konkreter Ablauf einer Vergabe

1. **Auftrag erkennen:** Existiert für diesen Benutzer oder Planauftrag bereits eine verbindliche Vergabe, dieselbe Identität zurückgeben. Wiederholte Jobs erzeugen keine weiteren Benutzer und verbrauchen keine zusätzlichen Namen.
2. **Bestand laden:** Bekannte exakte Kennungen, jüngste Basen, offene Reservierungen und aggregierte Familienhäufigkeiten laden. Eine gemeinsame Bestandsaufnahme kann innerhalb eines Batches wiederverwendet werden.
3. **Kandidaten erzeugen:** Zunächst 24 unterschiedliche Basen aus aktuell ausreichend verfügbaren Mustergruppen ziehen. Unterrepräsentierte Themen und seltene Wörter bevorzugen. Doppelte Basen innerhalb dieses Kandidatensatzes entfernen.
4. **Harte Regeln anwenden:** Syntax, Länge, Ausschluss vollständiger Personennamensbestandteile, gültige Katalogkombination, Basissperre und sehr hohe Schreibähnlichkeit prüfen. An dieser Stelle gibt es noch keinen rettenden Zahlenanhang.
5. **Verbleibende Kandidaten bewerten:** Zuerst bisher unbenutzte Basen bevorzugen. Innerhalb derselben Nutzungsklasse erhalten seltene Wortfamilien, passende Themenverteilung und geringe Schreibähnlichkeit Vorrang. Unter den fünf besten zulässigen Kandidaten gewichtet zufällig auswählen, damit nicht immer derselbe Gewinner entsteht.
6. **Optionalen Kontext ergänzen:** Erst jetzt Initialen, zulässige Ortscodes oder optionale Zahlen gemäß bestehendem Produktvertrag ergänzen. Diese verändern nicht die Basisbewertung. Den endgültigen Namen erneut vollständig validieren; weder Kürzen noch Endungen dürfen die Prüfung umgehen.
7. **Verbindlich reservieren:** Unter einer kurzen lokalen Transaktion und Vergabesperre den aktuellen Bestand erneut prüfen, Kennung/Basis reservieren und Benutzer beziehungsweise Planidentität konsistent speichern. Hat ein paralleler Auftrag den Kandidaten belegt, erneut auswählen.
8. **Begründung speichern:** Generator-/Katalogversion, Basis, Familien und verwendete Regelversion hinterlegen. Technische Diagnose speichert aggregierte Gründe und Zähler, keine vollständigen Persona-Daten.

Als Startbudget gelten höchstens vier Runden mit je 24 unterschiedlichen Kandidaten. Kandidaten sollen möglichst bereits aus dem nach harten Katalog-/Basissperren gefilterten Index kommen. Reicht das nicht aus, folgt eine begrenzte gezielte Suche in noch verfügbaren Bereichen; die Zahl der Prüfungen bleibt begrenzt. 96 erfolglose Prüfungen beweisen keine Poolerschöpfung: „Suchbudget erreicht“, „derzeit gesperrt“ und „keine geeignete Basis im geprüften Katalog“ erhalten getrennte Diagnosegründe. Bei weiterhin fehlendem Kandidaten wird der Auftrag nachvollziehbar zurückgestellt beziehungsweise als fehlgeschlagen behandelt. **Kein stiller Rückfall auf eine bereits häufige Basis plus neue Zahl.** Ein anzahlgenauer Planlauf darf deshalb nicht unbemerkt weniger Bewertungen liefern; er muss seinen bestehenden Fehler-/Rollback-Vertrag einhalten.

Die Bewertungsgewichte werden anhand von Vergleichsläufen kalibriert. Es ist keine mathematische „Natürlichkeitsgarantie“ vorgesehen. Die erklärbare Regel „diese Familie kam zuletzt häufig vor“ ist wichtiger als ein undurchsichtiger Gesamtwert.

### 6.6 Ähnliche Schreibweisen gezielt erkennen

Die Basissperre erkennt dieselbe bekannte Wortkombination zuverlässig. Eine zweite Prüfung erfasst nahe Schreibweisen anderer Basen. Dafür werden normalisierte Basisdarstellungen, nicht die endgültigen Namen samt Geburtsjahr und Ortscode, verglichen.

Vorgeschlagener Start: Bei Basen ab acht Zeichen ist eine Zeichenähnlichkeit von mindestens 0,88 ein Ablehnungsgrund im definierten jüngsten Vergleichsfenster. Der Wert ergibt sich aus `1 − Levenshtein-Abstand / maximale Länge`. Für kürzere Basen gilt eine eigene konservative Regel: sehr kurze Wörter nicht allein wegen eines einzelnen abweichenden Buchstabens sperren; bei fünf bis sieben Zeichen Abstand 1 zunächst als Prüf-/Gewichtungshinweis behandeln. Diese Schwellen sind Testparameter und anhand geprüfter Positiv-/Negativbeispiele zu kalibrieren.

Der Zeichenvergleich ersetzt die Wortfamilienlogik nicht: Zwei völlig unterschiedlich geschriebene Wörter können thematisch nah sein, und zwei ähnliche Zeichenfolgen können verschiedene sinnvolle Wörter darstellen. Keine pauschale Sperre ganzer Themen und keine unkontrollierte transitive Gruppenbildung nach dem Muster „A ähnelt B, B ähnelt C, also sind alle identisch“.

Für den ersten Ausbau kann ein begrenzter Vergleich gegen die letzten 1.000 Basen und aktive Reservierungen in Arbeitsspeicher genügen. Bei größerem Umfang werden passende Längenbereiche und indizierte Zeichenfolgen zur Vorselektion verwendet; deren Trefferabdeckung muss getestet werden. Ein Gesamtvergleich jedes Namens mit jedem anderen Namen ist nicht vorgesehen. Eine Vorselektion mit begrenzter Trefferzahl darf nicht als lückenlose globale Ähnlichkeitsgarantie ausgegeben werden.

### 6.7 Datenmodell, Parallelität und Zuständigkeiten

Der Generator wird in klar abgegrenzte Aufgaben aufgeteilt; konkrete Klassennamen sind Vorschläge:

| Baustein | Aufgabe |
|---|---|
| `UsernameCatalog` | Versionierter Wortkatalog, zulässige Kombinationen, Basis-/Familienkennungen und verfügbare Kapazität. |
| `UsernameFingerprint` | Einheitliche vollständige Kennung, Basisdarstellung und Merkmale; nachvollziehbare Altbestandserkennung. |
| `UsernameDiversitySelector` | Kandidaten, Nutzungshistorie, Familiengewichtung, Schreibähnlichkeit und begrenzte Auswahl. |
| `UsernameAllocator` | Transaktion, Reservierung, Eindeutigkeit, Auftragswiederholung und Konfliktbehandlung. |

Die bestehende `SyntheticIdentityGenerator::username()`-Schnittstelle kann als Einstieg erhalten bleiben und diese Zuständigkeiten delegieren. `createForClaimRating()`, die Legacy-Materialisierung und manuelle Umbenennungen müssen denselben Vergabe-/Validierungspfad verwenden. Bereits materialisierte oder manuell bearbeitete Identitäten werden nicht allein durch erneuten Aufruf umbenannt.

Vorgesehen sind eine eindeutige normalisierte Kennung im maßgeblichen Register, ein Vergabe-/Reservierungsdatensatz mit Benutzer-/Planbezug und Nutzungshistorie sowie indizierte Basis-/Familienmerkmale. Katalog-, Normalisierungs- und Generatorversion werden getrennt gespeichert; E-Mail- und Persona-Erzeugung müssen für eine reine Username-Version nicht verändert werden. Familienzähler sind ableitbar und müssen aus der Historie wiederherstellbar sein.

Für das vorhandene volumenarme Planungsmodell ist zunächst eine kurze serialisierte Finalisierung pro Namensraum sinnvoll: eine bereits existierende Kontrollzeile sperren, jüngste Vergaben nachprüfen, reservieren und speichern. Das vermeidet auch, dass zwei parallele Prozesse verschiedene Zeichenfolgen mit derselben gesperrten Basis vergeben. Ein Unique-Index nur auf dem vollständigen Benutzername würde dieses Problem nicht lösen. Die Kandidatenerzeugung findet außerhalb der Sperre statt; die endgültige Prüfung darin. Laufzeit und Sperrwartezeiten werden gemessen, bevor feinere Parallelisierung eingeführt wird.

Die exakte Eindeutigkeitsgarantie gilt zunächst für die zentral koordinierten 2261-Identitäten. Ein lesender Blick in Base verhindert keine gleichzeitige Vergabe durch andere Anwendungen. Eine Garantie über alle Base-Konten setzt voraus, dass alle betreffenden Schreibwege dieselbe Datenbankregel beziehungsweise Vergabestelle verwenden; dies ist Teil des Feldmapping-Pakets aus Abschnitt 4.

Eine reine Vorschau reserviert nichts. Verbindliche Planreservierungen haben keinen beliebigen kurzen Ablauf, der noch benötigte Identitäten freigibt. Abgebrochene technische Zwischenreservierungen werden nur nach gesichertem Auftragsstatus freigegeben. Wiederherstellung gelöschter Benutzer, Umbenennung und wiederholte Materialisierung erhalten jeweils eine explizite Regel; ein wiederhergestellter eigener Benutzer darf nicht an seiner eigenen Reservierung scheitern.

### 6.8 Prüfkriterien und messbare Zielwerte

Für einen Lauf mit `N` erfolgreich vergebenen Namen und `U` verschiedenen Basen ist die **Basiswiederholung** `N − U`, der Anteil `(N − U) / N`. Davon getrennt ausweisen: Anzahl mehrfach verwendeter Basen, Anzahl betroffener Benutzer, Anzahl ähnlicher Paare und Anteil nicht zuordenbarer Alt-Namen. Bei unvollständiger Basiserkennung zählen unbekannte Namen nicht automatisch als einzigartig; Abdeckung und Kennzahlen des auswertbaren Teilbestands werden angegeben.

| Kriterium | Vorgeschlagenes Abnahmeziel |
|---|---|
| Exakte Vergabe | Keine doppelte verbindliche Kennung innerhalb des koordinierten Namensraums, auch bei Konkurrenz und Wiederholung. |
| Sperrfenster | Keine neue automatische Vergabe derselben sicher erkannten Basis innerhalb der letzten 1.000 Vergaben oder gegen offene Reservierungen. |
| 1.000er-Lauf ab leerem Bestand | 1.000 erfolgreiche Vergaben, 1.000 verschiedene Basen. |
| 10.000er-Lauf ab leerem Bestand | Vorläufiges Ausbauziel: mindestens 9.000 verschiedene Basen; verfügbare Kapazität und weiche Musteranteile müssen das ermöglichen. |
| Vergleich mit altem Generator | Mindestens 50 % weniger Basiswiederholungen unter denselben Messbedingungen als Mindestziel; 10.000er-Baseline neu erheben. |
| Wortfamilien | Deutlich weniger Konzentration der häufigsten Familien; vorläufige Warnschwelle 5 % je Familie im 200er-Fenster, keine Garantie starrer Gleichverteilung. |
| Schreibähnlichkeit | Geprüfte Varianten mit nur zusätzlichem Zeichen oder Kontext zuverlässig erkennen; dokumentierte Negativbeispiele nicht pauschal sperren. |
| Ausschöpfung | Fehlversuche, nicht erfüllte Anfragen, Kandidatenzahl und Wartezeiten mit ausweisen. Verbesserungen dürfen nicht nur dadurch entstehen, dass viele Anfragen scheitern. |
| Leistung | Laufzeit pro Vergabe, Gesamtzeit eines Planlaufs, Datenbankabfragen und Sperrwartezeit erfassen; Grenzwerte auf der Zielumgebung festlegen. |

Die Stichproben der Erstanalyse bleiben die dokumentierten **alten** Ergebnisse. Für die geplante Version existieren noch keine Messergebnisse. Zielwerte sind vor der Einführung auf ausreichende Kapazität und Fehlalarmrate zu prüfen, nicht nachträglich als bereits erreicht darzustellen.

Geplant sind mindestens zehn Vergleichsläufe je Konfiguration mit gleichen Persona-Eingaben und gleichem Bestandsstand. Wiederholbare Tests erhalten eine injizierbare Zufallsquelle; das heutige `random_int` wird nicht fälschlich als durch einen allgemeinen Seed steuerbar angenommen. Für Produktionsvergaben bleibt eine geeignete Zufallsquelle erhalten. Verteilung, Median und schlechtester Lauf werden berichtet; ein einzelner günstiger Zufallslauf genügt nicht.

Zusätzlich zu frischen Beständen sind vorbefüllte und beinahe ausgeschöpfte Bestände, einseitig belegte Wortfamilien, vollständig gesperrte Mustergruppen, problematische kurze Personennamensbestandteile, Batchplanung, mindestens zehn konkurrierende Worker, Wiederholung nach Absturz sowie bereits manuell korrigierte Benutzer zu prüfen. Die generatorbezogene Abnahme ersetzt die Prüfung von Base-Synchronisierung und veröffentlichten Namen nicht.

### 6.9 Ergänzungen in der UI

Die geplante Verwaltung erhält einen Bereich **„Namensqualität“** mit vier getrennten Filtern: exakte Dubletten, gleiche Basis, ähnliche Schreibweise und häufige Wortfamilie. Pro Gruppe werden betroffene Benutzer, zugehörige Bewertungen, öffentliche Anzeige, Erkennungsgrund und Sicherheit der Zuordnung angezeigt. Beispiel: „Gleiche Basis; unterscheidet sich nur durch Ortscode.“

Eine Vorschau erzeugt auf Wunsch 1.000 Namen ohne Benutzeranlage oder Veröffentlichung und stellt alte/neue Konfiguration unter denselben Bedingungen gegenüber. Sie zeigt erfolgreiche Vergaben, Basiswiederholungen, häufigste Familien, Musterverteilung, Fehlversuche und Laufzeit. Zeitpunkt und Abdeckung des geprüften Bestands bleiben sichtbar, beispielsweise „lokaler Bestand geprüft; Base nicht vollständig abgeglichen“.

Im normalen Einstellungsbereich genügen ein freigegebenes Qualitätsprofil und die Vorschau. Fenstergrößen, Gewichtungen und Zeichenähnlichkeitsschwellen liegen zunächst in versionierter technischer Konfiguration. Später angebotene Profile müssen ihre konkreten Regelunterschiede erläutern; keine unklare Prozentanzeige „Natürlichkeit“.

Im Benutzereditor liefert **„Andere Benutzernamen vorschlagen“** beispielsweise fünf unterschiedliche, geprüfte Basen. Die Vorschläge stammen auch untereinander möglichst aus verschiedenen Familien. Auswahl oder freie Eingabe wird beim Speichern erneut geprüft. Exakte Kollisionen blockieren; bloße manuelle Ähnlichkeit zeigt einen begründeten Hinweis. Eine bewusste Ausnahme wird protokolliert und als solche in der Qualitätsauswertung erkennbar. Sie darf keine falsche Garantie für automatisch erzeugte Namen suggerieren.

Der Anzeigename wird nur mit geändert, wenn dies im Formular ausdrücklich ausgewählt wurde. Vorname, Nachname, E-Mail und Bewertungen bleiben bei einem reinen Username-Vorschlag unberührt. Öffentliche Änderungen laufen über den geplanten Editor mit Vorschau und Synchronisierung aus Abschnitt 5.

### 6.10 Bestehende ähnliche Namen behandeln

Zuerst nur eine lesende Bestandsauswertung: sichere exakte Konflikte, erkannte Basen, häufige Familien und vermutete Schreibähnlichkeiten. Jeder betroffene Benutzer erscheint einmal, auch bei mehreren Bewertungen. Bestehende IDs und sichtbare Namen werden bei der Analyse nicht geändert.

Danach können ausgewählte Benutzer einzeln oder als geprüfte Auswahl neue Vorschläge erhalten. Die Auswahl bleibt an den aktiven Filter gebunden. Vor dem Speichern zeigt die UI alten/neuen Benutzernamen, optional geänderten Anzeigenamen und alle betroffenen Bewertungen. Die verbindliche Vergabe erfolgt erst beim Speichern; inzwischen vergebene Vorschläge werden durch neue Alternativen ersetzt.

Altbestandserkennung wird nicht als Beweis einer falschen Identität dargestellt. Familienhäufigkeit ist ein Qualitätsmerkmal. Bereits veröffentlichte Benutzer werden nicht automatisch umbenannt. Manuell freigegebene Änderungen bleiben bei späteren Scheduler-/AI-Läufen erhalten. Rücknahme einer Namensänderung muss die historische Reservierung und eventuell zwischenzeitliche Änderungen prüfen.

### 6.11 Umsetzung in überprüfbaren Teilpaketen

| Teilpaket | Inhalt | Ergebnis vor dem nächsten Schritt |
|---|---|---|
| G1: Messung und Definition | Baseline, Abdeckung, eindeutiger Namensraum, Normalisierung, Metriken. | Alte Wiederholungen und verfügbare Daten sind nachvollziehbar. |
| G2: Katalog und Merkmale | Größerer Wortvorrat, erlaubte Kombinationen, kanonische Basis-/Familienkennungen. | Tatsächlich nutzbare Kapazität samt Stichprobenqualität belegt. |
| G3: Auswahl und Vergabe | Historie, begrenzte Kandidatensuche, Ähnlichkeitsprüfung, Reservierung, Konkurrenzschutz. | Einheitlicher Dienst funktioniert für Anlage und Materialisierung. |
| G4: Vergleich und Kalibrierung | Mehrfachläufe, vorbefüllte Bestände, Fehlalarme, Ausschöpfung und Laufzeit. | Zielwerte erreicht oder Parameter/Katalog vor Einführung angepasst. |
| G5: Vorschau und Bestandsansicht | Kennzahlen, Gründe, Filter, wenige verständliche Einstellungen. | Unterschiede sind im Admin prüfbar, ohne reale Profile zu erzeugen. |
| G6: Einführung und Korrekturen | Zunächst Test-/Stagingbestand; danach neue Vergaben überwachen, ausgewählte alte Namen über den Editor ändern. | Herkunft und manuelle Korrekturen bleiben nachvollziehbar. |

Die Regeln werden versioniert. Eine Rückkehr zu einer vorherigen Auswahlkonfiguration darf bestehende Reservierungen, Unique-Regeln oder manuelle Korrekturen nicht rückgängig machen. Bei Ausfällen oder zu hoher Ausschöpfung werden neue Vergaben sichtbar angehalten beziehungsweise wiederholt; bereits bestehende Benutzer bleiben unverändert.

### 6.12 Warum diese Kombination die Wiederholungen reduziert

Der größere Katalog liefert tatsächlich andere Wörter. Die Basissperre verhindert, dass derselbe Name durch andere Endungen erneut erscheint. Die Familiengewichtung verhindert Serien wie Wald/Wald/Wald oder Feder/Feder/Feder. Der Zeichenvergleich erkennt nahe Schreibvarianten. Die Nutzungshistorie sorgt dafür, dass diese Regeln über mehrere Planläufe und parallele Jobs hinweg wirken. Die Messung zeigt anschließend, ob die wahrnehmbare Vielfalt gestiegen ist und welcher Preis dafür bei Laufzeit oder Fehlversuchen entsteht.

Die Zusage lautet damit **„ähnliche Namen deutlich seltener und nachvollziehbar kontrollieren“**. Eine Garantie, dass nie zwei Menschen zwei Namen als ähnlich empfinden, ist weder messbar noch vorgesehen.

## 7. Umsetzungspakete und Abnahme

| Reihenfolge | Paket | Fertig, wenn … |
|---|---|---|
| 1 | Bestandsmessung, Schema-/Feldmapping und Namensvertrag | Öffentliche Anzeigequelle und Profilzuordnung sind eindeutig; Konfliktliste und Generatorbaseline liegen vor. |
| 2 | Suche, Filter, Benutzeransicht und gefilterte Auswahl | Vor-/Nachname und Benutzername zuverlässig gefunden werden; Auswahl und Trefferzahl exakt denselben Filterumfang verwenden. |
| 3 | Revisionen, Rechte und Base-Update-Service | Bestehende Bewertung und bestehender Benutzer wiederholbar, zugeordnet und konfliktfest aktualisiert werden. |
| 4 | Editor für veröffentlichte Bewertungen und Benutzer | Name und Benutzername unabhängig speicherbar sind; Vorschau, Auswirkungen und Fehlerzustände stimmen; dieselben IDs erhalten bleiben. |
| 5 | Generator mit größerer Vielfalt | Die vereinbarten Messziele, Eindeutigkeits- und Konkurrenzprüfungen erfüllt sind. |
| 6 | Gezielte Bestandskorrektur und kontrollierte Einführung | Einzelne ausgewählte Änderungen samt Historie und Wiederherstellung geprüft sind, danach größere Mengen nachvollziehbar verarbeitet werden können. |

Pakete 2 und 5 können nach Paket 1 parallel umgesetzt werden. Paket 4 setzt Paket 3 voraus. Paket 5 ist mit dieser Revision in die Teilpakete G1–G6 vertieft. Die frühere Gesamtschätzung von 6–9 Entwicklungstagen wird dadurch ersetzt: Eine belastbare neue Schätzung erfolgt nach Bestandsmessung und Katalogprobe, insbesondere anhand tatsächlich verfügbarer Basen und notwendiger Base-Schemaarbeiten. Bestehende UI-/Framework-Struktur beibehalten, kein Frameworkwechsel erforderlich.

### Wesentliche Prüffälle

- Suche nach vollständigem Namen, Benutzername, numerischem Benutzername und exakter ID; Kombination mit Datum/Versicherer/Status und leerem Ergebnis.
- „Alle Treffer“ bei mehreren Seiten, Filterwechsel und zwischenzeitlich geänderter Bearbeitbarkeit.
- Nur Anzeigename ändern, nur Benutzername ändern, Vor-/Nachname ändern: keine ungewollte gegenseitige Überschreibung; richtige öffentliche Ausgabe.
- Bereits veröffentlichte Bewertung ändern: bestehende Base-IDs, Veröffentlichungszeit und nicht betroffene Felder bleiben erhalten.
- Gemeinsamer Benutzer mit mehreren Bewertungen, fehlendes Profil, fremde Base-ID, entfernte Base-Verknüpfung und nicht unterstütztes Zielfeld.
- Eindeutigkeitskonflikte, parallele Vergabe, Legacy-Übernahme, veraltetes Formular und unberechtigte direkte Livewire-Aktion.
- Base-Ausfall, wiederholter Auftrag, verspäteter älterer Auftrag, Absturz nach Base-Commit und anschließender erfolgreicher Wiederholungsversuch.
- Änderungen bleiben bei späteren Scheduler-/AI-Läufen erhalten; bestehende Planung und Zurückruflogik funktionieren weiter.

## 8. Prüfung dieser Analyse und Grenzen

- Untersucht wurden lokale Quelltexte, Migrationen, Tests, Projektentscheidungen und die öffentliche Base-Anzeigelogik.
- Installiert laut Lockfile: Laravel 10.50.2, Livewire 3.7.11, Activitylog 4.9.0. Testlauf mit PHP 8.2.12 und PHPUnit 10.5.63.
- In der Erstanalyse ausgeführt: `php -d memory_limit=256M vendor/phpunit/phpunit/phpunit --filter SyntheticIdentityGeneratorTest --do-not-cache-result` — **11 Tests, 13.836 Assertions erfolgreich**.
- In der Erstanalyse zusätzlich: isolierte Generator-Stichproben mit 300, 1.000 und 5.000 Namen, ohne Datenbankzugriff.
- Revision 2: Plan und aktueller Generator erneut gelesen, Kapazitätsrechnung geprüft und Entwurf unabhängig auf Vergabe-/UI-Grenzen bewertet. Keine neue Generatorsimulation und kein erneuter Anwendungstest, da ausschließlich Dokumentation geändert wurde. Der neue Generator ist noch nicht implementiert; seine Zielwerte sind noch nicht verifiziert.
- Keine Live-Bestands-/Schemaprüfung, kein Browseraudit und kein Ende-zu-Ende-Test gegen veröffentlichte Daten. Live-Häufigkeit und Deploymentstand sind daher nicht bestätigt.
- Keine Anwendungsdateien oder Bestandsdaten geändert. Keine neuen Testdateien oder temporären Prüfartefakte verbleiben; gespeichert werden dieser Plan und Koordinationsnotizen.

## Technische Referenzen für die Umsetzung

- [Livewire 3: URL-Parameter](https://livewire.laravel.com/docs/3.x/url) — bestehende Filterpersistenz erweitern.
- [Livewire 3: Pagination](https://livewire.laravel.com/docs/3.x/pagination) — Seite bei Filteränderungen zurücksetzen.
- [Laravel 10: Validation](https://laravel.com/docs/10.x/validation) — serverseitige Validierung und Unique-Prüfung mit vertrauenswürdig ermittelter eigener ID.
- [PHP: Levenshtein-Abstand](https://www.php.net/manual/en/function.levenshtein.php) — begrenzter Zeichenvergleich als Ergänzung der Basis-/Familienmerkmale; die vorgeschlagenen Schwellenwerte sind projektspezifisch.
- [Laravel 10: Pessimistic Locking](https://laravel.com/docs/10.x/queries#pessimistic-locking) — kurze finale Vergabeprüfung mit gesperrter Kontrollzeile innerhalb einer lokalen Transaktion.

Die Empfehlungen beruhen vorrangig auf den oben genannten Projektdateien. Die Framework-Dokumentation wurde für die installierten Hauptversionen am 16.11.2026 geprüft.
