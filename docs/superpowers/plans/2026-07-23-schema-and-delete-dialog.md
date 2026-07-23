# Schema and Delete Dialog Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver a clean Doctrine schema and a fully accessible responsive delete confirmation dialog.

**Architecture:** Keep one global Stimulus dialog in `templates/internal/base.html.twig`. Add a dedicated normalization migration after the theme migration, containing only the SQL reported by Doctrine for the current local database.

**Tech Stack:** Symfony 7, Doctrine Migrations, Twig, Symfony UX Stimulus, CSS.

## Global Constraints

- Do not delete application data or existing source files.
- Do not deploy while `doctrine:schema:update --dump-sql` emits SQL.
- Keep all UI wording in French translation files.
- Run PHP-CS-Fixer, PHPStan, Twig lint and migration checks before deployment.

---

### Task 1: Normalize Doctrine schema

**Files:**
- Create: `jardin-sonore-backend/migrations/Version20260723130000.php`

**Interfaces:**
- Consumes: SQL emitted by `php bin/console doctrine:schema:update --dump-sql` after `Version20260723120000`.
- Produces: a migration that makes the mapped schema equal to the local database schema.

- [ ] **Step 1: Capture the schema diff**

Run: `docker compose exec -T php php bin/console doctrine:schema:update --dump-sql`

Expected: only the known UUID/date/index/mailing-column normalization statements are emitted.

- [ ] **Step 2: Write the normalization migration**

Create an `up()` method containing each SQL statement from Step 1, and a description explaining that it aligns legacy database metadata with current Doctrine mappings.

- [ ] **Step 3: Apply locally**

Run: `make backend-migrate`

Expected: Doctrine reports that the new migration executed successfully.

- [ ] **Step 4: Verify a clean schema**

Run: `docker compose exec -T php php bin/console doctrine:schema:update --dump-sql`

Expected: no SQL output.

### Task 2: Complete delete-dialog interactions

**Files:**
- Modify: `jardin-sonore-backend/templates/internal/base.html.twig`
- Modify: `jardin-sonore-backend/assets/controllers/confirmation_dialog_controller.js`
- Modify: `jardin-sonore-backend/assets/styles/app.css`

**Interfaces:**
- Consumes: `confirmation-dialog` Stimulus targets `dialog`, `form`, `token` and `item`.
- Produces: close controls for cancel, Escape, backdrop click and cross button.

- [ ] **Step 1: Add dialog close controls**

Add a button labelled `Fermer la fenêtre de confirmation` in the dialog header with `click->confirmation-dialog#close`, and bind dialog clicks to `confirmation-dialog#closeOnBackdrop`.

- [ ] **Step 2: Restrict backdrop closing to the backdrop**

Implement `closeOnBackdrop(event)` so it calls `this.close(event)` only when `event.target === this.dialogTarget`.

- [ ] **Step 3: Apply responsive action layout**

Style dialog actions as a horizontal flex row on desktop and a column with a visible gap on mobile. Position the close button in the upper-right corner.

- [ ] **Step 4: Verify behavior**

Run: `node --check jardin-sonore-backend/assets/controllers/confirmation_dialog_controller.js`

Expected: exit code 0.

### Task 3: Verify and deliver

**Files:**
- Modify: generated assets only through `make symfony-assets`

- [ ] **Step 1: Run static verification**

Run: `make backend-lint && docker compose exec -T php php bin/console lint:twig templates && docker compose exec -T php php bin/console debug:translation fr --domain=internal --only-missing`

Expected: each command exits successfully and reports no missing translation.

- [ ] **Step 2: Rebuild production assets**

Run: `make symfony-assets`

Expected: asset mapper compiles assets successfully.

- [ ] **Step 3: Commit, tag, push and deploy**

Run: `git add -A && git commit -m "feat(backoffice): improve catalog management" && git tag <deployment-tag> && git push origin main && git push origin <deployment-tag> && make deploy-backend`

Expected: branch, tag and backend deployment complete successfully.
