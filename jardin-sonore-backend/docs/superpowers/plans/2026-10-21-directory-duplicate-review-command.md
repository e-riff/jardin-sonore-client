# Directory Duplicate Review Command Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a read-only Symfony command that lists organization pairs with exact name, address, email, or phone signals for human review.

**Architecture:** A DBAL lookup generates candidate pairs and matching signals. A console command prints IDs, names, locations, and signals; no persistence or merge operation is available.

**Tech Stack:** PHP, Symfony Console, Doctrine DBAL, PHPUnit.

**Spec:** User approved a deduplication command for internal use; today’s production review remains manually validated with the user.

## Global Constraints

- Read-only by default and always; do not merge or edit organizations.
- Reuse existing Symfony and DBAL patterns; add no dependency.
- Include stable organization IDs and human-readable matching evidence.

## Review Focus

- Multiple branches share a name but are distinct: list as candidates with address evidence visible.
- Shared organization phone/email may be generic: label the signal and leave decisions to a human.
- Organizations may have several addresses: include active address and commune data in the review output.
- Candidate volume may grow: cap output and allow a configurable limit.
- Empty result sets should be explicit and successful.

---

### Task 1: Read-only duplicate candidate lookup

**Files:**
- Create: `src/Application/Directory/DirectoryDuplicateCandidate.php`
- Create: `src/Application/Directory/DirectoryDuplicateCandidateLookupInterface.php`
- Create: `src/Infrastructure/Directory/DoctrineDirectoryDuplicateCandidateLookup.php`
- Test: `tests/Integration/Infrastructure/Directory/DoctrineDirectoryDuplicateCandidateLookupTest.php`

**Interfaces:**
- Produces `findCandidates(int $limit): list<DirectoryDuplicateCandidate>` with paired organization IDs/names, locations, and matching signals.

- [ ] Write a failing SQLite-backed test proving exact normalized names surface as a candidate and an unrelated organization does not.
- [ ] Run the focused test and confirm the expected missing-class failure.
- [ ] Implement the lookup with exact-name, active-address, email, and phone evidence; keep it read-only.
- [ ] Run the focused test and confirm it passes.

### Task 2: Console review command and import ambiguity guard

**Files:**
- Create: `src/Infrastructure/Command/FindDirectoryDuplicatesCommand.php`
- Modify: `src/Application/Directory/DirectoryEstablishmentMatcher.php`
- Modify: `src/Infrastructure/Command/ImportDirectoryEstablishmentsCommand.php`
- Test: `tests/Unit/Application/Directory/DirectoryEstablishmentMatcherTest.php`
- Test: `tests/Unit/Infrastructure/Command/FindDirectoryDuplicatesCommandTest.php`

**Interfaces:**
- Command: `app:directory:find-duplicates`, option `--limit` (default 100), no write path.
- Import auto-match: require the top candidate to score above 90 and lead the second candidate by at least 15 points.

- [ ] Add failing tests for outputting a candidate row and refusing near-tied import candidates.
- [ ] Run focused tests and confirm they fail for the missing behavior.
- [ ] Implement the command and minimum-score-gap guard.
- [ ] Run focused tests and confirm they pass.
- [ ] Run backend lint and the complete PHPUnit suite.

