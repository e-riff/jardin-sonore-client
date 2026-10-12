# Mailing Introduction Blocks Implementation Plan

> **For agentic workers:** Use `superpowers:executing-plans` task by task. The user authorized implementation inline. Keep changes uncommitted because this repository's `AGENTS.md` requires an explicit commit request.

**Goal:** Replace manual HTML entry for mailing introductions with reorderable text and image blocks, and add an independent optional preheader without changing historical campaigns.

**Architecture:** New campaigns store ordered introduction blocks in a nullable JSON column; historical rows keep `NULL` and continue through the existing formatter. The email renderer chooses the legacy or block path. Symfony form collections manage block uploads, and a Stimulus controller provides editing, ordering and limited formatting.

**Tech Stack:** Symfony 8, Doctrine ORM, Twig, Stimulus, PHPUnit, PHP-CS-Fixer, PHPStan.

**Spec:** `docs/superpowers/specs/2026-10-12-mailing-introduction-blocks-design.md`

## Global Constraints

- Keep the banner, CTA, recommendations, audience and delivery flows intact.
- Preheader is optional and separate from the visible subtitle.
- Existing campaigns with `intro_blocks IS NULL` preserve the current HTML, text and preheader rendering.
- Multiple images are permitted. Images are local backend uploads and must survive deployment.
- Rich text supports only strong, emphasis, HTTP(S) links and existing brand colors `#A64D43`, `#692019`, `#47664B`.
- `main_text` remains populated for compatibility. New content requires a nonempty text paragraph.
- Do not commit without an explicit user request. Do not execute a new Doctrine migration without asking the user after it has been generated and reviewed.

## Review Focus

- An old sent campaign with subtitle and markup must render byte-equivalent intro and preheader after the schema change: cover in renderer tests.
- A new campaign with no preheader must use the first text block rather than subtitle: cover in renderer tests.
- A removed or replaced image must not disappear when form validation fails: cover in functional form tests.
- Malicious link schemes, HTML attributes and forged image paths must not reach the email: cover in formatter and form tests.
- A block with image and text must remain readable without CSS media query support: cover in HTML assertions and browser/email preview review.

---

### Task 1: Persistence and compatibility model

**Files:** `MailingCampaign`, `MailingCampaignEntity`, Doctrine mapping, mapper, create/update inputs and handlers; new migration; domain and mapper tests.

**Interfaces:** Nullable `preheader: ?string` and `introBlocks: ?array` on campaign and entity. `NULL` denotes legacy rendering; an ordered array denotes new rendering. Each block has `id`, `kind`, `text`, `imagePath`, `imageAlt`, `imageDecorative`, `imageSide` as applicable.

- [ ] Write tests for legacy `NULL`, block round-trip, preheader normalization and invalid block data.
- [ ] Run focused tests and observe the expected failures.
- [ ] Add model, mapping and migration. Generate migration with `make:migration` where available; review its SQL and description.
- [ ] Run focused tests and inspect schema diff without executing the migration until the user confirms.

### Task 2: Email and plain-text rendering

**Files:** `NewsletterMainTextFormatter`, `TwigNewsletterRenderer`, `default.html.twig`, `default.txt.twig`, focused unit tests.

**Interfaces:** Renderer branches on `introBlocks === null`. For blocks it produces trusted HTML and plain text from validated block values; legacy path remains as it is. Explicit preheader wins, then first block text; only legacy rows use the previous subtitle fallback.

- [ ] Write failing tests for legacy fidelity, block order, both image positions, multiple images, safe links and preheader behavior.
- [ ] Run focused tests and observe failures.
- [ ] Implement block formatting and email-safe Twig structure with absolute image URLs and alt text.
- [ ] Run focused tests and verify HTML and plain text outputs.

### Task 3: Form processing and image lifecycle

**Files:** Mailing create/edit form models and types, controller, create/update handlers, a dedicated intro image storage service, form partials, translations, functional tests.

**Interfaces:** A collection of block form models carries type, text, upload and image metadata. Existing image paths are looked up by stable block ID from the persisted campaign, never trusted from a submitted hidden field. Files are stored only for valid submissions; abandoned/failed submissions leave existing images untouched.

- [ ] Write failing functional tests for creating, editing, invalid submission, old draft conversion, image replacement and locked campaigns.
- [ ] Run focused tests and observe failures.
- [ ] Implement form mapping, upload handling and cleanup after successful persistence; keep `mainText` as derived plain text.
- [ ] Run focused tests and verify storage side effects.

### Task 4: Visual editing

**Files:** `templates/mailing/new.html.twig`, `templates/mailing/content.html.twig`, a new block row partial, a Stimulus controller, backend CSS, translation catalog.

**Interfaces:** Add paragraph, image or split block; move up/down; remove; choose image side; edit text visually with strong, emphasis, link and three color controls. Submit synchronized sanitized markup through form fields. Pasted content is plain text. Preview link continues to use the server email renderer.

- [ ] Add a browser-level reproduction for adding, formatting, moving and removing a block.
- [ ] Observe the old form fail that scenario.
- [ ] Implement the controller and form templates with keyboard-accessible controls and a clear disabled state for locked campaigns.
- [ ] Verify the scenario locally on the existing server and run frontend asset checks.

### Task 5: Full verification and deployment readiness

**Files:** Tests and documentation only if needed.

- [ ] Run the focused mailing suites and full backend PHPUnit suite with adequate memory.
- [ ] Run PHP-CS-Fixer, PHPStan, `composer validate --strict`, Twig lint and `asset-map:compile`.
- [ ] Verify Doctrine schema diff contains only `preheader` and `intro_blocks`, and validate migration up/down SQL without applying it.
- [ ] Inspect browser preview at desktop and narrow width, including a legacy campaign and a new multi-image campaign.
- [ ] Review diff and status. Report migration pending explicit execution confirmation and list changed files and commands.
