# Portail structures Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver a secure, read-only structures portal in Next.js backed by Symfony-owned authentication, authorization, sessions, session details and PDFs.

**Architecture:** Next.js is a same-origin BFF on `jardin-sonore.fr`; Symfony on `admin.jardin-sonore.fr` owns opaque bearer sessions and validates authorization on every private call. The browser only holds an HTTP-only cookie for Next, and Next proxies document bytes after Symfony authorizes them.

**Tech Stack:** Next.js 16, React 19, TypeScript strict, Tailwind 4, Symfony 8.1, PHP 8.4, Doctrine ORM 3, EasyAdmin 4, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-09-16-portal-structures-design.md`

> **Reprise UI — 2026-09-21.** Les maquettes V4 ont validé la direction éditoriale (une fiche sur surface claire, panneau PDF fonctionnel, listes à séparateurs et rayons contenus). Le plan exécutable de reprise, avec les écarts de contrat API et d’impersonation identifiés, est `docs/superpowers/plans/2026-09-21-portal-structures-ui-rebuild-implementation.md`. Il remplace les tâches client 4 à 6 ci-dessous lors de la reprise ; les tâches backend déjà cochées restent l’historique fiable.

## Global Constraints

- Keep all portal UI copy in `jardin-sonore-client/src/i18n/dictionaries/fr.ts`.
- Do not add a client-side bearer token, CORS policy or shared cross-subdomain cookie.
- Symfony filters authorization in database-backed queries; Next never decides access.
- Persist hashes, never raw access or impersonation tokens; never log tokens.
- Portal access is read-only; portal sessions last 7 days and impersonation sessions 30 minutes.
- Generate and review migrations but do not execute them without explicit user confirmation.
- Stop after every numbered task for user validation. Do not commit without explicit user approval.

---

## File Structure

- Backend portal services and API controllers live in `jardin-sonore-backend/src/Application/Portal/` and `src/Application/Controller/`.
- Doctrine entities/mappings for sessions and impersonation launches live under `src/Infrastructure/Doctrine/Entity/` and `Mapping/`.
- Portal BFF, pages and UI live under `jardin-sonore-client/src/app/portail/` and `src/components/portal/`.
- Shared client API DTOs and server-only API client live under `jardin-sonore-client/src/lib/portal/`.

### Task 1: Backend foundation — opaque portal sessions and Bearer authentication

**Files:** create `PortalSessionEntity`, its Doctrine mapping, `PortalSessionManager`, `PortalAccessTokenAuthenticator`, focused unit tests, and a reviewed Doctrine migration; modify `UserEntity`, security configuration and parameters reference.

**Interfaces:** `PortalSessionManager::create(UserEntity $user, ?AdminUserEntity $impersonator = null, ?DateTimeImmutable $expiresAt = null): IssuedPortalSession`; `authenticate(string $rawToken): PortalAuthenticatedUser`; `revokeForUser(UserEntity $user): void`; `revoke(string $rawToken): void`.

- [x] Write failing PHPUnit tests proving SHA-256-only persistence, expiry rejection, user-status rejection, token revocation and 7-day expiry.
- [x] Run those tests and confirm the missing classes cause failure.
- [x] Implement the entity with `user`, `tokenHash`, `createdAt`, `expiresAt`, `revokedAt`, optional `impersonatedBy`, and the manager using `bin2hex(random_bytes(32))`.
- [x] Add a stateless `/api/portal` firewall using the custom Bearer authenticator; leave only login, reset request and password-token consumption public.
- [x] Generate and inspect the migration. It may create only session data/indexes and foreign keys; do not apply it.
- [x] Run the focused tests, `php bin/console lint:container`, PHPStan and `git diff --check`; stop for validation.

### Task 2: Backend API — credentials, reset, sessions, authorized séance queries and PDF

**Files:** create API request/response DTOs, portal API controllers, `PortalSessionReader`, `PortalSessionAccessService`, repository query methods and functional tests; modify `PortalPasswordTokenManager`, mail sender and parameters.

**Interfaces:** JSON endpoints `POST /api/portal/auth/login`, `POST /api/portal/auth/logout`, `POST /api/portal/auth/password-reset-requests`, `POST /api/portal/password-tokens/{token}/consume`, `GET /api/portal/me`, `GET /api/portal/sessions?organization=<uuid>&page=<n>`, `GET /api/portal/sessions/{uuid}`, `GET /api/portal/sessions/{uuid}/document.pdf`.

- [x] Write failing functional tests for valid/invalid login, neutral reset response, auto-login after password consumption, unauthorized access, multi-organization de-duplication, denied detail/PDF and ready/pending PDF states.
- [x] Implement rate limiting for login/reset, password verification with Symfony's hasher, session issuance/revocation, and `PORTAL_PUBLIC_URL` links in transactional e-mail.
- [x] Implement explicit portal DTOs containing only preview-visible content. Query sessions through the account's active organization accesses, paginate in SQL and expose only authorized organization labels.
- [x] Stream PDFs with Symfony's existing safe filename behavior; return 404 for an unauthorized or unavailable document without revealing whether it exists.
- [x] Run focused functional tests, PHPStan, CS check and container lint; stop for validation.

### Task 3: Backend administrator impersonation

**Files:** create `PortalImpersonationLaunchEntity`, its mapping and its migration alongside the session migration; modify `UserCrudController`, Monolog configuration and relevant tests.

**Interfaces:** an admin-only CSRF-protected POST action issues `IssuedPortalImpersonationLaunch`; `PortalImpersonationLaunchManager::consume(string $rawLaunchToken): IssuedPortalSession` accepts one use within five minutes and creates a 30-minute impersonated session.

- [x] Write failing tests for admin-only issuance, one-time launch consumption, five-minute expiry, 30-minute session cap and security audit context.
- [x] Implement a hidden POST form targeted at a new tab rather than a GET URL token; log issuer, target, issue and consumption to a dedicated `portal_security` channel.
- [x] Add automatic launch/session invalidation for disabled users and preserve the regular read-only portal permission model.
- [x] Review migration scope, run backend tests and static/style checks. Migration `Version20260917120000` was explicitly approved and applied locally.

### Task 4: Next BFF authentication and portal shell

> **État au 2026-09-18.** Les corrections de sécurité BFF ont été implémentées et vérifiées (cookie `__Host-` conforme, rate limiting par IP visiteur authentifiée par secret partagé, runtime standalone et erreurs réseau). Le prototype Next a ensuite été retiré de `main` avant déploiement car son UX n’est pas validée. Reprendre cette tâche depuis les maquettes validées ; ne pas remettre en production les écrans existants tels quels.

**Files:** create server-only `src/lib/portal/api-client.ts`, cookie/session helpers, portal route layout, connection/reset/password pages and actions; modify the public header/footer and FR dictionary; modify `scripts/deploy-client.sh` and deployment example.

**Interfaces:** `PortalApiClient` attaches the bearer token only on server fetches; `getPortalSession()` redirects unauthenticated visitors to `/portail/connexion`; `POST /portail/seances/[uuid]/document.pdf` proxies an authorized PDF response.

- [x] Implement login, logout, reset-request and password-definition server actions. Set `__Host-portal_session` only after successful Symfony responses; clear it on logout/401 and use a session cookie for impersonation.
- [x] Implement the first compact authenticated layout and public « Espace structures » entry, with all wording in `fr.ts`.
- [ ] Add the dedicated unavailable-token and neutral reset confirmation states. Ensure password success redirects to the authenticated list.
- [x] Add `CPANEL_PORTAL_API_BASE_URL` to the deployment flow and pass it as server-only `PORTAL_API_BASE_URL` during the client build; no cPanel routing or CORS configuration is added.

> **Publication :** ces éléments client sont volontairement absents de `main` tant que les maquettes et la recette UX ne sont pas validées. Les réintroduire seulement avec les écrans finalisés.
- [ ] Run `npm run lint` and `npm run build`; manually verify no bearer token appears in HTML, browser storage or network calls; stop for validation.

### Task 5: Next sessions list, detail, PDF and impersonation experience

**Files:** create `src/app/portail/seances/page.tsx`, `[uuid]/page.tsx`, document route and focused portal components; extend i18n dictionary and styles only where necessary.

- [ ] Render the server-fetched, paginated list ordered by date descending, with authorized-organization filter, deduplication handled by Symfony and a useful empty state.
- [ ] Render the responsive detail from the portal DTO: hero, instruments/material, ordered sequences, accessible disclosure for lyrics/gestures, safe external media links, recommendations and PDF state.
- [ ] Proxy the PDF with its original content type/disposition, and display preparation/unavailable state without a generation action.
- [ ] Implement the token-post landing route from the admin form, show the fixed impersonation banner with « Terminer la session », and clear the session on exit.
- [ ] Run lint/build and manually test normal and impersonated navigation on mobile and desktop; stop for validation.

### Task 6: Migration, end-to-end verification and deployment handoff

- [ ] Add a scheduled Symfony command that purges expired or revoked regular portal sessions after a short retention window, while retaining impersonation audit records for the agreed audit period.
- [ ] Re-run `doctrine:migrations:diff --allow-empty-diff` and verify the schema diff contains only portal sessions and impersonation launches.
- [ ] Present the migration for explicit execution approval. Only after approval, run the project migration command and confirm the database version.
- [ ] Run all targeted PHPUnit tests, `npm run lint`, `npm run build`, PHPStan, CS dry-run and `git diff --check`.
- [ ] Follow the manual scenario: invitation → password → portal; login/reset; organization filter; permitted/denied PDF; disabled account; access removal; normal logout; impersonation start/end/expiry.
- [ ] Report changed files, verification evidence and any remaining design-delivery work. Propose, but do not create, the conventional commit `feat(portal): add structures portal`.

## Plan Self-Review

- Security/session foundation is Task 1; account, rights and document API is Task 2; admin test mode is Task 3; BFF and authentication screens are Task 4; session UI is Task 5; migration, verification and handoff are Task 6.
- The plan keeps CORS, browser-held API tokens, edit permissions and PDF generation outside scope.
- All named API endpoints, session lifetimes, token storage rules, deployment variables, stop points and migration constraint match the approved design.
