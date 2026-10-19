# MCP Commercial Follow-up Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let Mistral read a commercial dossier's context and, after separate user confirmations, add a mail summary and a dated reminder to that dossier.

**Architecture:** Keep MCP parsing and bounded argument validation in application registries. Resolve mail identity through the existing IMAP adapter, then perform commercial writes in a dedicated Doctrine adapter using the current project, event and action entities. Unique source keys and a project row lock make retries idempotent. A server flag keeps writes disabled until Mistral confirmation is configured.

**Tech Stack:** PHP 8.4+, Symfony, Doctrine ORM/DBAL, Webklex PHP-IMAP, PHPUnit 12, PHPStan, PHP-CS-Fixer, MySQL/MariaDB in Docker and cPanel.

**Spec:** [MCP commercial context, summaries and reminders](../specs/2026-10-19-assistant-mcp-commercial-followup-design.md).

## Global Constraints

- Keep `declare(strict_types=1);` in new PHP files and use typed constants; import native PHP classes.
- Reuse the current MCP bearer and cPanel IMAP configuration. Never log secrets, mail bodies or full bearer headers.
- First version reads INBOX only. Neither write tool sends mail, modifies IMAP flags, or changes sessions.
- Each write tool needs its own Mistral confirmation and a backend write flag that defaults to false.
- Keep source identity unique per dossier and mail separately for summaries and reminders. Preserve the existing backoffice edit and action workflows.
- Use the repository's Docker PHP runner for tests that need MySQL. Generate and review the migration; ask the user before applying it locally.
- Do not commit without the user's explicit agreement. Before any production deployment, follow repository rules: commit, tag, push branch, push tag, then deploy.

## Review Focus

- An expired or forged `mailReference` must fail before any database write (Task 2 and Task 4 tests).
- Concurrent calls for the same dossier and mail must produce one summary or reminder (Task 3 unique indexes and Task 4 integration tests).
- A closed dossier or invalid reminder date must create no action (Task 4 and Task 5 tests).
- A denied or disabled write tool must leave the database untouched (Task 5 tests and Mistral acceptance).
- A mail summary edited in the backoffice must retain its source metadata and source key (Task 6 test).

---

### Task 1: Read bounded dossier context

**Files:**
- Create: `jardin-sonore-backend/src/Application/Mcp/ProjectContextReader.php` — interface for `get(int $projectId): array`.
- Create: `jardin-sonore-backend/src/Infrastructure/Doctrine/Commercial/DoctrineProjectContextReader.php` — DTO-like array projection from project, recent relevant events and open actions.
- Modify: `jardin-sonore-backend/src/Application/Mcp/ReadToolRegistry.php` — add `get_project_context` definition, positive `projectId` validation and dispatch.
- Modify: `jardin-sonore-backend/config/services.php` — bind `ProjectContextReader` to the Doctrine adapter.
- Modify: `jardin-sonore-backend/tests/Unit/Application/Mcp/ReadToolRegistryTest.php` — registry contract.
- Create: `jardin-sonore-backend/tests/Integration/Infrastructure/Commercial/DoctrineProjectContextReaderTest.php` — real Doctrine query behavior.

**Interfaces:** `ProjectContextReader::get(int $projectId): array` returns `id`, `title`, `status`, `organization`, `primaryContact`, `recentEvents` (at most 10), `openActions` (at most 20) and `url`; unknown IDs return a bounded tool error, not an unrelated dossier.

- [ ] Write tests for a known dossier with more than 10 events and 20 actions; assert bounds, recent-event order, open actions, dates, URL and no document/mail body. Add unknown and malformed ID cases.
- [ ] Run the focused tests in Docker and confirm they fail for the missing tool/adapter.
- [ ] Implement the reader and registry wiring with indexed Doctrine queries and bounded scalar projections.
- [ ] Re-run the focused tests; run `composer stan` and `composer cs-check`.

### Task 2: Resolve and validate a source mail without changing its flags

**Files:**
- Create: `jardin-sonore-backend/src/Application/Mcp/MailSource.php` — readonly value with `reference`, `sourceKey`, `messageId`, `subject`, `from`, `date`.
- Modify: `jardin-sonore-backend/src/Application/Mcp/MailReader.php` — add `source(string $mailReference): MailSource`.
- Modify: `jardin-sonore-backend/src/Infrastructure/Mail/ImapMailReader.php` — reuse reference decoding and UIDVALIDITY validation, fetch only headers with `leaveUnread()`, compute source key using normalized account and valid Message-ID or INBOX/UIDVALIDITY/UID fallback.
- Modify: `jardin-sonore-backend/tests/Unit/Infrastructure/Mail/ImapMailReaderTest.php` — valid source, missing Message-ID fallback, stale UIDVALIDITY, missing UID and no flag changes.

**Interfaces:** `MailReader::source(string $mailReference): MailSource`; `MailSource::sourceKey` is a 64-character SHA-256 hex string. Inject the configured IMAP username with `#[Autowire('%app.mcp.imap_username%')]`; do not expose it separately through MCP.

- [ ] Write focused failing tests for stable identity across repeated calls, safe fallback and invalid references. Assert header-only fetch and `leaveUnread()`.
- [ ] Run `ImapMailReaderTest.php` and confirm the new cases fail.
- [ ] Add the value object and implementation. Keep `readThread` behavior intact.
- [ ] Re-run the focused tests and static analysis.

### Task 3: Persist mail source keys with uniqueness

**Files:**
- Modify: `jardin-sonore-backend/src/Infrastructure/Doctrine/Entity/CommercialEventEntity.php` and its mapping file — nullable `mailSourceKey` with a named unique index on `(project_id, mail_source_key)`.
- Modify: `jardin-sonore-backend/src/Infrastructure/Doctrine/Entity/CommercialActionEntity.php` and its mapping file — equivalent nullable source key and unique index.
- Create: a single Doctrine migration generated by `make:migration`, containing only the two nullable columns and two unique indexes; revise its description, `up()` and `down()`.
- Modify: `jardin-sonore-backend/tests/Integration/Infrastructure/Commercial/CommercialPersistenceTest.php` — historical null keys and uniqueness for populated keys.

**Interfaces:** Existing records retain `NULL` source keys. Application setters/getters accept only a 64-character source hash. The database permits one summary and one reminder for the same `(project_id, source_key)` because each table has its own index.

- [ ] Write a persistence test that stores old-style events/actions with null keys and mail-linked ones with keys; assert uniqueness is enforced for a repeated key in one dossier.
- [ ] Add entity fields and mappings, then run `php bin/console make:migration` using the Docker PHP service. Inspect the generated SQL and remove unrelated changes.
- [ ] Ask the user before executing the migration on local development and test databases, as required by repository instructions. After approval, run it, inspect `down()`, then run `php bin/console doctrine:schema:validate` and `php bin/console doctrine:schema:update --dump-sql` in Docker; the latter must propose no unrelated SQL.
- [ ] Re-run the focused persistence test.

### Task 4: Write a summary or reminder idempotently

**Files:**
- Create: `jardin-sonore-backend/src/Application/Mcp/ProjectFollowupWriter.php` — `recordSummary(int $projectId, MailSource $source, string $summary): array` and `createReminder(int $projectId, MailSource $source, string $title, DateTimeImmutable $dueOn): array`.
- Create: `jardin-sonore-backend/src/Infrastructure/Doctrine/Commercial/DoctrineProjectFollowupWriter.php` — transaction, project row lock, duplicate lookup and entity creation.
- Modify: `jardin-sonore-backend/config/services.php` — bind the writer interface.
- Create: `jardin-sonore-backend/tests/Integration/Infrastructure/Commercial/DoctrineProjectFollowupWriterTest.php` — committed behavior and retries.

**Interfaces:** Each method returns `projectId`, created/existing record ID, `alreadyExists` and dossier URL. Summary stores a `note` event with source date, sender, subject, message ID and reference in string metadata; reminder stores an open `CommercialActionEntity` plus its normal `action_created` event. Calls use `EntityManagerInterface::wrapInTransaction()` and a `Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE` lock on the dossier row before duplicate lookup and flush.

- [ ] Write tests for a created note/action, repeated identical calls, same source in another dossier, closed dossier and invalid project. Verify a second service instance sharing the database returns the existing row and the unique indexes reject duplicate keys.
- [ ] Run the focused test and confirm the new writer is missing.
- [ ] Implement the transaction and entity operations; map unique-key conflicts to a safe existing-record or conflict result without leaking SQL.
- [ ] Re-run the integration test, `composer stan` and `composer cs-check`.

### Task 5: Expose two confirmed MCP write tools behind a default-off flag

**Files:**
- Create: `jardin-sonore-backend/src/Application/Mcp/WriteToolRegistry.php` — tool definitions, bounded argument validation, source resolution, disabled-state guard and writer dispatch.
- Modify: `jardin-sonore-backend/src/Application/Mcp/McpProtocol.php` — merge read/write definitions and dispatch calls to the correct registry.
- Modify: `jardin-sonore-backend/config/parameters.yaml.dist` and `jardin-sonore-backend/.env` — `app.mcp.write_enabled: '%env(bool:MCP_WRITE_ENABLED)%'` and `MCP_WRITE_ENABLED=0`.
- Modify: `jardin-sonore-backend/tests/Functional/Application/Controller/McpControllerTest.php` and `jardin-sonore-backend/tests/Unit/Application/Mcp/ReadToolRegistryTest.php`.
- Create: `jardin-sonore-backend/tests/Unit/Application/Mcp/WriteToolRegistryTest.php`.

**Interfaces:** `record_mail_summary(projectId, mailReference, summary)` and `create_project_reminder(projectId, mailReference, title, dueOn)` use `readOnlyHint=false`, `destructiveHint=false`; both are listed while disabled but return a tool error without reading or writing. With the flag enabled, malformed dates, oversized text, missing source and unknown tool names return bounded errors. `get_project_context` remains read-only.

- [ ] Write failing tests for `tools/list`, default-off refusal without writer calls, each successful dispatch, invalid IDs/dates/lengths, a stale mail reference that stops before the writer, and unknown tool names.
- [ ] Run the unit and functional MCP tests; confirm failures.
- [ ] Add the write registry, parameter and dispatch with no change to bearer authentication or existing read tools.
- [ ] Re-run those tests and static/style checks. Inspect Mistral's new tool permissions before enabling production writes.

### Task 6: Show source attribution and complete the acceptance checks

**Files:**
- Modify: `jardin-sonore-backend/templates/commercial/project/_timeline.html.twig` — visible source line for mail-linked notes, while preserving existing edit controls.
- Modify: `jardin-sonore-backend/translations/commercial.fr.yaml` — source label and safe missing-field fallback.
- Modify: `jardin-sonore-backend/tests/Functional/Application/Controller/CommercialProjectControllerTest.php` — rendered source, edit preserving metadata/key and no mail body.
- Modify: `ROADMAP.md` — deployed state and next manual checks only after acceptance.

**Interfaces:** A mail-linked note displays date, subject and sender as provenance. Notes without source metadata render as before. Editing summary text preserves the source fields and unique key.

- [ ] Write a failing functional test for a mail-linked note and edit, plus an old-style note.
- [ ] Implement the template and translation changes; re-run the functional test.
- [ ] Run the complete backend PHPUnit suite in Docker with `memory_limit=768M`, `composer stan`, `composer cs-check`, and `git diff --check`. Verify generated migration SQL, reverse SQL and clean schema diff.
- [ ] After the user's approval for the migration and production rollout, commit/tag/push/deploy under the repository rules. Keep writes disabled until Mistral is set to require confirmation for each write tool.
- [ ] In production, verify read context and a controlled mail source without printing content. Have the user deny then approve one summary and one reminder in Mistral, confirm the resulting dossier entries and no duplicates, and check that the source mail stays unread.
