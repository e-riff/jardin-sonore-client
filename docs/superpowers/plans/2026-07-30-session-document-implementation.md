# Session Document Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Produce one current, locally stored PDF per session, with a client-ready HTML reading view and selectively visible sequence media.

**Architecture:** `SessionSequence` owns an immutable-at-import collection of media snapshots. `SessionSummary` owns the document lifecycle and is persisted through the existing Doctrine mapper. The repository schedules `GenerateSessionDocumentMessage` after every save; its Messenger handler renders a shared client-reading projection to PDF, records the result and logs to the dedicated `session_document` channel.

**Tech Stack:** PHP 8.4, Symfony 8.1, Doctrine ORM 3, Messenger, Twig, Monolog, Dompdf, PHPStan, PHP CS Fixer.

## Global Constraints

- Preserve existing sessions by migrating the `sequences` JSON payloads without losing primary URL, secondary URL or image URL.
- A sequence has zero or one featured medium; a featured medium is always visible in the shared session.
- Non-featured media are displayed only when `displayOnSession` is true; private media remain stored but never enter the shared projection or PDF.
- The shared HTML and PDF include material and further exploration, but exclude sequence notes and session general notes.
- All session saves, including ordering and role changes, set the document to pending and dispatch an asynchronous generation message.
- PDF failures do not block session edits and are retryable from the backoffice.
- Generate and store PDFs outside `public/`; use an authorized download route.
- Log scheduling, start, success, failure and retry to Monolog channel `session_document` with `session_uuid`.
- Do not create a commit before the user reviews the complete diff.

---

## File Structure

- `src/Domain/Model/Session/SessionSequenceMedia.php`: validates the snapshot attributes of one linked medium.
- `src/Domain/Model/Session/SessionDocumentStatus.php`: document lifecycle enum.
- `src/Domain/Model/Session/SessionSequence.php`: serializes old and new media payloads and enforces featured-media uniqueness.
- `src/Domain/Model/Session/SessionSummary.php`: tracks document state and exposes transition methods used by repository and handler.
- `src/Application/Session/SessionDocumentView.php` and related views: shared client-ready read projection.
- `src/Application/Session/Message/GenerateSessionDocumentMessage.php` and handler: asynchronous document lifecycle.
- `src/Application/Session/SessionDocumentGeneratorInterface.php`: application contract for PDF creation.
- `src/Infrastructure/Session/DompdfSessionDocumentGenerator.php`: Dompdf adapter and local-file writer.
- `templates/session/document.html.twig` and `templates/session/document.pdf.twig`: HTML and print-specific presentations of the same view.
- `migrations/Version20260730120000.php`: document columns and legacy JSON media transformation.
- `tests/Unit/...` and `tests/Integration/...`: PHPUnit coverage added with Symfony's PHPunit bridge.

### Task 1: Establish test support and the media snapshot domain model

**Files:**
- Modify: `jardin-sonore-backend/composer.json`
- Modify: `jardin-sonore-backend/composer.lock`
- Create: `jardin-sonore-backend/phpunit.xml.dist`
- Create: `jardin-sonore-backend/tests/bootstrap.php`
- Create: `jardin-sonore-backend/tests/Unit/Domain/Model/Session/SessionSequenceMediaTest.php`
- Create: `jardin-sonore-backend/src/Domain/Model/Session/SessionSequenceMedia.php`
- Modify: `jardin-sonore-backend/src/Domain/Model/Session/SessionSequence.php`

**Interfaces:**
- Produces `SessionSequenceMedia::__construct(string $label, MediaResourceType $type, string $url, ?string $imageUrl, bool $featured, bool $displayOnSession)`.
- Produces `SessionSequence::getMedia(): array` and preserves `SessionSequence::fromArray(array $payload): self` compatibility with `primaryUrl`, `secondaryUrl`, and `imageUrl`.

- [ ] **Step 1: Add PHPUnit and write failing media tests**

Add `symfony/phpunit-bridge` to `require-dev`, create `phpunit.xml.dist` with `bootstrap="tests/bootstrap.php"`, and write tests asserting that two featured media throw `InvalidArgumentException`, a featured medium is visible even when constructed with `displayOnSession: false`, and legacy payloads yield a featured primary medium plus visible secondary/image media.

```php
public function testFeaturedMediumIsAlwaysVisible(): void
{
    $medium = new SessionSequenceMedia('Écouter', MediaResourceType::SOUNDTRACK, 'https://example.test/a', null, true, false);

    self::assertTrue($medium->isDisplayedOnSession());
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `cd jardin-sonore-backend && php bin/phpunit tests/Unit/Domain/Model/Session/SessionSequenceMediaTest.php`

Expected: failure because the medium class and PHPUnit configuration do not exist yet.

- [ ] **Step 3: Implement the immutable media value object and sequence migration reader**

Create the readonly `SessionSequenceMedia` object with trimmed non-empty `label` and `url`, nullable normalized image URL, `isFeatured()` and `isDisplayedOnSession()`. Replace the three URL properties of `SessionSequence` with `array $media`; `fromArray()` must read `media` when present, otherwise derive legacy media. `toArray()` writes only `media`. Reject more than one featured entry in the sequence constructor.

- [ ] **Step 4: Run focused tests and static analysis**

Run: `cd jardin-sonore-backend && php bin/phpunit tests/Unit/Domain/Model/Session/SessionSequenceMediaTest.php && composer stan`

Expected: tests pass and PHPStan reports no new issue.

### Task 2: Propagate media through session inputs, views and editor form

**Files:**
- Modify: `jardin-sonore-backend/src/Application/Session/SaveSessionSequenceInput.php`
- Modify: `jardin-sonore-backend/src/Application/Session/SessionSequenceView.php`
- Modify: `jardin-sonore-backend/src/Application/Form/Model/SessionSequenceFormModel.php`
- Modify: `jardin-sonore-backend/src/Application/Form/SessionSequenceType.php`
- Modify: `jardin-sonore-backend/src/Application/Session/AddSessionSequence.php`
- Modify: `jardin-sonore-backend/src/Application/Session/UpdateSessionSequence.php`
- Modify: `jardin-sonore-backend/src/Application/Session/UpdateSessionSequenceRole.php`
- Modify: `jardin-sonore-backend/src/Application/Controller/SessionSummaryController.php`
- Modify: `jardin-sonore-backend/templates/session/sequence_form.html.twig`
- Modify: `jardin-sonore-backend/templates/session/composer_activity_form.html.twig`
- Modify: `jardin-sonore-backend/translations/sessions+intl-icu.fr.yaml`
- Test: `jardin-sonore-backend/tests/Unit/Application/Session/SessionSequenceViewTest.php`

**Interfaces:**
- Consumes `SessionSequenceMedia` from Task 1.
- Produces `SaveSessionSequenceInput::$media` and `SessionSequenceView::$media`, each typed as `list<SessionSequenceMedia>`.

- [ ] **Step 1: Write failing projection/form-mapping tests**

Cover `SessionSequenceView::fromDomain()` and `SessionSequenceFormModel::fromView()` with a featured YouTube medium, one visible link and one private link. Assert all three survive editing while only display logic later filters private media.

- [ ] **Step 2: Run the focused tests to verify they fail**

Run: `cd jardin-sonore-backend && php bin/phpunit tests/Unit/Application/Session/SessionSequenceViewTest.php`

Expected: failure because `media` is not present in the input, view, or form model.

- [ ] **Step 3: Implement collection editing without changing catalogue source models**

Add a nested Symfony collection form with fields `label`, `type`, `url`, `imageUrl`, `featured`, and `displayOnSession`. In the controller, map form rows to `SessionSequenceMedia`; preserve the existing `fromRepertoireItemView`, `fromMediaResourceView`, and `fromSessionRecommendationView` import flows by converting their current primary/secondary/image values into copied session media. Keep the catalogue entities unchanged.

- [ ] **Step 4: Run focused tests and inspect the rendered editor manually**

Run: `cd jardin-sonore-backend && php bin/phpunit tests/Unit/Application/Session/SessionSequenceViewTest.php && composer cs-check`

Expected: tests and style check pass; editor exposes visibility checkboxes and at most one featured choice.

### Task 3: Add durable document state and migrate existing data

**Files:**
- Create: `jardin-sonore-backend/src/Domain/Model/Session/SessionDocumentStatus.php`
- Modify: `jardin-sonore-backend/src/Domain/Model/Session/SessionSummary.php`
- Modify: `jardin-sonore-backend/src/Application/Session/SessionSummaryView.php`
- Modify: `jardin-sonore-backend/src/Infrastructure/Doctrine/Entity/SessionSummaryEntity.php`
- Modify: `jardin-sonore-backend/src/Infrastructure/Doctrine/Mapping/App.Infrastructure.Doctrine.Entity.SessionSummaryEntity.php`
- Modify: `jardin-sonore-backend/src/Infrastructure/Doctrine/Mapper/SessionSummaryMapper.php`
- Create: `jardin-sonore-backend/migrations/Version20260730120000.php`
- Test: `jardin-sonore-backend/tests/Unit/Domain/Model/Session/SessionSummaryDocumentTest.php`

**Interfaces:**
- Produces `SessionDocumentStatus::{PENDING,GENERATING,READY,FAILED}`.
- Produces `SessionSummary::markDocumentPending()`, `markDocumentGenerating()`, `markDocumentReady(string $path)`, and `markDocumentFailed(string $error)`.

- [ ] **Step 1: Write failing lifecycle tests**

Assert that any call to `updateDetails()`, `addSequence()`, `replaceSequence()`, `removeSequence()`, `moveSequenceUp()`, `moveSequenceDown()`, and `reorderSequences()` moves a previously ready document to `PENDING` and clears its current path and error. Assert only a generated file can enter `READY`.

- [ ] **Step 2: Run lifecycle tests to verify they fail**

Run: `cd jardin-sonore-backend && php bin/phpunit tests/Unit/Domain/Model/Session/SessionSummaryDocumentTest.php`

Expected: failure because no document state exists.

- [ ] **Step 3: Implement state transitions, mapping, and the reviewed migration**

Add nullable `document_path`, `document_error`, `document_generated_at`, and non-null `document_status` fields. Generate the migration with Doctrine, then edit it to set existing sessions to `pending` and transform each legacy JSON sequence into the Task 1 `media` array. Its `down()` removes only these document columns; the backward-compatible sequence reader continues to accept the migrated JSON payload.

- [ ] **Step 4: Verify the migration is scoped and tests pass**

Run: `cd jardin-sonore-backend && php bin/console doctrine:migrations:diff --allow-empty-diff && php bin/phpunit tests/Unit/Domain/Model/Session/SessionSummaryDocumentTest.php`

Expected: the diff command prints no pending schema changes; lifecycle tests pass.

### Task 4: Schedule document generation and provide Monolog observability

**Files:**
- Create: `jardin-sonore-backend/src/Application/Session/Message/GenerateSessionDocumentMessage.php`
- Create: `jardin-sonore-backend/src/Application/Session/MessageHandler/GenerateSessionDocumentHandler.php`
- Modify: `jardin-sonore-backend/src/Infrastructure/Doctrine/Repository/SessionSummaryDoctrineRepository.php`
- Modify: `jardin-sonore-backend/config/packages/messenger.php`
- Modify: `jardin-sonore-backend/config/packages/monolog.php`
- Test: `jardin-sonore-backend/tests/Integration/Infrastructure/Doctrine/Repository/SessionSummaryDoctrineRepositoryTest.php`
- Test: `jardin-sonore-backend/tests/Integration/Application/Session/MessageHandler/GenerateSessionDocumentHandlerTest.php`

**Interfaces:**
- Produces `GenerateSessionDocumentMessage::__construct(public readonly string $sessionUuid)` routed to `async`.
- `GenerateSessionDocumentHandler::__invoke(GenerateSessionDocumentMessage $message): void` receives `#[AutowireLogger(channel: 'session_document')] LoggerInterface $sessionDocumentLogger`.

- [ ] **Step 1: Write failing repository and handler tests**

Use Symfony's in-memory transport to assert one message is dispatched after `SessionSummaryDoctrineRepository::save()`. Fake a `SessionDocumentGeneratorInterface` to assert the handler marks the session ready on success, failed on an exception, and writes structured `session_uuid` log context in both paths.

- [ ] **Step 2: Run integration tests to verify they fail**

Run: `cd jardin-sonore-backend && php bin/phpunit tests/Integration/Infrastructure/Doctrine/Repository/SessionSummaryDoctrineRepositoryTest.php tests/Integration/Application/Session/MessageHandler/GenerateSessionDocumentHandlerTest.php`

Expected: failure because no message, handler, or channel exists.

- [ ] **Step 3: Implement after-save dispatch and channel configuration**

Inject `MessageBusInterface` into the repository; after its flush, dispatch the UUID message and log the scheduling event. Route the message to `async`. Add `session_document` to configured Monolog channels and a `rotating_file` handler at `%kernel.logs_dir%/session_document.log`, `info` level, 30 files, for dev/test/prod. The handler must transition to generating before work and log start, success, failure and retries with no private session content.

- [ ] **Step 4: Run integration tests and inspect log routing**

Run: `cd jardin-sonore-backend && php bin/phpunit tests/Integration/Infrastructure/Doctrine/Repository/SessionSummaryDoctrineRepositoryTest.php tests/Integration/Application/Session/MessageHandler/GenerateSessionDocumentHandlerTest.php && composer stan`

Expected: dispatch, lifecycle and failure assertions pass; PHPStan accepts attribute injection.

### Task 5: Render and store the canonical PDF, then expose status and controlled download

**Files:**
- Modify: `jardin-sonore-backend/composer.json`
- Modify: `jardin-sonore-backend/composer.lock`
- Create: `jardin-sonore-backend/src/Application/Session/SessionDocumentGeneratorInterface.php`
- Create: `jardin-sonore-backend/src/Application/Session/SessionDocumentView.php`
- Create: `jardin-sonore-backend/src/Application/Session/SessionDocumentSequenceView.php`
- Create: `jardin-sonore-backend/src/Application/Session/BuildSessionDocumentView.php`
- Create: `jardin-sonore-backend/src/Infrastructure/Session/DompdfSessionDocumentGenerator.php`
- Create: `jardin-sonore-backend/templates/session/document.html.twig`
- Create: `jardin-sonore-backend/templates/session/document.pdf.twig`
- Modify: `jardin-sonore-backend/src/Application/Controller/SessionSummaryController.php`
- Modify: `jardin-sonore-backend/templates/session/show.html.twig`
- Modify: `jardin-sonore-backend/templates/session/edit.html.twig`
- Modify: `jardin-sonore-backend/translations/sessions+intl-icu.fr.yaml`
- Modify: `jardin-sonore-backend/config/services.php`
- Test: `jardin-sonore-backend/tests/Unit/Application/Session/BuildSessionDocumentViewTest.php`
- Test: `jardin-sonore-backend/tests/Integration/Infrastructure/Session/DompdfSessionDocumentGeneratorTest.php`

**Interfaces:**
- Produces `SessionDocumentGeneratorInterface::generate(SessionDocumentView $view): string`, returning the local PDF path.
- Produces `BuildSessionDocumentView::__invoke(SessionSummary $sessionSummary): SessionDocumentView`.

- [ ] **Step 1: Add Dompdf and write failing projection/generator tests**

Require `dompdf/dompdf:^3.1`. Test that the view excludes `generalNotes` and sequence `notes`, includes `materialSummary` and `furtherExploration`, expands lyrics, and exposes only media where `isDisplayedOnSession()` is true. Use a temporary directory in the generator test and assert a non-empty PDF is written outside the public directory.

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd jardin-sonore-backend && php bin/phpunit tests/Unit/Application/Session/BuildSessionDocumentViewTest.php tests/Integration/Infrastructure/Session/DompdfSessionDocumentGeneratorTest.php`

Expected: failure because projection, generator and Dompdf adapter do not exist.

- [ ] **Step 3: Implement shared HTML/PDF projection and local storage**

Create the read-model builder from `SessionSummary`; pass it to both Twig templates. `document.html.twig` keeps lyrics inside `<details>` and renders a featured valid YouTube URL in an iframe with a restrictive title; `document.pdf.twig` emits all lyrics as text and never uses interactive markup. Configure a scalar `session_document_directory` resolved from `var/session-documents`; write atomically through a temporary file then rename. Add a GET route `/{uuid}/document.pdf` that returns only a ready local PDF, otherwise 404, and show status plus retry action in the editor.

- [ ] **Step 4: Run PDF tests and validate document output**

Run: `cd jardin-sonore-backend && php bin/phpunit tests/Unit/Application/Session/BuildSessionDocumentViewTest.php tests/Integration/Infrastructure/Session/DompdfSessionDocumentGeneratorTest.php && composer cs-check`

Expected: tests pass; generated PDF is non-empty and contains the intended client fields only.

### Task 6: Add retry, verify the complete flow, and prepare review evidence

**Files:**
- Modify: `jardin-sonore-backend/src/Application/Controller/SessionSummaryController.php`
- Modify: `jardin-sonore-backend/templates/session/edit.html.twig`
- Modify: `jardin-sonore-backend/translations/sessions+intl-icu.fr.yaml`
- Test: `jardin-sonore-backend/tests/Integration/Application/Controller/SessionSummaryControllerTest.php`
- Modify: `docs/superpowers/specs/2026-07-30-session-document-design.md`

**Interfaces:**
- Consumes `GenerateSessionDocumentMessage` and current `SessionDocumentStatus`.
- Produces POST route `session_document_retry`, valid only for a failed session, which marks it pending and dispatches a new message.

- [ ] **Step 1: Write failing controller tests for document UI paths**

Assert a failed session exposes the retry control, valid CSRF retry dispatches exactly one message and redirects, an unavailable document download is rejected, and a ready document returns `application/pdf`.

- [ ] **Step 2: Run controller tests to verify they fail**

Run: `cd jardin-sonore-backend && php bin/phpunit tests/Integration/Application/Controller/SessionSummaryControllerTest.php`

Expected: failure because retry and controlled download are not wired.

- [ ] **Step 3: Implement retry UI and execute full checks**

Add the CSRF-protected retry action, translate all new visible wording, and update the design spec only if implementation reveals a necessary accepted precision. Do not modify catalogue entities or add catalogue listeners.

- [ ] **Step 4: Run the final automated and manual verification plan**

Run: `cd jardin-sonore-backend && php bin/phpunit && composer cs-check && composer stan && php bin/console doctrine:migrations:diff --allow-empty-diff`

Expected: all automated checks pass and Doctrine reports no schema delta. Then manually create/update/reorder a session, confirm each mutation queues and refreshes its PDF, inspect `var/log/*session_document*`, validate private media and notes are absent, confirm material/prolongations appear, and exercise failed-generation retry.

- [ ] **Step 5: Present the complete diff and test evidence for user review**

Run: `git diff --check && git diff --stat && git status --short`

Expected: only session document code, migration, translations, dependencies, tests, and the two design documents are changed. Do not commit; provide the test results and propose `feat(sessions): generate canonical session documents` for the user to review.
