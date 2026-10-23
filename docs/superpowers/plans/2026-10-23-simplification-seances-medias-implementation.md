# Simplification des séances et médias Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Composer des séquences libres en un passage, gérer les médias depuis leur catalogue et le répertoire, et réduire les actions offertes sur les séquences issues du répertoire.

**Architecture:** Conserver les JSON de répertoire et de séance en lisant leurs anciens formats. Les nouvelles associations de répertoire portent un état de visibilité ; les nouvelles associations de séquence libre portent un UUID de média. Turbo charge les dialogues, Stimulus gère leur ouverture et la sélection locale, et LiveComponent conserve l'édition des consignes.

**Tech Stack:** PHP 8.4, Symfony, Doctrine, Twig, Symfony UX Turbo/LiveComponent/Autocomplete, Stimulus, PHPUnit.

**Spec:** [Conception](../specs/2026-10-23-simplification-seances-medias-design.md)

## Global Constraints

- Respecter `AGENTS.md`, `.codex/jardin-sonore-guidelines.md` et la priorité de `ROADMAP.md`.
- Ne pas modifier une fiche du répertoire lorsqu'on ajoute sa séquence à une séance.
- Une séquence libre sélectionne des médias visibles sans réglage par lien ; seul le répertoire porte un état local de visibilité, activé par défaut.
- L'état global inactif masque un média partout, y compris dans les anciennes séances.
- Garder lisibles les anciens liens UUID du répertoire, les copies de médias des séances et les champs média historiques ; préserver le contrat MCP existant.
- Les libellés UI vont dans `translations/sessions+intl-icu.fr.yaml`. N'ajouter aucune dépendance.
- Ne faire aucun commit sans demande ou accord explicite de l'utilisateur. Une migration créée n'est exécutée qu'après confirmation, conformément à `AGENTS.md`.

## Review Focus

- Média lié à plusieurs fiches avec des visibilités différentes : le réglage d'une fiche ne change pas les autres (Task 2).
- Média globalement désactivé après publication : il disparaît du portail et du PDF, tout en restant identifiable dans l'édition (Tasks 2 et 4).
- Séquence libre non enregistrée avec champs saisis et média créé à la volée : le retour au formulaire conserve les champs et la fermeture ne laisse pas de séquence vide (Task 5).
- Ancienne séance avec copie média et fiche de répertoire avec liste d'UUID : affichage conservé après évolution des formats (Tasks 2 et 4).
- Titres de média identiques : résultats distincts, recherche sur titre/type/source/numéro/URL et sélection de l'UUID voulu (Task 1).

---

### Task 1: Catalogue média, catégories et choix distinctifs

**Files:**
- Modify: `jardin-sonore-backend/src/Domain/Model/Session/MediaResourceType.php`, `src/Domain/Model/Session/MediaResource.php`, `src/Infrastructure/Doctrine/Mapper/MediaResourceMapper.php`, `src/Infrastructure/Doctrine/Repository/MediaResourceDoctrineRepository.php`, `src/Application/Form/MediaResourceType.php`, `src/Application/Form/Model/MediaResourceFormModel.php`, `src/Application/Form/MediaResourceChoiceLabels.php`, `src/Application/Session/SearchMediaResources.php`, `src/Application/Session/MediaResourceView.php`, `templates/media_resource/form.html.twig`, `templates/media_resource/new_frame.html.twig`, `translations/sessions+intl-icu.fr.yaml` (paths relative to `jardin-sonore-backend/`).
- Test: `jardin-sonore-backend/tests/Unit/Application/Form/MediaResourceChoiceLabelsTest.php`; update existing media form and search tests if present.

**Interfaces:** Produce a label formatter used by both repertoire choices and the session picker; expose a display number through the read model without replacing the UUID as link key.

- [ ] **Step 1:** Add a failing test with two media sharing `Sui lo ruin`: labels contain different numeric IDs, type and source; searches by `partition`, source, number and URL find the intended result.
- [ ] **Step 2:** Run `docker compose exec -T php php -d memory_limit=1G ./bin/phpunit tests/Unit/Application/Form/MediaResourceChoiceLabelsTest.php` and confirm the new assertions fail.
- [ ] **Step 3:** Keep stored enum values `soundtrack`, `video`, `link` so old rows remain readable; relabel `soundtrack` as « Musique » and `link` as « Autre », and add `ambient_sound` (« Fond sonore ») and `score` (« Partition »). Expose the existing integer DB ID as read-only display data through mapper/domain/view, retaining UUID as the link key. Keep historical fields readable, remove description/secondary URL/image inputs from the new UI, and retain source, file or URL, themes and active state. Update the picker to show number, title, type and source or a short URL hint.
- [ ] **Step 4:** Run the focused test and existing media form/search tests; confirm pass. Inspect old records with a nonempty historical field before deciding whether a data migration is needed.

### Task 2: Visibilité des liens du répertoire et rendu partagé

**Files:**
- Modify: `jardin-sonore-backend/src/Domain/Model/Session/RepertoireItem.php`, `src/Application/Session/RepertoireItemView.php`, `src/Application/Session/SaveRepertoireItemInput.php`, `src/Application/Session/SessionSequenceView.php`, `src/Application/Mcp/McpSessionCatalog.php`, `src/Application/Mcp/WriteToolRegistry.php` and the repertoire mapper/entity only as needed for JSON compatibility.
- Test: `jardin-sonore-backend/tests/Unit/Application/Session/SessionSequenceViewTest.php`, `tests/Unit/Domain/Model/Session/RepertoireItemGeneralInstructionsTest.php`, relevant MCP catalogue tests.

**Interfaces:** `RepertoireItem::getLinkedMediaUuids()` remains available for existing callers; add a normalized association read method returning UUID plus `displayOnPublic` so new and old JSON can be consumed together.

- [ ] **Step 1:** Add failing tests for a legacy UUID list, new association objects, different visibility per fiche, global inactivity, and the first visible active media being featured.
- [ ] **Step 2:** Run the focused PHPUnit tests and confirm the new cases fail.
- [ ] **Step 3:** Normalize legacy UUID entries as visible, persist new visibility on each link, and filter `documentMedia` for portal, HTML preview and PDF. Preserve hidden links in the internal editor. Keep MCP reads/writes compatible with the existing `linkedMediaUuids` contract while exposing the new visibility without losing it on edits.
- [ ] **Step 4:** Run the focused tests and verify portal/PDF view tests pass. Inspect the schema diff; add a migration only if the chosen storage actually requires one.

### Task 3: Gestion des médias depuis la fiche du répertoire

**Files:**
- Modify: `jardin-sonore-backend/templates/repertoire/form.html.twig`, `templates/media_resource/new_frame.html.twig`, `src/Application/Controller/RepertoireCatalogController.php`, `src/Application/Controller/MediaResourceCatalogController.php`, `assets/controllers/repertoire_media_linker_controller.js`, `assets/controllers/internal_dialog_controller.js`, `assets/styles/app.css`.
- Create: a small shared Twig partial under `jardin-sonore-backend/templates/media_resource/` for a media result or linked card, reused by repertoire and sequence dialogues.
- Test: add a focused functional controller test under `jardin-sonore-backend/tests/Functional/Application/Controller/`.

**Interfaces:** The dialog submits UUID associations and visibility to Task 2; creating media returns its UUID to the invoking form. It does not delete catalogue resources when a link is removed.

- [ ] **Step 1:** Add a failing functional test: add existing media, create and attach media, hide one link, detach another, and retain unrelated catalogue records.
- [ ] **Step 2:** Run that test and confirm failure.
- [ ] **Step 3:** Replace the current select plus generated HTML list with a native `<dialog>` and Turbo Frame for catalogue search/create; keep link state on the parent repertoire form. Use Stimulus only for dialog lifecycle and local selection, and retain the parent form's unsaved values when the frame changes. Support preview of file or URL.
- [ ] **Step 4:** Run the functional test, `composer cs-check`, `composer stan`, and an asset build. Check Escape, close button, backdrop and keyboard focus manually.

### Task 4: Catalogue references in free sequences

**Files:**
- Modify: `jardin-sonore-backend/src/Domain/Model/Session/SessionSequence.php`, `src/Domain/Model/Session/SessionSequenceMedia.php`, `src/Application/Form/Model/SessionSequenceFormModel.php`, `src/Application/Session/SessionSequenceView.php`, `src/Application/Session/SaveSessionSequenceInput.php`, `src/Application/Controller/SessionSummaryController.php`.
- Create: `jardin-sonore-backend/src/Application/Command/ReconcileSessionMediaReferencesCommand.php`, commande `app:sessions:reconcile-media-references` en lecture seule par défaut avec option d'écriture explicite.
- Test: `jardin-sonore-backend/tests/Unit/Domain/Model/Session/SessionSequenceMediaTest.php`, `tests/Unit/Application/Session/SessionSequenceViewTest.php`, `tests/Unit/Application/Form/Model/SessionSequenceFormModelTest.php`.

**Interfaces:** New free-sequence media entries carry catalogue UUIDs and resolve current title/type/URL at read time; legacy entries without UUID retain their snapshot behavior.

- [ ] **Step 1:** Add failing tests for UUID round-trip, catalogue changes reflected in a free sequence, global inactive filtering, duplicate add suppression, missing catalogue resources, legacy snapshot deserialization, and safe reconciliation of a legacy snapshot whose URL matches exactly one catalogue resource.
- [ ] **Step 2:** Run the focused tests and confirm failure.
- [ ] **Step 3:** Extend the JSON media representation compatibly, resolve UUID-backed entries through `MediaResourceRepositoryInterface`, and keep old snapshots readable. Include a selected medium immediately; detach by UUID without deleting the catalogue resource. Provide a dry-run reconciliation report for existing snapshots: assign a catalogue UUID only for an unambiguous URL match, and list unmatched or ambiguous entries for review before any data rewrite.
- [ ] **Step 4:** Run focused tests and confirm portal and document view tests pass. Review the reconciliation report; request confirmation before executing a data migration, and record unresolved legacy snapshots as a release risk if any remain.

### Task 5: Formulaire unique des séquences libres et repère commun

**Files:**
- Modify: `jardin-sonore-backend/src/Application/Form/SessionSequenceType.php`, `src/Application/Form/RepertoireSessionSequenceType.php`, `src/Application/Controller/SessionSummaryController.php`, `src/Application/Twig/Component/SessionSequenceLocalDetails.php`, `templates/components/SessionSequenceLocalDetails.html.twig`, `templates/session/composer_activity_form.html.twig`, `templates/session/sequence_form.html.twig`, `templates/session/_composer.html.twig`, `translations/sessions+intl-icu.fr.yaml`.
- Create: `jardin-sonore-backend/assets/controllers/session_media_dialog_controller.js` only if the existing dialog controller cannot preserve the unsaved parent form and selected UUIDs.
- Test: `jardin-sonore-backend/tests/Integration/Routing/SessionMediaRoutingTest.php`, `tests/Unit/Template/SessionTemplateRegressionTest.php`, plus a focused functional create/edit test.

**Interfaces:** One optional `role` value remains the stored repère; `type` is inferred for new free sequences and from repertoire source for repertoire sequences. The media dialog from Task 3 remains a sibling of the parent form, so Turbo frame updates never replace entered fields.

- [ ] **Step 1:** Add failing tests for one-submit creation, no sequence after cancel, repère free text, instruments before long content, lyrics/gestures in `<details>`, and media creation/selection preserving unsaved fields.
- [ ] **Step 2:** Run focused tests and confirm failure.
- [ ] **Step 3:** Rename UI copy to « Séquence libre » and « Repère dans le déroulé », keep a free text input with nonbinding suggestions, remove visible type choice, and render all creation fields at once. Use Turbo Streams only when saving must update the composer and close the overlay; retain LiveComponent for inline instructions and Stimulus for client-only dialog actions.
- [ ] **Step 4:** Run focused tests, `composer cs-check`, `composer stan`, and asset build; manually create, cancel, reopen and edit a free sequence.

### Task 6: Séquences du répertoire et accès direct à leur fiche

**Files:**
- Modify: `jardin-sonore-backend/templates/session/composer_repertoire_form.html.twig`, `templates/session/_composer.html.twig`, `src/Application/Controller/SessionSummaryController.php`, `translations/sessions+intl-icu.fr.yaml`.
- Test: `jardin-sonore-backend/tests/Unit/Template/SessionTemplateRegressionTest.php`, `tests/Integration/Routing/SessionMediaRoutingTest.php`.

**Interfaces:** A repertoire sequence keeps only local repère, instruments and instructions; the source edit link uses `repertoire_edit` with `target="_blank"` and `rel="noopener noreferrer"` when source UUID resolves.

- [ ] **Step 1:** Add failing tests proving the repertoire editor contains no sequence-level media creation/attachment controls and the card has an accessible source edit link only for an existing source.
- [ ] **Step 2:** Run focused tests and confirm failure.
- [ ] **Step 3:** Simplify the repertoire sequence form and card, fold long lyrics/gestures into `<details>`, and remove obsolete sequence media routes only after checking whether old bookmarked links need a redirect. Keep local instructions separate from the source fiche.
- [ ] **Step 4:** Run focused tests and manually confirm changes made in the new tab appear in a refreshed session without overwriting local details.

### Task 7: Regression pass and release readiness

**Files:** Update only the regression tests or translations implicated by failures in Tasks 1–6.

- [ ] **Step 1:** Run `docker compose exec -T php php -d memory_limit=1G ./bin/phpunit`, `composer cs-check`, `composer stan`, and the backend asset build command established in the Makefile; fix concrete failures.
- [ ] **Step 2:** Manually inspect old and new sessions in the backoffice, portal, HTML preview and PDF, including hidden/inactive media and duplicate titles.
- [ ] **Step 3:** Review `git diff --check`, `git status --short`, and Doctrine schema diff. If a migration exists, inspect SQL and `down()`; ask before running it, as required by `AGENTS.md`.
- [ ] **Step 4:** Report files changed, commands and outcomes, remaining risks, and a conventional commit suggestion. Do not commit or deploy without the user's instruction.
