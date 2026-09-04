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
