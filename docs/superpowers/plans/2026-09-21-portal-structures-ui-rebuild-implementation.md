# Portail structures UI rebuild Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the read-only structures portal UI from the validated V4 visual direction, while preserving Symfony as the sole authority for authentication, authorization, sessions and documents.

**Architecture:** Next.js remains a same-origin server BFF. Server components fetch typed portal DTOs with the opaque bearer token stored only in a `__Host-` HTTP-only cookie; client components are limited to forms and disclosure interactions. The portal uses one editorial content sheet for a session detail, one functional document panel, and separators rather than nested rounded cards.

**Tech Stack:** Next.js 16, React 19, TypeScript strict, Tailwind 4, Node test runner, Symfony 8.1, Doctrine ORM 3, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-09-16-portal-structures-design.md`; visual source (local, deliberately unversioned): `.codex/design portal/4/`.

## Global Constraints

- Keep Symfony authoritative; Next never filters access, resolves access rights, or exposes a bearer token to browser JavaScript, HTML, logs or storage.
- Keep the portal read-only: no creation, edit, deletion, messaging workflow, analytics or PDF generation.
- Keep all customer-facing French copy in `jardin-sonore-client/src/i18n/dictionaries/fr.ts`.
- Keep `__Host-portal_session` `HttpOnly`, `Secure`, `SameSite=Lax`, path `/`, host-only; regular sessions live 7 days and impersonation sessions are browser sessions.
- Use the V4 hierarchy: warm app background, a single white/near-white editorial sheet for session detail, a separate functional PDF panel, restrained 4–8px radii, dark text for body copy, brown/coral only for accents and section labels.
- Do not reintroduce a public « Espace structures » entry or `/portail/**` on `main` until the completed browser recipe is accepted.
- Do not add a dependency merely for portal styling, date formatting, fetching, or state management.

## Review Focus

- A user with two authorized structures must see only the requested structure’s sessions; an unknown organization UUID must not reveal data.
- A revoked/expired session must clear the cookie and redirect through the existing invalid-session flow, including while downloading a PDF.
- A document in preparation or absent must have a calm UI state and must never present a download link.
- Long titles, organization names, notes and sequence text must wrap without overflowing the sheet, actions or mobile viewport.
- An impersonation launch token is one-time, is never rendered into a GET URL or client bundle, creates a non-persistent session, and leaves a visible termination action.

## File Structure

- `jardin-sonore-client/src/lib/portal/types.ts`: front-only, typed representations of the existing portal JSON contract.
- `jardin-sonore-client/src/lib/portal/api-client.ts`: authenticated server fetches for list, detail, document and impersonation launch consumption.
- `jardin-sonore-client/src/lib/portal/session.ts` and `cookie-options.ts`: HTTP-only session and impersonation marker lifecycle.
- `jardin-sonore-client/src/components/portal/`: small visual units only: authentication frame, account header, content sheet, session row, document panel, sequence disclosure and impersonation banner.
- `jardin-sonore-client/src/app/portail/`: server pages and route handlers; no portal domain data is fetched from a client component.
- `jardin-sonore-backend/src/Application/Controller/PortalApiController.php` and `src/Application/Portal/PortalSessionResponse.php`: only the narrowly required portal-facing DTO/launch endpoint additions, with backend functional tests.

### Task 1: Freeze the portal JSON contract needed by the V4 screens

**Files:**
- Create: `jardin-sonore-client/src/lib/portal/types.ts`
- Modify: `jardin-sonore-client/src/lib/portal/api-client.ts`
- Modify if required by the client rendering audit: `jardin-sonore-backend/src/Application/Portal/PortalSessionResponse.php`, `jardin-sonore-backend/src/Application/Controller/PortalApiController.php`
- Test: `jardin-sonore-client/tests/portal-security.test.ts`, `jardin-sonore-backend/tests/Functional/Application/Controller/PortalApiControllerTest.php`

**Interfaces:**
- `PortalSessionListResponse` contains `items`, `pagination.page`, `pagination.pageSize`, `pagination.total`.
- `PortalSessionSummary` contains only `uuid`, `title`, `sessionDate`, authorized `organizations`, optional `theme`, and `documentStatus`.
- `PortalSessionDetail` adds text and ordered sequence representations needed by the V4 sheet. Never make the client resolve catalog UUIDs into private data.
- `PortalApiClient.sessions(organizationUuid?: string, page?: number)` and `PortalApiClient.session(uuid: string)` perform authenticated server-only GET requests.

- [ ] Add Node tests that assert list/detail requests URL-encode UUIDs, attach the bearer token, use `no-store`, and turn transport failures into `PortalApiUnavailableError`.
- [ ] Run `node --test tests/portal-security.test.ts`; confirm the new methods are initially absent.
- [ ] Add the minimal TypeScript DTOs and API client methods. Keep JSON parsing nullable until the page validates the response shape through types and response status.
- [ ] Inspect an actual representative backend response. If it only contains catalog UUIDs for copy that V4 displays as names or links, add a portal-specific, authorized display DTO on Symfony; do not issue extra client requests to catalog endpoints and do not leak non-public catalog fields.
- [ ] Add or extend the backend functional test for the exact public portal response. Assert unauthorized session detail stays a 404 and document status remains the only PDF availability signal.
- [ ] Run `node --test tests/portal-security.test.ts` and `./bin/phpunit tests/Functional/Application/Controller/PortalApiControllerTest.php` from `jardin-sonore-backend`.
- [ ] Commit the contract increment: `feat(portal): expose typed session data for structures UI`.

### Task 2: Establish the V4 portal shell and visual primitives

**Files:**
- Create: `jardin-sonore-client/src/components/portal/PortalAccountHeader.tsx`
- Create: `jardin-sonore-client/src/components/portal/PortalContentSheet.tsx`
- Create: `jardin-sonore-client/src/components/portal/PortalDocumentPanel.tsx`
- Create: `jardin-sonore-client/src/components/portal/PortalImpersonationBanner.tsx`
- Modify: `jardin-sonore-client/src/app/portail/(authenticated)/layout.tsx`
- Modify: `jardin-sonore-client/src/app/globals.css`
- Modify: `jardin-sonore-client/src/i18n/dictionaries/fr.ts`

**Interfaces:**
- `PortalContentSheet({children}: {children: ReactNode})` owns the single desktop detail-sheet surface; it must not be used as a card around every section.
- `PortalDocumentPanel({status, sessionUuid}: {status: PortalDocumentStatus; sessionUuid: string})` owns the three document states and only emits a document link for `ready`.
- `PortalImpersonationBanner({onTerminate}: {onTerminate: () => Promise<void>})` describes temporary admin preview and exposes one termination action.

- [ ] Add the translation keys for the dedicated shell, document states, list/detail labels, empty state and impersonation wording before consuming them.
- [ ] Implement a compact account header with logo, visible account identity and a text-first logout action; no public marketing navigation or footer.
- [ ] Implement the V4 visual tokens in the existing Tailwind/global style system: darker body text, restrained radii, visible `:focus-visible`, one editorial sheet surface, subtle separators, and no new font or CSS framework.
- [ ] Implement the four focused primitives. Keep the PDF panel and the sheet as the only elevated surfaces in the session detail; make all other detail sections typographic sections separated by rules.
- [ ] Verify at 375px, 768px and 1280px that long identity/title text wraps, the PDF panel stacks after content on mobile, and no horizontal scroll appears.
- [ ] Run `npm run lint` in `jardin-sonore-client`.
- [ ] Commit the visual foundation: `feat(portal): add structures portal v4 shell`.

### Task 3: Rebuild the authentication, reset and unavailable screens

**Files:**
- Create: `jardin-sonore-client/src/components/portal/PortalAuthenticationFrame.tsx`
- Modify: `jardin-sonore-client/src/app/portail/connexion/page.tsx`
- Modify: `jardin-sonore-client/src/app/portail/reinitialiser-mot-de-passe/page.tsx`
- Modify: `jardin-sonore-client/src/app/portail/definir-mot-de-passe/[token]/page.tsx`
- Modify: `jardin-sonore-client/src/app/portail/indisponible/page.tsx`
- Modify: `jardin-sonore-client/src/components/portal/PortalLoginForm.tsx`
- Modify: `jardin-sonore-client/src/components/portal/PortalResetRequestForm.tsx`
- Modify: `jardin-sonore-client/src/components/portal/PortalPasswordForm.tsx`
- Modify: `jardin-sonore-client/src/i18n/dictionaries/fr.ts`

**Interfaces:**
- Existing server actions stay the sole submit path: `loginPortalAction`, `requestPortalPasswordResetAction`, `definePortalPasswordAction`.
- Login error is one generic message; reset confirmation is neutral whether the e-mail is known or not; an unavailable password token has an explicit path back to reset.

- [ ] Make the shared frame match the V4 calm form surface without duplicating layout/CSS across three pages.
- [ ] Keep form fields labelled, with `name`, `type`, `autocomplete`, visible focus, inline error state and an enabled submit button until the request begins.
- [ ] Replace all prototype wording that exposes account existence, asserts an unsupported time limit, promises an operation is running, or says the portal itself is read-only as a disclaimer.
- [ ] Keep reset confirmation neutral and preserve automatic authenticated redirect after a successful password definition.
- [ ] Use the unavailable screen only for transport/service failure; provide retry and contact actions without claiming a specific recovery time.
- [ ] Run `npm run lint` and manually exercise login error, reset confirmation, expired token and service-unavailable render states at mobile width.
- [ ] Commit the auth screens: `feat(portal): rebuild structures authentication screens`.

### Task 4: Build the authorized sessions index, filtering and empty state

**Files:**
- Create: `jardin-sonore-client/src/app/portail/(authenticated)/seances/page.tsx`
- Create: `jardin-sonore-client/src/components/portal/PortalSessionList.tsx`
- Create: `jardin-sonore-client/src/components/portal/PortalSessionListItem.tsx`
- Create: `jardin-sonore-client/src/components/portal/PortalOrganizationFilter.tsx`
- Modify: `jardin-sonore-client/src/lib/portal/api-client.ts`
- Modify: `jardin-sonore-client/src/i18n/dictionaries/fr.ts`

**Interfaces:**
- Read `organization` and `page` from page search parameters; pass them unchanged only after validating that the selected organization belongs to `PortalAccount.organizations`.
- The Symfony response remains authoritative: it supplies pagination and de-duplicates shared sessions.
- `PortalSessionListItem` links to `/portail/seances/{uuid}` and shows date, authorized organization labels, title, optional theme and document state.

- [ ] Implement a server page that calls `getPortalSession()` then uses `PortalApiClient` with the HTTP-only token; send API transport failure to `/portail/indisponible`, 401 to invalid-session handling, and non-OK list responses to the safe unavailable state.
- [ ] Render a compact native select only for accounts with more than one organization. Keep selection and pagination in URL search parameters so refresh/back/share behavior stays coherent.
- [ ] Implement a separator-led list, not a pile of cards. A single latest-session emphasis is allowed only if labelled; otherwise all rows use the same treatment.
- [ ] Implement the approved empty state: short, warm, no implied promise of upload timing, and one discrete `mailto:` contact action.
- [ ] Render PDF state as text/badge only: `ready`, `pending`, `unavailable`; no document action except on a ready detail page.
- [ ] Run `npm run lint` and use browser checks with a one-organization account, multi-organization account, empty list, pending PDF and a long organization name.
- [ ] Commit the index: `feat(portal): add authorized sessions index`.

### Task 5: Build the session detail and secure PDF affordance

**Files:**
- Create: `jardin-sonore-client/src/app/portail/(authenticated)/seances/[uuid]/page.tsx`
- Create: `jardin-sonore-client/src/components/portal/PortalSessionDetail.tsx`
- Create: `jardin-sonore-client/src/components/portal/PortalSessionSequence.tsx`
- Modify: `jardin-sonore-client/src/components/portal/PortalDocumentPanel.tsx`
- Modify: `jardin-sonore-client/src/app/portail/seances/[uuid]/document.pdf/route.ts`
- Modify: `jardin-sonore-client/src/i18n/dictionaries/fr.ts`

**Interfaces:**
- Detail page consumes only `PortalSessionDetail` from `PortalApiClient.session(uuid)`.
- Document panel uses the existing same-origin `GET /portail/seances/{uuid}/document.pdf` route; it never calls Symfony from browser code.
- Sequence disclosure uses native `<details>/<summary>` when lyrics/gestures exist; absent optional data renders nothing rather than an empty heading.

- [ ] Fetch the detail in a server component and map 404 to `notFound()` without exposing whether the session exists; map 401 and transport failures as in Task 4.
- [ ] Render the V4 editorial sheet: session metadata, title, optional pedagogical intention, material, ordered sequences, optional safe external links and the further-exploration callout. Use text sections/rules rather than cards.
- [ ] Render all three PDF states. Only `ready` has a labelled download link; pending/unavailable states explain the situation without a generation CTA.
- [ ] Preserve the original `Content-Type` and `Content-Disposition` in the proxy route, clear a rejected session, and never buffer document bytes in a client component.
- [ ] Test narrow and wide detail layouts with a very long title, no notes, no material, no sequences, ready document and pending document.
- [ ] Run `npm run lint`, `npm run build`, and manually download one authorized document plus attempt a denied/missing UUID.
- [ ] Commit the detail: `feat(portal): add structures session detail`.

### Task 6: Complete the one-time impersonation handoff and its visible exit

**Files:**
- Modify: `jardin-sonore-backend/src/Application/Controller/PortalApiController.php`
- Modify: `jardin-sonore-backend/tests/Functional/Application/Controller/PortalApiControllerTest.php`
- Create: `jardin-sonore-client/src/app/portail/impersonation/route.ts`
- Modify: `jardin-sonore-client/src/lib/portal/api-client.ts`
- Modify: `jardin-sonore-client/src/lib/portal/cookie-options.ts`
- Modify: `jardin-sonore-client/src/lib/portal/session.ts`
- Modify: `jardin-sonore-client/src/app/portail/(authenticated)/layout.tsx`
- Modify: `jardin-sonore-client/src/app/portail/actions.ts`
- Modify: `jardin-sonore-client/tests/portal-security.test.ts`

**Interfaces:**
- Symfony exposes a one-time `POST /api/portal/impersonation-launches/{token}/consume` endpoint that delegates only to `PortalImpersonationLaunchManager::consume()` and returns an opaque portal session token.
- Next’s `POST /portail/impersonation` consumes `launchToken` server-side, writes the non-persistent portal cookie plus an HTTP-only host-only impersonation marker, then redirects to `/portail/seances`.
- `terminatePortalImpersonationAction()` revokes/clears the session and marker then redirects to `/portail/connexion`.

- [ ] Add backend functional tests for valid one-time launch consumption, reused/expired launch rejection, and no launch token in response bodies or redirect URLs.
- [ ] Implement the narrow Symfony endpoint without changing authorization rules, session duration, logging or the existing admin POST launch form.
- [ ] Add the client API method and route handler. Read `launchToken` from POST form data only; reject empty/malformed input with the safe unavailable route and never log it.
- [ ] Extend the cookie helper tests: normal sessions have a seven-day lifetime, impersonation sessions and marker are `HttpOnly`/`Secure`/host-only/session cookies, and both cookies are cleared on exit.
- [ ] Render the compact V4 impersonation banner in the authenticated layout only while the marker exists. Its only special action is « Terminer la session d’aperçu »; do not add editing affordances.
- [ ] Run `node --test tests/portal-security.test.ts`, the focused backend controller test, `npm run lint`, and a browser scenario from the EasyAdmin launch through explicit exit.
- [ ] Commit the handoff: `feat(portal): complete secure admin preview`.

### Task 7: Acceptance, production-return gate and cleanup policy

**Files:**
- Modify: `docs/superpowers/plans/2026-09-16-portal-structures-implementation.md`
- Modify if needed: `jardin-sonore-client/scripts/deploy-client.sh`, deployment documentation and parameter examples

- [ ] Verify environment inputs are server-only and present in the production standalone runtime: `PORTAL_API_BASE_URL` and `PORTAL_BFF_SHARED_SECRET`; no `NEXT_PUBLIC_` equivalent exists.
- [ ] Run `npm run lint`, `npm run build`, `node --test tests/portal-security.test.ts`, focused backend portal tests, PHPStan/CS checks appropriate to backend changes, and `git diff --check`.
- [ ] Run the browser acceptance recipe: invitation → password choice → automatic session → list; normal login/reset; one/multiple structure filters; empty list; ready/pending PDF; denied URL; disabled account; logout; admin preview launch/exit/expiry.
- [ ] Inspect browser HTML, storage and network requests; verify no bearer or launch token is visible outside the HTTP-only cookie/BFF exchange.
- [ ] Confirm the V4 design at 375px, 768px and 1280px, including focus navigation and long-content wrapping.
- [ ] Only after explicit approval, reintroduce the public entry point and portal deployment configuration to `main`; commit and tag using the project deployment rules.

## Plan Self-Review

- The plan starts by freezing the API contract because the prototype exposes UUID-only detail references that the V4 UI cannot responsibly label itself.
- Visual work is isolated into shell/primitives, auth, list, detail and admin-preview tasks so each can be reviewed and tested independently.
- The plan keeps user-facing data server-fetched, read-only and authorization-controlled by Symfony; it adds no client token, CORS policy, shared cross-subdomain cookie or PDF-generation behavior.
- The V4 content sheet is one page-level surface, not a return to nested cards.
