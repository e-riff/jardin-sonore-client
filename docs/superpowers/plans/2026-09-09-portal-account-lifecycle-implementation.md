# Portal Account Lifecycle Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let administrators maintain portal accounts, safely grant and revoke organization accesses, and deliver usable invitation and password-reset links through a temporary Symfony page.

**Architecture:** A dedicated Doctrine token entity stores only a SHA-256 hash of an opaque random token. `PortalPasswordTokenManager` owns generation, invalidation, validation and consumption; a separate mail sender turns a raw token into an e-mail. EasyAdmin invokes an application service through explicit actions, while a public Symfony controller consumes a valid token and sets the user password with Symfony's configured hasher.

**Tech Stack:** PHP 8.4, Symfony 8.1, Doctrine ORM 3, EasyAdmin 4, Symfony Mailer, Twig, PHPUnit, PHPStan, PHP CS Fixer.

**Spec:** `docs/superpowers/specs/2026-09-09-portal-account-lifecycle-design.md`

**Execution timing:** Deferred to the user's next-day relaunch. Do not implement, apply the migration, or create a commit before that explicit relaunch.

## Global Constraints

- Do not modify the Next.js portal client or add a portal firewall/session yet.
- The portal account e-mail is independent from address-book contact e-mails.
- Persist only a hash of an opaque random token; never log or retain the raw token.
- An invitation activates a pending account after a successful password choice; a reset keeps an active account active.
- A token is single-use, time-limited, and a newly issued token invalidates valid tokens of the same type for that account.
- An inactive account cannot receive either kind of link.
- A user must always retain at least one organization access.
- Generate and review the Doctrine migration, but do not execute it without the user's explicit confirmation.
- Do not create a git commit unless the user explicitly requests one.

---

## File Structure

- `src/Domain/Model/Portal/PasswordTokenType.php`: token-kind enum.
- `src/Infrastructure/Doctrine/Entity/UserPasswordTokenEntity.php`: persistence model for token lifecycle data.
- `src/Infrastructure/Doctrine/Mapping/App.Infrastructure.Doctrine.Entity.UserPasswordTokenEntity.php`: token table mapping and indexes.
- `src/Application/Portal/PortalPasswordTokenManager.php`: secure token lifecycle rules.
- `src/Application/Portal/PortalAccessManager.php`: organization-access removal protection and invitation/reset eligibility.
- `src/Application/Portal/PortalAccountMailSenderInterface.php`: application port for delivery.
- `src/Infrastructure/Mailer/SymfonyPortalAccountMailSender.php`: Mailer adapter and link rendering.
- `src/Application/Controller/PortalPasswordController.php`: public temporary password-choice endpoint.
- `src/Infrastructure/Admin/UserCrudController.php`: readable edition form and contextual actions.
- `src/Infrastructure/Admin/UserOrganizationAccessCrudController.php`: safe access editing semantics.
- `templates/portal_password/set.html.twig` and mail templates: provisional interaction and French messages.
- `migrations/Version20260909120000.php`: reviewed schema for the token table.

### Task 1: Establish the token domain, entity mapping, and schema migration

**Files:**
- Create: `jardin-sonore-backend/src/Domain/Model/Portal/PasswordTokenType.php`
- Create: `jardin-sonore-backend/src/Infrastructure/Doctrine/Entity/UserPasswordTokenEntity.php`
- Create: `jardin-sonore-backend/src/Infrastructure/Doctrine/Mapping/App.Infrastructure.Doctrine.Entity.UserPasswordTokenEntity.php`
- Create: `jardin-sonore-backend/migrations/Version20260909120000.php`
- Create: `jardin-sonore-backend/tests/Unit/Infrastructure/Doctrine/Entity/UserPasswordTokenEntityTest.php`

**Interfaces:**
- Produces `PasswordTokenType::{INVITATION, PASSWORD_RESET}`.
- Produces `UserPasswordTokenEntity::isUsableAt(DateTimeImmutable $now): bool`, `consumeAt(DateTimeImmutable $now): void`, and `invalidateAt(DateTimeImmutable $now): void`.
- `UserPasswordTokenEntity` has `UserEntity $user`, `PasswordTokenType $type`, `string $tokenHash`, `DateTimeImmutable $expiresAt`, `?DateTimeImmutable $consumedAt`, `?DateTimeImmutable $invalidatedAt`.

- [ ] **Step 1: Write failing lifecycle tests**

```php
public function testAUsableTokenCannotBeConsumedTwice(): void
{
    $token = new UserPasswordTokenEntity(/* user, type, hash, future expiry */);
    $now = new DateTimeImmutable('2026-09-09T10:00:00+00:00');

    self::assertTrue($token->isUsableAt($now));
    $token->consumeAt($now);

    self::assertFalse($token->isUsableAt($now));
}
```

Also assert expired and invalidated tokens are unusable, and that an expiry equal to `now` is unusable.

- [ ] **Step 2: Run the focused test and confirm it fails**

Run: `cd jardin-sonore-backend && ./bin/phpunit tests/Unit/Infrastructure/Doctrine/Entity/UserPasswordTokenEntityTest.php`

Expected: failure because the enum and entity do not exist.

- [ ] **Step 3: Implement the enum, entity and explicit Doctrine mapping**

Generate the opaque token outside the entity; keep the entity responsible only for its state transitions. Map a `portal_user_password_token` table with a binary-safe `token_hash` string (64-character SHA-256 hex), token type enum, timestamps, mandatory `user_id` with cascade delete, plus indexes on `token_hash` and `(user_id, type)`.

- [ ] **Step 4: Generate and review the migration without applying it**

Run: `cd jardin-sonore-backend && php bin/console doctrine:migrations:diff`

Replace generated noise with a descriptive `getDescription()` and ensure the migration creates only `portal_user_password_token`, its foreign key and its indexes. Rename it to `Version20260909120000.php` only if that migration version remains unused. Do not execute it; present it for explicit user approval.

- [ ] **Step 5: Run focused verification**

Run: `cd jardin-sonore-backend && ./bin/phpunit tests/Unit/Infrastructure/Doctrine/Entity/UserPasswordTokenEntityTest.php && php bin/console lint:container`

Expected: token behavior tests pass and Doctrine/Symfony can load the new mapping.

### Task 2: Implement secure token issuance, consumption, and account-access rules

**Files:**
- Create: `jardin-sonore-backend/src/Application/Portal/IssuedPortalPasswordToken.php`
- Create: `jardin-sonore-backend/src/Application/Portal/PortalPasswordTokenManager.php`
- Create: `jardin-sonore-backend/src/Application/Portal/PortalAccessManager.php`
- Modify: `jardin-sonore-backend/src/Infrastructure/Doctrine/Entity/UserEntity.php`
- Modify: `jardin-sonore-backend/src/Infrastructure/Doctrine/Entity/UserOrganizationAccessEntity.php`
- Create: `jardin-sonore-backend/tests/Unit/Application/Portal/PortalPasswordTokenManagerTest.php`
- Create: `jardin-sonore-backend/tests/Unit/Application/Portal/PortalAccessManagerTest.php`

**Interfaces:**
- Produces `IssuedPortalPasswordToken::__construct(UserPasswordTokenEntity $tokenEntity, string $rawToken)`; callers use `rawToken` only to send the e-mail.
- Produces `PortalPasswordTokenManager::issueInvitation(UserEntity $user): IssuedPortalPasswordToken`, `issuePasswordReset(UserEntity $user): IssuedPortalPasswordToken`, `findUsable(string $rawToken): ?UserPasswordTokenEntity`, and `consumeWithPassword(UserPasswordTokenEntity $token, string $plainPassword): void`.
- Produces `PortalAccessManager::removeAccess(UserEntity $user, UserOrganizationAccessEntity $access): void`.
- `UserEntity` implements `PasswordAuthenticatedUserInterface`; `getPassword()` returns an empty string only when no password is set, so Symfony's hasher can operate before activation.

- [ ] **Step 1: Write failing token-manager tests**

```php
public function testIssuingAReplacementInvitationInvalidatesThePreviousOne(): void
{
    $first = $manager->issueInvitation($pendingUser);
    $second = $manager->issueInvitation($pendingUser);

    self::assertFalse($first->tokenEntity->isUsableAt($clockNow));
    self::assertTrue($second->tokenEntity->isUsableAt($clockNow));
    self::assertNotSame($first->rawToken, $second->rawToken);
}
```

Cover invalid target state (`INACTIVE`, reset on pending, invitation on active), SHA-256 hash lookup rather than raw token storage, password hashing, invitation activation, reset state preservation, and no second consumption. Add access-manager tests for deleting a non-last access and rejection of the last access.

- [ ] **Step 2: Run the two test files and confirm they fail**

Run: `cd jardin-sonore-backend && ./bin/phpunit tests/Unit/Application/Portal/PortalPasswordTokenManagerTest.php tests/Unit/Application/Portal/PortalAccessManagerTest.php`

Expected: failure because the manager, access service and password hashing integration do not exist.

- [ ] **Step 3: Implement the managers with injected clock, hasher and entity manager**

Inject `ClockInterface`, `UserPasswordHasherInterface` and `EntityManagerInterface` into `PortalPasswordTokenManager`. Use `bin2hex(random_bytes(32))` as the raw URL token and `hash('sha256', $rawToken)` for the stored value. In one transaction, invalidate usable tokens with the same user/type, persist the replacement and flush. `consumeWithPassword()` re-checks usability, hashes through `UserPasswordHasherInterface::hashPassword($user, $plainPassword)`, consumes the token and calls `$user->setStatus(UserStatus::ACTIVE)` only for invitation tokens.

In `PortalAccessManager`, count the current collection before removing; throw `LogicException` if it is the last access and otherwise remove it from the owning user collection.

- [ ] **Step 4: Run focused tests and static analysis**

Run: `cd jardin-sonore-backend && ./bin/phpunit tests/Unit/Application/Portal/PortalPasswordTokenManagerTest.php tests/Unit/Application/Portal/PortalAccessManagerTest.php && vendor/bin/phpstan analyse --memory-limit=1G --debug`

Expected: all lifecycle and last-access tests pass; PHPStan reports no errors.

### Task 3: Add mail delivery and the temporary public password page

**Files:**
- Create: `jardin-sonore-backend/src/Application/Portal/PortalAccountMailSenderInterface.php`
- Create: `jardin-sonore-backend/src/Infrastructure/Mailer/SymfonyPortalAccountMailSender.php`
- Create: `jardin-sonore-backend/src/Application/Form/PortalSetPasswordType.php`
- Create: `jardin-sonore-backend/src/Application/Controller/PortalPasswordController.php`
- Create: `jardin-sonore-backend/templates/portal_password/set.html.twig`
- Create: `jardin-sonore-backend/templates/portal_password/email.html.twig`
- Modify: `jardin-sonore-backend/config/parameters.yaml.dist`
- Modify: `jardin-sonore-backend/config/packages/security.php`
- Create: `jardin-sonore-backend/tests/Functional/Application/Controller/PortalPasswordControllerTest.php`
- Create: `jardin-sonore-backend/tests/Unit/Infrastructure/Mailer/SymfonyPortalAccountMailSenderTest.php`

**Interfaces:**
- Produces `PortalAccountMailSenderInterface::sendInvitation(UserEntity $user, string $rawToken): void` and `sendPasswordReset(UserEntity $user, string $rawToken): void`.
- Produces public route `portal_password_set` at `/portail/definir-mot-de-passe/{token}` for GET and POST.
- Adds `app.portal.password_link_ttl` (seconds) and `app.portal.public_base_url` to `parameters.yaml.dist`.

- [ ] **Step 1: Write failing mail and controller tests**

```php
public function testValidInvitationSetsPasswordActivatesUserAndConsumesToken(): void
{
    $client->request('GET', "/portail/definir-mot-de-passe/{$rawToken}");
    $client->submitForm('Définir mon mot de passe', [
        'portal_set_password[password][first]' => 'Passphrase très solide 2026',
        'portal_set_password[password][second]' => 'Passphrase très solide 2026',
    ]);

    self::assertResponseIsSuccessful();
    self::assertSame(UserStatus::ACTIVE, $user->getStatus());
    self::assertNotNull($token->getConsumedAt());
}
```

Also cover an invalid/expired/reused token yielding the same neutral response, CSRF-protected form errors for mismatched passwords, and e-mail subject/recipient/link for both message types.

- [ ] **Step 2: Run the focused tests and confirm they fail**

Run: `cd jardin-sonore-backend && ./bin/phpunit tests/Functional/Application/Controller/PortalPasswordControllerTest.php tests/Unit/Infrastructure/Mailer/SymfonyPortalAccountMailSenderTest.php`

Expected: failure because neither controller nor sender exists.

- [ ] **Step 3: Implement the sender, form and controller**

Use `MailerInterface`, `Address`, `Email`, Twig and `UrlGeneratorInterface`; construct the absolute URL from `app.portal.public_base_url` plus the generated route path. Render one concise French template whose heading/body varies by token type. Add a repeated `PasswordType` form field with Symfony's `RepeatedType`, `NotBlank`, `Length(min: 12)` and `invalid_message`.

Make the route `PUBLIC_ACCESS` before the existing catch-all admin rule. On GET, resolve the token through `findUsable()`. On successful POST, call `consumeWithPassword()` and render a success state; never authenticate the user. For invalid state, return the identical neutral 404-style template/status without stating whether an account exists.

- [ ] **Step 4: Run focused verification**

Run: `cd jardin-sonore-backend && ./bin/phpunit tests/Functional/Application/Controller/PortalPasswordControllerTest.php tests/Unit/Infrastructure/Mailer/SymfonyPortalAccountMailSenderTest.php && php bin/console lint:container`

Expected: valid invitation/reset flows work, invalid token information stays neutral, and mail/template services compile.

### Task 4: Make the EasyAdmin edit page operational and add safe actions

**Files:**
- Modify: `jardin-sonore-backend/src/Infrastructure/Admin/UserCrudController.php`
- Modify: `jardin-sonore-backend/src/Infrastructure/Admin/UserOrganizationAccessCrudController.php`
- Modify: `jardin-sonore-backend/src/Infrastructure/Doctrine/Entity/UserEntity.php`
- Create: `jardin-sonore-backend/tests/Functional/Infrastructure/Admin/UserCrudControllerTest.php`
- Create: `jardin-sonore-backend/tests/Functional/Infrastructure/Admin/UserOrganizationAccessCrudControllerTest.php`

**Interfaces:**
- Adds EasyAdmin entity actions `sendInvitation`, `resendInvitation`, and `sendPasswordReset` linked to the existing account page.
- `UserCrudController::sendInvitation(AdminContext $context): Response`, `resendInvitation(AdminContext $context): Response`, and `sendPasswordReset(AdminContext $context): Response` issue a token, send it, redirect to the account detail/edit page and add a flash message.
- The edit form exposes `email`, `status` and `organizationAccesses`; `email` does not invoke any address-book update.

- [ ] **Step 1: Write failing functional tests for edition and actions**

```php
public function testEditingPortalEmailDoesNotEditDirectoryEmail(): void
{
    // Submit the EasyAdmin edit form with a new portal email.
    // Reload the linked EmailContactEntity.
    self::assertSame('new.portal@example.test', $userEntity->getEmail());
    self::assertSame('directory@example.test', $emailContactEntity->getEmailAddress());
}
```

Cover only invitation actions for pending users, only reset for active users, no actions for inactive users, a successful sender call/flash redirect, duplicate structure rejection and the last-access removal rejection.

- [ ] **Step 2: Run the focused functional tests and confirm they fail**

Run: `cd jardin-sonore-backend && ./bin/phpunit tests/Functional/Infrastructure/Admin/UserCrudControllerTest.php tests/Functional/Infrastructure/Admin/UserOrganizationAccessCrudControllerTest.php`

Expected: failure because the fields, actions and protected delete path are absent.

- [ ] **Step 3: Implement the focused EasyAdmin behavior**

On edit, show a form panel for the portal identity (`email`, `status`) and a second panel for accesses. Keep creation-only fields hidden. Allow deleting a collection row only when more than one exists and call `PortalAccessManager` from the CRUD delete/removal path so the server remains authoritative. Leave `person` as read-only for existing access; add a structure through the existing access form and rely on the database unique constraint plus a clear French validation error for duplicates.

Add contextual actions to index/detail/edit views. Each action checks eligibility through the application service, issues before sending, and if `MailerInterface` raises, records a user-visible error and leaves the new token usable for a retry. Do not expose raw tokens in flash messages, logs or redirects.

- [ ] **Step 4: Run focused tests and check rendered forms manually**

Run: `cd jardin-sonore-backend && ./bin/phpunit tests/Functional/Infrastructure/Admin/UserCrudControllerTest.php tests/Functional/Infrastructure/Admin/UserOrganizationAccessCrudControllerTest.php && node --check assets/controllers/portal_access_email_controller.js`

Expected: the editor is sectioned, portal e-mail remains independent, action visibility matches status, and server-side rules reject invalid access removal.

### Task 5: Apply approved migration, verify the end-to-end flow, and prepare handoff

**Files:**
- Modify: `jardin-sonore-backend/docs/portal-access-todo.md`
- Test: `jardin-sonore-backend/tests/Unit/Application/Portal/`
- Test: `jardin-sonore-backend/tests/Functional/`

**Interfaces:**
- Consumes every interface above; produces a testable EasyAdmin-to-mail-to-password-page lifecycle.

- [ ] **Step 1: Re-check migration scope and request execution confirmation**

Run: `cd jardin-sonore-backend && php bin/console doctrine:migrations:diff --allow-empty-diff && git diff -- migrations/Version20260909120000.php`

Expected: no additional schema change is proposed and the migration only creates the password-token table. Ask the user to approve execution before the next step.

- [ ] **Step 2: Execute the migration only after approval**

Run: `cd jardin-sonore-backend && make backend-migrate`

Expected: Doctrine applies `Version20260909120000` and reports the database at its latest version.

- [ ] **Step 3: Run the full relevant verification suite**

Run: `cd jardin-sonore-backend && ./bin/phpunit tests/Unit/Application/Portal tests/Unit/Infrastructure/Doctrine/Entity/UserPasswordTokenEntityTest.php tests/Functional/Application/Controller/PortalPasswordControllerTest.php tests/Functional/Infrastructure/Admin/UserCrudControllerTest.php tests/Functional/Infrastructure/Admin/UserOrganizationAccessCrudControllerTest.php && php bin/console lint:container && vendor/bin/phpstan analyse --memory-limit=1G --debug && vendor/bin/php-cs-fixer fix --dry-run --diff --config=.php-cs-fixer.dist.php --path-mode=intersection src/Application/Portal src/Application/Controller/PortalPasswordController.php src/Infrastructure/Admin/UserCrudController.php src/Infrastructure/Admin/UserOrganizationAccessCrudController.php src/Infrastructure/Doctrine/Entity/UserEntity.php src/Infrastructure/Doctrine/Entity/UserPasswordTokenEntity.php src/Infrastructure/Mailer/SymfonyPortalAccountMailSender.php && git diff --check`

Expected: all targeted tests, container validation, static analysis, style check and whitespace validation pass.

- [ ] **Step 4: Document the manual test sequence**

Update `jardin-sonore-backend/docs/portal-access-todo.md` with: configure `MAILER_DSN`, `DEFAULT_CONTACT`, `MAILING_FROM_NAME` and `app.portal.public_base_url`; create a pending account from a structure; send invitation; inspect Mailpit; open the received URL; set password; confirm account becomes active; request a reset; and verify an old link is refused after resend.

- [ ] **Step 5: Present the diff and propose a commit only**

Run: `git status --short && git diff --check`

Report files changed, verification evidence, migration result and the manual test steps. Offer `feat(portal): add account invitation lifecycle` as a conventional commit message, but do not commit without explicit user approval.

## Plan Self-Review

- Spec coverage: Task 1 and Task 2 cover durable, hashed, expiring one-time tokens and the last-access rule. Task 3 covers provisional public password selection, CSRF, password hashing and e-mail delivery. Task 4 covers editable identity, structures and status-specific EasyAdmin actions. Task 5 covers migration approval, complete verification and manual testing.
- Completeness scan: no unfinished marker, deferred implementation wording or unspecified test requirement remains.
- Type consistency: `PasswordTokenType`, `UserPasswordTokenEntity`, `IssuedPortalPasswordToken`, `PortalPasswordTokenManager`, `PortalAccessManager`, and `PortalAccountMailSenderInterface` are introduced before their consumers and use the same names throughout.
