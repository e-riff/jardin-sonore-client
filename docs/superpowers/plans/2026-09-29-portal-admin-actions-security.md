# Portal Admin Actions Security Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make administrator invitation and password reset actions safe against GET, CSRF, invalid account state and open redirection, and establish whether portal rate limiting uses a trustworthy visitor IP.

**Architecture:** Replace EasyAdmin CRUD links with explicit POST forms and routes, following the existing impersonation action. Keep token creation and mail delivery in their current services. Audit the public proxy → Next.js BFF → Symfony IP chain before changing IP selection.

**Tech Stack:** PHP 8.4, Symfony 8.1, EasyAdmin 5, PHPUnit 12, Next.js.

**Spec:** `docs/superpowers/specs/2026-09-29-admin-account-actions-and-organization-people-design.md`

## Global Constraints

- The backoffice is restricted to `ROLE_ADMIN`; portal users gain no directory write access.
- No schema change or new dependency is expected.
- Do not commit or deploy without the user's explicit request, per `AGENTS.md`.
- Keep UI wording in translations where practical.

## Review Focus

- A forged GET to either sending route must not create a token or send mail (Task 1).
- A valid CSRF for one user/action must fail for another user/action (Task 1).
- An inactive account or an account in the wrong state must be rejected server-side (Task 1).
- A caller-supplied `Referer` must not control the redirect destination (Task 1).
- A caller-supplied first `x-forwarded-for` entry must not become the limiter key if the public proxy preserves it (Task 2).

---

### Task 1: POST account actions

**Files:**
- Modify: `jardin-sonore-backend/src/Infrastructure/Admin/UserCrudController.php`
- Create: `jardin-sonore-backend/templates/admin/action/portal_account_mail.html.twig`
- Modify: `jardin-sonore-backend/tests/Functional/Infrastructure/Admin/UserCrudControllerTest.php`

**Interfaces:**
- Consume: `PortalPasswordTokenManager::issueInvitation(UserEntity): IssuedPortalPasswordToken`, `::issuePasswordReset(UserEntity): IssuedPortalPasswordToken`, and `PortalAccountMailSenderInterface`.
- Produce: POST routes `/backoffice/user/{id}/send-invitation` and `/backoffice/user/{id}/send-password-reset`; CSRF IDs `portal_invitation_{id}` and `portal_password_reset_{id}`.

- [ ] **Step 1: Add failing functional cases** for GET refusal; absent, wrong and cross-action CSRF; wrong account state; correct action form on index/detail/edit; one valid POST issuing one token and one mail; and a malicious `Referer` that still redirects to the account detail URL.
- [ ] **Step 2: Run** `./bin/phpunit tests/Functional/Infrastructure/Admin/UserCrudControllerTest.php` from `jardin-sonore-backend/`. Expect the new cases to fail before implementation.
- [ ] **Step 3: Implement explicit POST routes** with `Request` and `#[MapEntity(id: 'id')] UserEntity`, reject invalid CSRF/state with HTTP 403, and generate the account-detail return URL with `AdminUrlGenerator` or the known internal route.
- [ ] **Step 4: Render each EasyAdmin action as a POST form** using `linkToUrl()`, `renderAsForm()` and the new Twig template, keeping its existing state-dependent visibility. Preserve the existing SMTP failure flash behavior.
- [ ] **Step 5: Run** `./bin/phpunit tests/Functional/Infrastructure/Admin/UserCrudControllerTest.php`; expect all cases to pass.

### Task 2: Verify the rate-limit IP trust boundary

**Files:**
- Inspect: production proxy configuration and request behavior; `jardin-sonore-client/src/lib/portal/api-client-core.ts`; `jardin-sonore-backend/src/Infrastructure/Security/PortalRateLimitKeyResolver.php`
- Conditional modify: the BFF IP resolver and its tests, only if the public proxy accepts a forged `x-forwarded-for` value.
- Document: evidence and conclusion in the task report without printing the shared secret.

**Interfaces:**
- Consume: `portalClientIpFromHeaders(Headers): string | null` and `PortalRateLimitKeyResolver::resolve(Request): string`.
- Produce: a tested, trusted visitor IP selection or documented evidence that the deployed proxy already makes the current selection trustworthy.

- [ ] **Step 1: Inspect the deployed proxy chain read-only** and send a harmless request carrying a chosen `x-forwarded-for` value through the public endpoint. Determine what header Next.js receives and whether it can be forged; do not expose `PORTAL_BFF_SHARED_SECRET`.
- [ ] **Step 2: If spoofing is possible, write a failing test** for the observed header shape, then change the BFF selection to use only the address established by the trusted proxy boundary. If that boundary cannot be established, report the limitation and do not claim a verified fix.
- [ ] **Step 3: Run** the targeted client test plus `npm run lint` and `npm run build` from `jardin-sonore-client/` if client code changes; run `./bin/phpunit tests/Unit/Infrastructure/Security/PortalRateLimitKeyResolverTest.php` from `jardin-sonore-backend/` if the Symfony resolver changes.

### Task 3: Final verification

**Files:** No product file changes expected.

**Interfaces:** Consumes the completed actions and IP audit.

- [ ] **Step 1: Run** targeted PHPUnit tests, `composer cs-check`, `composer stan`, and `git diff --check`; record exact results.
- [ ] **Step 2: Review** the diff for only the specified changes, the route methods and CSRF identifiers, and the IP trust conclusion. Report modified files and a suggested conventional commit message without committing.
