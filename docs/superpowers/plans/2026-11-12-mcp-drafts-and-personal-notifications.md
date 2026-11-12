# MCP drafts and personal notifications — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Edit manual and MCP drafts without losing attachments, and send deduplicated personal notifications through a separate MCP tool.

**Architecture:** Keep the current IMAP store and legacy entry points. Extend the draft snapshot and add a general editor accepting partial changes; revisions protect the full snapshot. Notifications use a dedicated service, synchronous mail transport and a DBAL receipt store with a database lock for reservations/rate limits, mapped as an infrastructure entity.

**Tech Stack:** PHP 8.4+, Symfony Mailer/Mime, Webklex IMAP, Doctrine ORM/DBAL, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-12-mcp-drafts-and-personal-notifications-design.md` (approved in conversation).

## Global Constraints

- No new dependencies, commit, deployment or real email without authorization.
- Ask before executing the migration. Continue all independent implementation and verification first.
- Only configured Drafts folder; never global IMAP expunge.
- Attachments: 10 maximum, 5 MiB each, 10 MiB total decoded, bounded encoded input.
- Notifications: fixed server recipient; 10 reservations/hour; no body in receipts.

## Review Focus

- External changes to recipients, attachments or reply headers must invalidate the revision.
- Missing/replaced Message-ID must lead to a new explicit search, never a guessed replacement.
- Partial updates must preserve HTML, To/Cc/Bcc, inline attachments and threading.
- Retried/concurrent notification requests must never start a second SMTP attempt.
- SMTP acceptance and uncertain outcomes must not be presented as delivery confirmation.

### Task 1: General draft snapshots, editing and attachments

**Files:** `src/Application/Mcp/{MailDraft,MailDraftAttachment,GeneralMailDraftEditor}.php`, `src/Infrastructure/Mail/{ImapMailDraftStore,DraftMimeBuilder}.php`, draft interfaces and registries; corresponding unit tests.

**Interfaces:** `MailDraftStore::search(string $folder, ?string $query, int $limit): array`; `GeneralMailDraftEditor::search/read/update`; updates take a current Message-ID, revision and partial changes. Legacy standalone editing delegates to the same replacement implementation.

- [x] Add failing tests for manual drafts, partial changes, full revision conflicts, attachment validation/preservation and safe replacement.
- [x] Run focused PHPUnit and confirm expected failures.
- [x] Implement snapshots, bounded IMAP search, MIME builder and general editor.
- [x] Expose search/read/edit tools and attachment creation support; retain old tool names.
- [x] Run focused PHPUnit including registry and MIME tests.

### Task 2: Personal notification receipts and sending

**Files:** dedicated notification service/store contract, DBAL adapter, entity and PHP mapping, generated migration, `.env`, `parameters.yaml.dist`, services aliases and MCP write registry; unit/integration tests.

**Interfaces:** `PersonalNotificationSender::send(array $arguments): array`; receipt reservation atomically returns new/existing/conflict/rate_limited; completion records accepted/uncertain. Use `TransportInterface` synchronously so accepted means SMTP transport acceptance, not queue dispatch.

- [x] Add failing tests for fixed recipient, filtered HTML, deduplication, mismatched retry, disabled tool and uncertain SMTP.
- [x] Run tests to observe expected failures.
- [x] Implement receipt reservation/rate limit and sending, register tool and disabled-by-default configuration.
- [x] Generate/review migration without applying it; test the mapping/SQL against an isolated test schema only when permitted.
- [x] Run focused tests and report migration execution approval separately after remaining work.

### Task 3: Verification and documentation

- [x] Run full PHPUnit (`docker compose exec -T php php -d memory_limit=1G ./bin/phpunit`), style/PHPStan, Symfony container lint.
- [x] Review the complete diff and rectify findings; no commit unless requested.
- [x] Update roadmap and usage documentation with tool contracts, configuration, manual IMAP/Mistral recipe and migration status.

## Execution ledger

- Approved design; user authorized implementation in this session. No automatic commits.
- Workspace: user chose current directory; branch `feat/mcp-drafts-personal-notifications`. No worktree created.
- Ruling: no commits, per project instructions. Progress stays in this ledger until explicit commit authorization.
- Baseline: 591 tests, 3050 assertions; pre-existing CommercialProjectControllerTest failure at line91; 2 deprecations and 4 PHPUnit notices.
- Task 1: implemented; focused editor/IMAP/MIME tests pass. Full snapshots canonicalize MIME line endings and attachment order; real Content-ID is read from MIME part headers.
- Ruling: search returns opaque UIDVALIDITY+UID references so even drafts with missing/duplicate Message-ID can be selected safely. Message-ID lookups fail on ambiguous matches.
- Ruling: reply replacement IDs persist the desired full snapshot and request fingerprint, allowing safe receipt finalization after IMAP deletion succeeded but DB finalization was interrupted. Existing old-format in-progress receipts fail closed and require a new search.
- Task 2: implemented; sender tests pass, receipt integration tests pass against connection-local temporary tables including a competing database lock. No persistent migration or real mail executed.
- Migration: `make:migration` generated `Version20261112080023`; reviewed SQL, up/down dry-runs pass; schema dump only creates `mcp_personal_notification`. Prior data migration `Version20261027160000` remains untouched.
- Ruling: POST/proxy limits raised from10MiB to16MiB so10MiB of attachments fits after base64 encoding. Existing upload_max_filesize remains8MiB. Controller rejects >16MiB authenticated JSON before decoding. Nginx syntax passes; runtime reload remains for delivery.
- Independent review: three Important findings (line endings, true Content-ID, interrupted receipt recovery) reproduced with regression tests, fixed and re-reviewed; no substantive findings remain.
- Personal recipient prepared in ignored `.env.local`; feature remains disabled. Personal address is not placed in tracked documentation/configuration.
- Task 3: verification complete. Full PHPUnit: 618 tests,3153 assertions,1 identical pre-existing failure (CommercialProjectControllerTest line91),2 deprecations,4 notices. Focused final MCP/mail run:88 tests,498 assertions,all pass. PHPStan,CS check,Symfony container lint,Nginx syntax and git diff --check pass. Migration application awaits user approval.


## Changed files and verification record

- Draft application: `src/Application/Mcp/GeneralMailDraftEditor.php`,
  `MailDraft.php`, `MailDraftAttachment.php`, `MailDraftStore.php`,
  `CreateStandaloneMailDraft.php`, `EditStandaloneMailDraft.php`,
  `StandaloneMailDraftWriter.php`, `UpdateMailDraft.php`, `ReadToolRegistry.php`,
  `WriteToolRegistry.php`.
- IMAP/MIME infrastructure: `src/Infrastructure/Mail/ImapMailDraftStore.php`,
  `DraftMessageMapper.php`, `DraftMimeBuilder.php`.
- Personal notification service/contract: `src/Application/Mcp/PersonalNotificationSender.php`,
  `PersonalNotificationStore.php`; Doctrine adapter `DoctrinePersonalNotificationStore.php`,
  entity `McpPersonalNotificationEntity.php` and corresponding PHP mapping.
- HTTP/configuration: `src/Application/Controller/McpController.php`, `.env`,
  `config/parameters.yaml.dist`, `config/services.php`, `public/.user.ini`,
  root `docker/backend/php/uploads.ini`, `docker/backend/nginx/{default,https}.conf`.
  Ignored `.env.local` contains the fixed personal recipient; it is never staged.
- Migration: `migrations/Version20261112080023.php`; generated and dry-run only.
- Tests: existing standalone/reply/registry/IMAP/controller tests updated;
  new general editor,attachment,sender,MIME and notification-store tests.
- Documentation: `ROADMAP.md`, `docs/mcp-mail-tools.md`, this plan and approved spec.

All backend paths above are relative to `jardin-sonore-backend/` unless marked root.
Executed from repository root:

```sh
git status --short
git switch -c feat/mcp-drafts-personal-notifications
docker compose ps --status running
docker compose exec -T php php -d memory_limit=1G ./bin/phpunit
docker compose exec -T php php -d memory_limit=1G ./bin/phpunit --filter 'Mcp|MailDraft|DraftMime|DraftHtml|DraftAttachment'
docker compose exec -T php vendor/bin/phpstan analyse --memory-limit=1G --no-progress
docker compose exec -T php vendor/bin/php-cs-fixer fix --quiet
docker compose exec -T php vendor/bin/php-cs-fixer fix --dry-run --diff --quiet
docker compose exec -T php php bin/console lint:container
docker compose exec -T nginx nginx -t
docker compose exec -T php php bin/console make:migration --no-interaction
docker compose exec -T php php bin/console doctrine:migrations:status --no-interaction
docker compose exec -T php php bin/console doctrine:schema:update --dump-sql
docker compose exec -T php php bin/console doctrine:migrations:execute 'DoctrineMigrations\Version20261112080023' --up --dry-run --no-interaction
docker compose exec -T php php bin/console doctrine:migrations:execute 'DoctrineMigrations\Version20261112080023' --down --dry-run --no-interaction
git diff --check
```

Focused per-file PHPUnit runs were also used for RED/GREEN cycles and regressions.
No frontend files changed; no Next server or frontend build was needed.
No migration was applied; no commit,push,deploy or real email was performed.


## Delivery authorization and preflight — 12 November

- User authorized local migration, commit, push and deployment ("go, et deploy").
- Local new migration applied alone; schema validation passes. Older Ludobus
  data migration remains untouched locally; it is already applied in production.
- Root cause of baseline test failure confirmed: fixed November session date is
  now past. Stabilized the test fixtures using two future months and checked the
  rendered date attribute; no product/controller/template behavior changed.
- Full suite after stabilization:618 tests,3155 assertions,0 failures,4 PHPUnit
  notices. PHPStan,CS and Composer validation pass.
- Local old production cache still referenced removed EasyAdmin. Moved it aside
  (no deletion), then production asset compilation passed (81 assets).
- Production preflight:PHP8.5.12,76 existing migrations applied,no pending,
  schema synchronized; all3 draft flags enabled. Notification configuration absent.
- Remote main equals local base e52f8e81; integrate by fast-forward, commit and
  push branch/tag before backend deployment.
- Planned deployment tag:`deploy-mcp-drafts-notifications-20261112-01`.
- Production recipient activation and post-deployment read-only checks pending.
