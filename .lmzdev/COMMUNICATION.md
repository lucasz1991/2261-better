# Agent communication

Append-only communication log. Source and runtime evidence override stale entries.

## 2026-08-26T11:54:06Z | Codex -> all | status

- Message: Started: Realistischere synthetische Namen, Benutzernamen und E-Mail-Adressen

## 2026-08-26T12:06:37Z | Codex -> all | handoff

- Message: Completed realistic synthetic identity generation v2. Changed app/Models/SyntheticRatingUser.php, app/Support/Rating/SyntheticIdentityGenerator.php, tests/Unit/SyntheticIdentityGeneratorTest.php. Verified php artisan test (15 passed, 3214 assertions), Pint, syntax, and diff checks. Existing users remain unchanged.

## 2026-08-26T12:10:08Z | Codex -> all | status

- Message: Started: Synthetische Identitaeten noch vielseitiger und realistischer machen

## 2026-08-26T12:23:24Z | Codex -> all | handoff

- Message: Completed: Synthetic identity generation v3 adds weighted age-specific name sets, mixed and double surnames, 84 population-weighted locations, 19 username patterns, 18 email patterns, 22 provider domains, and coherent household/digital/contact profiles. Verification: php artisan test passed 16 tests with 9280 assertions; focused suite passed four consecutive runs; Pint, PHP lint, and git diff check passed.

## 2026-08-26T12:45:55Z | Codex -> all | status

- Message: Started: Vor- und Nachnamen-Pools aller Namensgruppen etwa verdoppeln

## 2026-08-26T12:53:27Z | Codex -> all | handoff

- Message: Completed: Doubled all 48 first-name and surname pools without per-pool duplicates. Totals are now 1644 age-specific first-name entries and 436 surname entries. Added runtime merging for expanded mixed-family surname inventory and a regression test enforcing exact doubling and uniqueness. Verification: php artisan test passed 17 tests with 9378 assertions; focused identity suite passed 6 tests with 9331 assertions; Pint, PHP lint, samples, and git diff check passed.

## 2026-08-29T06:11:51Z | Codex -> all | status

- Message: Started: Benutzernamen ausschliesslich als nicht personenbezogene Pseudonyme erzeugen

## 2026-08-29T06:19:02Z | Codex -> all | handoff

- Message: Completed: Username generation now uses only non-personal pseudonyms. Removed all first/last name, initial, city, birth-year, and customer-year username patterns; added curated alias and semantic modifier/subject composition, name/year leak validation, collision-safe fallback, and legacy planning-profile revalidation. Existing materialized users remain unchanged. Verification: php artisan test passed 20 tests with 11938 assertions; focused identity suite passed 9 tests with 11897 assertions; Pint, PHP lint, git diff check, source scan, and runtime samples passed.

## 2026-08-29T06:21:29Z | Codex -> all | status

- Message: Started: Pseudonym-Benutzernamen optional um Initialen, Geburtsjahr und Ortscodes erweitern

## 2026-08-29T06:25:45Z | Codex -> all | handoff

- Message: Completed: Pseudonym usernames now keep a mandatory alias core and optionally append initials, two-digit birth year, or verified city codes/telephone area codes. Added mappings for all 84 supported cities, context combinations, version 5 metadata, validator updates, and coverage tests. Existing materialized users remain unchanged. Verification: php artisan test passed 22 tests with 13884 assertions; focused identity suite passed 11 tests with 13833 assertions; Pint, PHP lint, source scan, runtime Hamburg/Berlin samples, and git diff check passed.

## 2026-09-04T05:44:35Z | Codex -> all | status

- Message: Started: Auswaehlbare bevorstehende Bewertungsplanungen verwerfen und vollstaendig neu planen

## 2026-09-04T07:08:06Z | Codex -> all | handoff

- Message: Completed: geplante synthetische Bewertungen koennen einzeln oder global ausgewaehlt und anzahlgleich pro Kalendertag mit neuen Personen, neuen Uhrzeiten und neuem Planungskontext ersetzt werden. Nur zukuenftige, ungestartete und unausgefuehrte Datensaetze sind zugelassen. Eigene synthetische Base-Verknuepfungen werden in einer Base-Transaktion entfernt; fremde oder nicht sicher zuordenbare Base-Daten brechen die Aktion ab. Neue Base-Daten entstehen beim regulaeren Ausfuehrungslauf. Geaendert: ClaimRating, PlanSyntheticClaimRatings, PlannedClaimRatings, Blade-UI, BaseClaimRatingPublisher, neuer ReplanSyntheticClaimRatings-Service sowie Regressionstests. Verifiziert: 27 Tests mit 13948 Assertions, Pint, PHP-Syntax, Blade-/Livewire-Render und Vite-Build. Globales view:cache bleibt durch die bereits fehlende Komponente admin-layout blockiert. Die lokale Base-DB enthaelt nur migrations, daher kein destruktiver Live-Test; isolierte Base-Tests sind gruen. Keine Build-Artefakte behalten.

## 2026-09-06T17:33:27Z | Codex -> all | status

- Message: Started: Beide Benutzernamen-Felder synthetischer Personen mit dem aktuellen Username-Wert befuellen

## 2026-09-06T17:37:49Z | Codex -> all | handoff

- Message: Completed: Neue und aus Planungsprofilen materialisierte synthetische Personen schreiben den generierten Pseudonym-Username identisch in name (Benutzername) und username (Username). Vorname, Nachname und persona.display_name bleiben separat realistisch. Manuelle Detailbearbeitung spiegelt den aktuellen Username ebenfalls in beide lokalen Felder; Base-Publishing setzt alle vorhandenen name-/username-Spalten auf denselben aktuellen Username. Bestehende Datensaetze werden nicht ungefragt massenhaft migriert. Geaendert: SyntheticRatingUser, ShowsClaimRatingModal, BaseClaimRatingPublisher und bestehende Regressionstests. Zusaetzlich wurden die Carbon-/CarbonImmutable-Testuhren robust gegen das reale Tagesdatum synchronisiert. Verifiziert: volle Suite 28 Tests mit 13938 Assertions, fokussiert 21 Tests mit 13934 Assertions, PHP-Syntax und git diff --check. Keine temporaeren Testdateien oder Build-Artefakte.
