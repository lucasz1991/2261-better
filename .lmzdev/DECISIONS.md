# Decisions

Record durable decisions with date, context, decision, and consequences.

## 2026-08-26 | Synthetic identity generation v2

- Visible names are selected from age-cohort pools; birth year, occupation, household, and customer-since year remain internally consistent.
- Username and email local parts use separate weighted real-world patterns. Random token suffixes are reserved for repeated collisions.
- `name` stores the display name, while `username` remains a separate login-style identifier.
- Test provenance remains internal JSON metadata and is not embedded in visible names, usernames, or email local parts.
- Existing records are not rewritten automatically.

## 2026-08-26 | Synthetic identity generation v3

- Name selection uses weighted, age-specific name sets while allowing a controlled share of mixed first-name/surname combinations and double surnames.
- Regions are selected by state population weight before choosing one of more than 70 cities, instead of weighting every listed city equally.
- Household size, child count, marital status, digital affinity, contact channel, device, availability, insurance experience, and writing style are generated as coherent profiles rather than independent random values.
- Username and email generation includes aliases, initials, birth-year variants, compact forms, and multiple separators; collision suffixes remain a fallback instead of a permanent fingerprint.
- Existing records remain unchanged and keep their original identity version.

## 2026-08-26 | Name pool expansion

- Every general and additional age-specific first-name pool receives a separate expansion with exactly as many unique entries as its original source pool.
- Every surname pool is expanded by the same rule; mixed-family selection uses the combined expanded surname inventory.
- Expansions remain separate constants so original data, added data, exact growth, and duplicate checks stay auditable.

## 2026-08-29 | Non-personal username pseudonyms

- Username generation is separated from email-local-part generation and no longer receives name-, initial-, city-, birth-year-, or customer-year-based patterns.
- Usernames combine curated aliases with semantically compatible modifier/subject pools and optional non-personal numbers.
- A final validator rejects accidental first-name or surname substrings and personal year values, including collision and fallback paths.
- Legacy planning profiles are revalidated before materialization; existing linked synthetic users are not renamed automatically.

## 2026-08-29 | Pseudonym usernames with optional context

- Every generated username retains a recognized pseudonym base; initials, location, or birth-year data may only be appended as context.
- Weighted context variants include initials, two-digit birth year, location code, arbitrary non-personal numbers, and combinations of these values.
- All 84 supported cities map to a common vehicle-style abbreviation and a real telephone area code, including `hh/040` for Hamburg and `b/030` for Berlin.
- Full first names and surnames remain forbidden, while the validator now accepts allowed context suffixes.

## 2026-09-04 | Exact replacement of future synthetic rating plans

- Replanning is restricted to future synthetic ratings that have neither started nor executed; `scheduled`, prepared `rated`, and retryable `failed` records are eligible even when they already carry a Base link.
- Each selected eligible record is replaced exactly once on the same calendar day with a fresh synthetic user, a different visible minute, and a fully rerolled provider/type/subtype/score context.
- Replacement planning uses an exact-count mode that excludes existing and discarded schedule minutes and filters provider-first weighting to providers with at least one active weighted type/subtype pair.
- All replacement records are created and validated before old local records are soft-deleted, so planning failures roll back the local transaction without losing the existing plan.
- Existing Base rating/user records are deleted only when ownership metadata proves that they belong to the local synthetic `2261-better` record. Foreign or ambiguous links abort the operation.
- Base removals for one replacement batch share one Base transaction. New Base users and ratings are not created immediately; the normal execution/publish flow creates them when each replacement reaches its new scheduled time.

## 2026-09-06 | Identical account-name fields for synthetic people

- The generated pseudonym username is the canonical account name for synthetic people and is written identically to local `name` and `username`.
- Real first and last names remain in their dedicated columns and in the persona metadata; `publicProfile.display_name` continues to derive from those name parts.
- Saving a planned person's details treats the Username input as authoritative and mirrors it into the Benutzername field.
- Base-user creation independently mirrors the same current username into every available `name` and `username` column, including legacy local records whose two fields still differ.
- Existing materialized records are not bulk-migrated; this avoids changing already linked or executed test data without an explicit migration request.
