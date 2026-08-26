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
