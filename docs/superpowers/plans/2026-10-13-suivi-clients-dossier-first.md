# Suivi clients dossier-first Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. The user and repository instructions forbid commits without an explicit request; the commit step normally suggested by this skill is deliberately omitted.

**Goal:** Faire du dossier l'unique entrée visible du suivi clients, avec création rapide, tâches datées, fil chronologique et documents compacts, tout en conservant les anciennes données.

**Architecture:** Garder les tables de demande pour les données brutes, la clé de soumission et le mail, mais créer un dossier pour chaque nouvelle arrivée. Les commandes métier sur tâches, devis et factures produisent leurs événements dans la même transaction. Les écrans Symfony UX lisent les dossiers ; les anciennes routes redirigent vers eux.

**Tech Stack:** PHP 8.4, Symfony 8.1, Doctrine ORM/DBAL et migrations, Twig, Symfony UX LiveComponent, Stimulus, CSS existant, PHPUnit 12.

**Spec:** [2026-10-13-suivi-clients-dossier-first-design.md](../specs/2026-10-13-suivi-clients-dossier-first-design.md)

## Global Constraints

- Préserver les dossiers, demandes, tâches, événements, devis, factures, dates et liens d'e-mails existants ; aucune suppression de données.
- Les dossiers publics peuvent avoir `organization_id` nul jusqu'au rapprochement humain ; les créations manuelles exigent une structure.
- Une soumission publique rejouée conserve un seul dossier, une seule tâche initiale et une seule livraison de mail.
- Gmail, Drive et l'outil de facturation restent les sources des conversations et fichiers en V1.
- Les états visibles sont Ouvert, Confirmé, Terminé et Sans suite ; « À traiter » est déduit des tâches.
- Les libellés réutilisables vont dans `translations/commercial.fr.yaml` ou le domaine partagé concerné ; interface mobile first et accessible au clavier.
- Aucun commit, déploiement ni exécution de migration de données avant l'accord requis par `AGENTS.md`. Pour la migration Doctrine, générer avec `make:migration` quand possible, puis nettoyer et décrire le résultat.

## Review Focus

1. Soumission publique sans nom de structure : dossier provisoire lisible avec coordonnées brutes, sans création d'annuaire ni erreur de rendu.
2. Rejeu concurrent du même `submission_key` : un dossier, une tâche et une livraison ; contenu différent avec même clé rejeté.
3. Demande ancienne liée à deux dossiers : l'URL historique ouvre le plus ancien, les deux dossiers restent intacts et le message initial n'est pas dupliqué.
4. Devis refusé puis nouveau devis envoyé : le dossier reste Ouvert ou Confirmé selon son état antérieur ; une nouvelle signature n'ajoute qu'un événement.
5. Action rapide lancée depuis une ligne entièrement cliquable : le bouton reçoit le clic et le clavier sans ouvrir la ligne ni muter via GET.

---

## File Structure

Le modèle et la reprise restent dans `Infrastructure/Doctrine/Entity`, `Infrastructure/Doctrine/Mapping`, `Infrastructure/Commercial` et une migration générée. `Application/Commercial` porte les commandes de création et les transitions. Les contrôleurs gèrent seulement les entrées HTTP. Les pages de suivi restent sous `templates/commercial`, leurs chaînes dans `translations/commercial.fr.yaml` ; le bouton de retour commun vit dans `templates/components`. Une composante LiveComponent gère l'index filtrable. Les tests reprennent les suites Unit, Integration et Functional déjà présentes.

### Task 1: Modèle dossier compatible et reprise des anciennes demandes

**Files:**
- Modify: `jardin-sonore-backend/src/Infrastructure/Doctrine/Entity/CommercialProjectEntity.php`, `CommercialEventEntity.php`, `CommercialQuoteEntity.php`
- Modify: leurs trois mappings dans `jardin-sonore-backend/src/Infrastructure/Doctrine/Mapping/`
- Create: migration Doctrine générée dans `jardin-sonore-backend/migrations/Version<timestamp>.php`
- Create: `jardin-sonore-backend/src/Infrastructure/Commercial/BackfillCommercialRequestProjects.php`
- Test: `jardin-sonore-backend/tests/Integration/Infrastructure/Commercial/CommercialDossierBackfillTest.php`

**Interfaces:** `CommercialProjectEntity::__construct(string $title, ?OrganizationEntity $organization, ?CommercialRequestEntity $request, DateTimeImmutable $createdAt)`, `setOrganization(OrganizationEntity $organization): void`, `setTitle(string $title): void`, `getOrganization(): ?OrganizationEntity`; `BackfillCommercialRequestProjects::run(): int` returns the count of created projects. `CommercialQuoteEntity` gains `refusedOn` and the event gains nullable source relations to action, quote and invoice.

- [ ] Write integration tests for linked requests (including two projects), unlinked `to_qualify` and `without_result` requests, preserved IDs/statuses, and a second backfill run creating zero dossiers or tasks. Assert a verification task only for an unlinked request still `to_qualify`.
- [ ] Run `cd jardin-sonore-backend && ./bin/phpunit tests/Integration/Infrastructure/Commercial/CommercialDossierBackfillTest.php`; expect failure on missing backfill interface.
- [ ] Update nullable organization and event source mappings; generate and review the migration for `organization_id`, `quote.refused_on` and nullable event source FKs. Add DBAL backfill with a deterministic canonical project (`created_at`, then `id`) and raw request data preserved. Use `SELECT ... FOR UPDATE` or equivalent for idempotent row handling.
- [ ] Run the focused test; expect pass. Run a schema diff check against the test database; expect no unrelated SQL. **Do not run this migration on production here.**

### Task 2: Création manuelle directe et rattachement du dossier provisoire

**Files:**
- Create: `jardin-sonore-backend/src/Application/Commercial/CreateCommercialProject.php`, `jardin-sonore-backend/src/Application/Form/CommercialProjectCreateType.php`, `jardin-sonore-backend/templates/commercial/project/new.html.twig`
- Modify: `jardin-sonore-backend/src/Application/Controller/CommercialProjectController.php`, `CommercialProjectContactsController.php`
- Modify: `jardin-sonore-backend/translations/commercial.fr.yaml`
- Test: `jardin-sonore-backend/tests/Functional/Application/Controller/CommercialProjectControllerTest.php`

**Interfaces:** `CreateCommercialProject::manual(OrganizationEntity $organization, ?PersonEntity $person, string $firstMessage, ?string $title, string $initialStatus, ?string $taskTitle, ?DateTimeImmutable $taskDueOn): CommercialProjectEntity`. The first message is a dated event; a task is created only when title and due date are present. Existing `commercial_project_show` remains the success route.

- [ ] Write functional tests: existing/new structure; person belonging to the selected structure or no person; rejection of a foreign person; optional title; editable/removable default « Répondre » task due on the local current day; initial Open/Confirmed; POST redirects to the new dossier. Cover provisional dossier organization assignment without changing raw request values.
- [ ] Run the focused controller test; expect the new creation route to fail.
- [ ] Implement the service, form and controller GET/POST; reuse `OrganizationAutocompleteType`, the existing person lookup endpoint and explicit person creation. Avoid email/phone fields in the first-event area. Add a separate POST for explicit organization assignment to a provisional dossier and adapt contact operations to require an assigned organization.
- [ ] Run the focused test; expect pass.

### Task 3: Création automatique et idempotente depuis le formulaire public

**Files:**
- Modify: `jardin-sonore-backend/src/Infrastructure/Commercial/ContactRequestDeliveryStore.php`
- Modify: `jardin-sonore-backend/src/Application/Controller/CommercialContactApiController.php` only if its response contract requires the dossier ID
- Test: `jardin-sonore-backend/tests/Integration/Infrastructure/Commercial/ContactRequestDeliveryTest.php`, `jardin-sonore-backend/tests/Functional/Application/Controller/CommercialContactApiControllerTest.php`

**Interfaces:** `ContactRequestDeliveryStore::record(CommercialContactRequestInput $input): int` retains its request-ID contract for the Next BFF. During its existing DBAL transaction, lock the request row, ensure one related provisional project and one `Répondre` task due on the local intake date, then preserve the delivery upsert and payload hash validation.

- [ ] Add tests for an absent organization name, exact preservation of original fields, same-key replay, conflicting payload, one task, and failure before commit leaving no partial dossier or success response.
- [ ] Run both focused test files; expect failure because no dossier is created.
- [ ] Extend the existing transactional DBAL upsert without changing the public response or mail content. Set the technical request status consistently and avoid an extra duplicate event because the raw first message supplies the initial timeline entry.
- [ ] Run both focused test files; expect pass.

### Task 4: Tâches et fil modifiables sans faits inventés

**Files:**
- Modify: `jardin-sonore-backend/src/Application/Commercial/CommercialActionWorkflow.php`, `CommercialProjectWorkflow.php`
- Modify: `jardin-sonore-backend/src/Application/Controller/CommercialActionController.php`, `CommercialProjectController.php`
- Modify: `jardin-sonore-backend/src/Infrastructure/Doctrine/Entity/CommercialEventEntity.php`
- Test: `jardin-sonore-backend/tests/Unit/Application/Commercial/CommercialWorkflowTest.php`, `jardin-sonore-backend/tests/Functional/Application/Controller/CommercialActionControllerTest.php`, `CommercialProjectControllerTest.php`

**Interfaces:** `CommercialActionWorkflow::complete(CommercialActionEntity $action, ?string $note, ?string $correctedTitle = null): void`; `CommercialActionWorkflow::postpone(CommercialActionEntity $action, DateTimeImmutable $dueOn): void` remains. Add `CommercialEventEntity::revise(?string $content, array $metadata): void` for free notes and completed-task display text, with a source link for the latter.

- [ ] Test task creation independent of note creation; completion with corrected title and optional result; repeated completion rejected without duplicate event; postpone history containing old/new dates; note correction preserving `occurredAt`; source-linked automatic events edited through their source only. Test manual status changes in both directions, including reopening without reactivating canceled tasks and closure rules for unpaid invoices.
- [ ] Run focused unit and functional tests; expect failures for missing routes/signatures.
- [ ] Add separate, CSRF-protected POST routes for `+ À faire`, `+ Ajouter au fil`, event editing and manual status selection. Validate title/date/person on the server; keep the existing note-draft behavior where a longer note form remains useful. Extend `CommercialProjectWorkflow` with `changeStatusManually(CommercialProjectEntity $project, string $status): void`, preserving closure guards and recording reversals. Implement source-aware event rendering and task completion in the workflow, not a Doctrine listener.
- [ ] Run focused tests; expect pass.

### Task 5: Devis refusé, corrections et factures compactes

**Files:**
- Modify: `jardin-sonore-backend/src/Application/Commercial/RecordCommercialQuote.php`, `RecordCommercialInvoice.php`
- Modify: `jardin-sonore-backend/src/Application/Controller/CommercialQuoteController.php`, `CommercialInvoiceController.php`
- Modify: `jardin-sonore-backend/src/Application/Form/CommercialQuoteType.php`, `CommercialInvoiceType.php`
- Test: `jardin-sonore-backend/tests/Unit/Application/Commercial/CommercialQuoteWorkflowTest.php`, `CommercialInvoiceWorkflowTest.php`, `jardin-sonore-backend/tests/Functional/Application/Controller/CommercialQuoteControllerTest.php`, `CommercialInvoiceControllerTest.php`

**Interfaces:** `RecordCommercialQuote::markRefused(CommercialQuoteEntity $quote, DateTimeImmutable $refusedOn): void`; `RecordCommercialQuote::correct(CommercialQuoteEntity $quote, string $reference, string $filename, int $amountCents, DateTimeImmutable $sentOn): void`; `RecordCommercialInvoice::correct(CommercialInvoiceEntity $invoice, string $reference, int $amountCents, DateTimeImmutable $issuedOn): void`. Existing `markSigned()` remains and rejects a refused quote; refused/signed transitions are idempotent for same-state retries.

- [ ] Test sent → signed confirms once; sent → refused retains dossier status; refused → signed rejected; repeated transitions add no event; corrected entry preserves previous values in an audit event; new actual quote uses `replaces` instead of overwriting an old sent PDF. Test invoice pay and correction similarly.
- [ ] Run focused workflow and controller tests; expect refusal/edit paths to fail.
- [ ] Add CSRF-protected refuse/edit routes and forms. Save signed/refused dates and linked events in one flush; retain the existing unique quote reference rule and invoice reminder behavior.
- [ ] Run focused tests; expect pass.

### Task 6: Accueil, index des dossiers et courriel regroupés par dossier

**Files:**
- Create: `jardin-sonore-backend/src/Application/Commercial/CommercialProjectQueue.php`, `jardin-sonore-backend/src/Application/Twig/Component/CommercialProjectTable.php`, `jardin-sonore-backend/templates/components/CommercialProjectTable.html.twig`, `jardin-sonore-backend/templates/commercial/project/index.html.twig`
- Modify: `jardin-sonore-backend/src/Application/Controller/CommercialDashboardController.php`, `CommercialProjectController.php`
- Modify: `jardin-sonore-backend/src/Application/Commercial/BuildCommercialDigest.php`, `CommercialDigest.php`, `jardin-sonore-backend/templates/commercial/digest_email.html.twig`, `digest_email.txt.twig`
- Test: `jardin-sonore-backend/tests/Functional/Application/Controller/CommercialDashboardControllerTest.php`, `CommercialProjectControllerTest.php`, `jardin-sonore-backend/tests/Integration/Infrastructure/Commercial/BuildCommercialDigestTest.php`

**Interfaces:** `CommercialProjectQueue::forDate(DateTimeImmutable $localDate): array` returns dossier rows with the earliest open task, extra-task count and bucket `priority|upcoming|without_task`. `CommercialProjectTable` exposes LiveProps `query`, `status`, `sort` and renders filtered project rows. Keep `commercial_dashboard` and `commercial_project_show` routes.

- [ ] Test one dossier with two tasks appears once under its earliest due date; closed dossiers absent by default and present by filter; « Nouveau » appears for the first seven local days only; text search and sort changes without submit; Monday digest lists active no-task dossiers but never a separate request section. Test provisional dossier labels when organization is null. Test that a row's quick postpone offers +1 day, +2 days, +1 week and a free date without triggering row navigation.
- [ ] Run focused tests; expect the old request group and duplicate action rows to fail.
- [ ] Implement queue query with bounded joins or a query DTO to avoid per-row collection loads. Render three collapsible tables (priority open by default), each row as a keyboard-accessible link with independent quick-action forms; reuse `catalog_row_controller.js` only where it already handles nested buttons correctly. Add the LiveComponent for the full index, following `RepertoireCatalogTable` conventions. Update digest model/templates and translation keys.
- [ ] Run focused tests; expect pass.

### Task 7: Fiche dossier mobile first, contacts, documents et chronologie

**État au 13 octobre 2026 :** première implémentation commitée sous `a732e0a`, tests et contrôles techniques réussis. La validation visuelle reste ouverte : l'utilisateur trouve le rendu moins convaincant que la proposition approuvée. Reprendre la comparaison avec les maquettes et captures avant de cocher cette tâche ou de commencer l'harmonisation de la tâche 8. Vérifier notamment la densité de la fiche, les contacts, la visibilité des tâches et la lecture du fil sur mobile et ordinateur. La recette authentifiée sur téléphone n'a pas encore été faite.

**Files:**
- Modify: `jardin-sonore-backend/templates/commercial/project/show.html.twig`, `jardin-sonore-backend/assets/styles/app.css`, `jardin-sonore-backend/translations/commercial.fr.yaml`
- Create: `jardin-sonore-backend/assets/controllers/commercial_timeline_controller.js` only if native `<details>` cannot meet the two-line disclosure and default-open behavior
- Test: `jardin-sonore-backend/tests/Functional/Application/Controller/CommercialProjectControllerTest.php`, `jardin-sonore-backend/tests/Unit/Template/InternalNavigationTemplateTest.php`

**Interfaces:** The show template receives `project`, open task rows, timeline rows (raw initial request + events sorted by timestamp/id descending), document counts and `driveLinks`. CSS supplies shared document, task and timeline classes. No data mutation occurs in the template.

- [ ] Test rendered provisional and attached contacts, both structure and person phone numbers, plain visible values with adjacent `tel:`/`mailto:` icons; document `<details>` closed initially; quote/invoice icon action labels; date and complete/postpone controls on tasks; newest timeline row open; long entries show disclosure, short entries do not.
- [ ] Run focused rendering tests; expect layout semantics/assertions to fail.
- [ ] Rebuild the dossier template in the approved order. Keep contacts legible, including the structure phone on mobile. Use a compact collapsed document summary and one external-link explanation; icons have labels/titles. Keep the strongest timeline treatment from the earlier mockups: spine, clear dates, first item open, two-line excerpts and edit pencil outside disclosure. Add only the minimum Stimulus needed for text overflow detection.
- [ ] Run rendering tests, `make symfony-assets`, and manual phone/desktop browser recipe; expect no horizontal scroll and accessible keyboard actions.

### Task 8: Navigation historique et styles partagés du backoffice

**Files:**
- Modify: `jardin-sonore-backend/src/Application/Controller/CommercialRequestController.php`
- Modify: `jardin-sonore-backend/templates/internal/base.html.twig`, affected `templates/commercial/*/*.html.twig`, header back links in `templates/mailing/*.html.twig`, `templates/mailing/audience_mask/*.html.twig`, `templates/session/{new,edit,sequence_form}.html.twig`, `templates/{instrument,media_resource,repertoire}/form.html.twig`, and `jardin-sonore-backend/assets/styles/app.css`
- Create: `jardin-sonore-backend/templates/components/_internal_back_button.html.twig`
- Modify: `jardin-sonore-backend/translations/internal+intl-icu.fr.yaml`, `commercial.fr.yaml`
- Test: `jardin-sonore-backend/tests/Functional/Application/Controller/CommercialRequestControllerTest.php`, `jardin-sonore-backend/tests/Unit/Template/InternalNavigationTemplateTest.php`

**Interfaces:** GET `/commercial/requests/{id}` redirects to canonical `commercial_project_show` (oldest linked project), GET `/commercial/requests` redirects to `commercial_project_index`, GET `/commercial/requests/new` redirects to `commercial_project_new`. The back-button partial accepts `href: string` and `label: string` so existing routes and translated labels can be reused.

- [ ] Test old request URLs after migration, including a multi-dossier legacy request; verify no old request index or qualification step remains in the nav. Test back button presence and consistent target on dossier, quote, invoice and other visited business forms. Nested row buttons must not trigger row navigation.
- [ ] Run focused tests; expect old route/template behavior to fail.
- [ ] Add redirects without breaking old mailed links; replace nav item with Dossiers and use the shared back-button partial for the header back links found across the business backoffice. Keep destination-specific labels, consolidate only CSS rules shared by tables/forms in the touched screens, and preserve portal-specific styling.
- [ ] Run focused tests; expect pass.

### Task 9: Integration, migration audit and final recipe

**Files:**
- Modify: tests or translations touched by Tasks 1–8 only when a failed check shows a concrete gap
- Document: update `ROADMAP.md` after the implementation's actual status is known, not before

- [ ] Run the full commercial PHP test set and the integration tests for contact delivery and digest; expect all pass.
- [ ] Run `make backend-lint` and `make symfony-assets`; expect PHP-CS-Fixer, PHPStan and asset build pass. Run Doctrine schema validation and diff; expect only the intended migration and no remaining schema drift.
- [ ] After the separate confirmation required by `AGENTS.md`, apply the migration on a test database and run backfill twice; inspect counts before/after for requests, projects, tasks, events, quotes and invoices. Confirm one task and one dossier for a replayed site submission and old URL redirects. A production run requires its own deployment decision and checks.
- [ ] Review authenticated flows on phone and desktop: create a manual dossier, handle an unresolved site dossier, complete/postpone a task, sign/refuse a quote, find a document, filter closed dossiers, and use the back button. Check keyboard navigation, tap targets and line overflow.
- [ ] Report changed files, commands and any limitation to the user. Propose a conventional commit message; do not commit without explicit agreement.
