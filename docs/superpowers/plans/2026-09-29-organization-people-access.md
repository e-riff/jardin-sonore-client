# Organization People Access Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let administrators find people by organization name and open and safely edit each person's complete record from an organization detail page.

**Architecture:** Keep `PersonCrudController` as the one editor. Add links on the organization detail page and an EasyAdmin association search field. Prevent edits of shared email or phone values from mutating another person's or organization's contact by detaching a shared contact before form binding changes its value.

**Tech Stack:** PHP 8.4, Symfony 8.1, Doctrine ORM 3, EasyAdmin 5, PHPUnit 12.

**Spec:** `docs/superpowers/specs/2026-09-29-admin-account-actions-and-organization-people-design.md`

## Global Constraints

- The backoffice is restricted to `ROLE_ADMIN`; the portal remains read-only for directory data.
- No schema change or new dependency is expected.
- Keep UI wording in the `backoffice` translation catalog where practical.
- Do not commit or deploy without the user's explicit request, per `AGENTS.md`.

## Review Focus

- An organization with no people must display a clear empty state without broken links (Task 1).
- An organization with several people must link each row to the correct person (Task 1).
- A search for part of an organization name must return its people and preserve ordinary person-name search (Task 1).
- Editing a shared email or phone must leave every other link and value unchanged (Task 2).
- Changing only a link label, type or active state must not create or mutate a shared contact value (Task 2).

---

### Task 1: Navigate and search people

**Files:**
- Modify: `jardin-sonore-backend/src/Infrastructure/Admin/OrganizationCrudController.php`
- Modify: `jardin-sonore-backend/src/Infrastructure/Admin/PersonCrudController.php`
- Create: `jardin-sonore-backend/templates/admin/field/organization_people.html.twig`
- Modify: `jardin-sonore-backend/translations/backoffice+intl-icu.fr.yaml` if new labels are needed.
- Create: `jardin-sonore-backend/tests/Functional/Infrastructure/Admin/OrganizationPeopleNavigationTest.php`

**Interfaces:**
- Consume: `OrganizationEntity::getPeople(): Collection`, `AdminUrlGenerator`, and `PersonCrudController` detail/edit pages.
- Produce: detail-page person links, including an edit route, and `PersonCrudController` search fields including `organization.name`.

- [ ] **Step 1: Write failing functional cases** for an empty organization, multiple linked people with correct detail/edit destinations, and searching the person index by full/partial organization name while preserving person-name search.
- [ ] **Step 2: Run** `./bin/phpunit tests/Functional/Infrastructure/Admin/OrganizationPeopleNavigationTest.php` from `jardin-sonore-backend/`; expect the new cases to fail.
- [ ] **Step 3: Render organization people** through a focused EasyAdmin detail field template using generated internal URLs, escaping names and roles; keep the existing add-person action.
- [ ] **Step 4: Add `organization.name`** to `PersonCrudController::configureCrud()->setSearchFields()`.
- [ ] **Step 5: Run** the targeted functional test; expect all cases to pass.

### Task 2: Isolate edits of shared contact values

**Files:**
- Modify: `jardin-sonore-backend/src/Infrastructure/Admin/Form/EmailContactLinkFormType.php`
- Modify: `jardin-sonore-backend/src/Infrastructure/Admin/Form/PhoneContactLinkFormType.php`
- Create: `jardin-sonore-backend/tests/Functional/Infrastructure/Admin/PersonSharedContactEditTest.php`
- Conditional modify: a small shared form subscriber/service only if duplication between the two form types would obscure the rule.

**Interfaces:**
- Consume: `EmailContactLinkEntity::getEmailContact()/setEmailContact()`, `PhoneContactLinkEntity::getPhoneContact()/setPhoneContact()`, contact collections, and `SharedContactLinkResolver::resolveContactDetails()`.
- Produce: form handling that copies a shared contact before submitting a changed email/phone value; unchanged values and link-only properties retain the existing reference.

- [ ] **Step 1: Write failing functional cases** with two directory entries referencing the same email and phone: edit one person's values, then assert the other entry still references the original values and metadata; also edit link-only properties and assert no new contact value is created.
- [ ] **Step 2: Run** `./bin/phpunit tests/Functional/Infrastructure/Admin/PersonSharedContactEditTest.php` from `jardin-sonore-backend/`; expect the isolation cases to fail.
- [ ] **Step 3: Before Symfony writes a changed submitted value**, detach the current link from a contact referenced by another link and attach a new contact entity. Keep both sides of the old and new relationships consistent, including phone links. Apply the same rule to email and phone, then let `SharedContactLinkResolver` deduplicate against an existing value. Do not copy newsletter consent from the old email to a new address.
- [ ] **Step 4: Run** the targeted contact test and the organization navigation test; expect both to pass.

### Task 3: Final verification

**Files:** No product file changes expected.

**Interfaces:** Consumes both completed tasks.

- [ ] **Step 1: Run** targeted PHPUnit tests, `composer cs-check`, `composer stan`, and `git diff --check`; record exact results.
- [ ] **Step 2: Review** authorization, generated URLs and the shared-reference diff. Report modified files and a suggested conventional commit message without committing.
