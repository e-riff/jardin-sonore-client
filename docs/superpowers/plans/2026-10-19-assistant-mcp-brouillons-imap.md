# MCP IMAP Reply Drafts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Permettre à Mistral de consulter les réponses envoyées liées à un mail reçu et d'enregistrer, après approbation, une réponse dans le dossier Brouillons IMAP partagé avec Thunderbird et Roundcube.

**Architecture:** Le lecteur IMAP existant fournit un contexte de réponse et une recherche bornée dans Envoyés. Un service de brouillon fabrique le MIME, réserve une clé de source unique en base et ajoute le message au dossier IMAP configuré ; une relance réconcilie par `Message-ID` déterministe sans écraser un brouillon édité. Le registre MCP expose une lecture et une écriture distinctes, cette dernière protégée par un drapeau dédié.

**Tech Stack:** PHP >=8.4, Symfony 8.1, Doctrine ORM 3 / Migrations 4, Webklex PHP-IMAP 6.2, PHPUnit 12.

**Spec:** [Conception des brouillons IMAP](../specs/2026-10-19-assistant-mcp-brouillons-imap-design.md)

**État au 19 octobre 2026 :** implémentation et déploiements réalisés ; les tests automatisés et contrôles production sont consignés dans la section « Réalisation et recette ». Il reste la recette utilisateur des brouillons dans Thunderbird/Roundcube. Les pièces jointes sont un chantier séparé et ne font pas partie de ce plan.

## Global Constraints

- `save_mail_draft` accepte `mailReference` et `body` texte non vide, de 10 000 caractères au plus ; le destinataire et les en-têtes sont calculés côté serveur.
- Le brouillon est `text/plain` UTF-8, porte `\Draft`, `In-Reply-To`/`References` valides et n'est jamais envoyé par le MCP.
- `find_sent_replies` retourne au plus trois réponses liées par les en-têtes du mail source ; `INBOX` reste le seul dossier de recherche des mails reçus.
- `MCP_DRAFT_WRITE_ENABLED=0` par défaut ; l'outil Mistral reste sur « Demander ». Les écritures commerciales existantes restent indépendantes.
- Les chemins IMAP Envoyés et Brouillons sont configurés explicitement et vérifiés ; aucun dossier n'est créé ou deviné par l'IA.
- Pas de nouvelle dépendance. Nouveaux fichiers PHP strictement typés ; noms métier en anglais. Une migration Doctrine est générée puis relue, et son exécution locale demande l'accord prévu par `AGENTS.md`.

## Review Focus

1. Un `Reply-To` multiple ou contenant des retours de ligne doit arrêter la création avant IMAP (Task 1).
2. Un formulaire auto-adressé à `contact@jardin-sonore.fr` doit être refusé comme source de réponse (Task 1).
3. Un sujet `Re: ...` accentué et un corps avec retours de ligne ne doivent ni doubler le préfixe ni injecter d'en-tête (Task 2).
4. Une réponse IMAP incertaine après `APPEND` ne doit pas produire de second brouillon au rappel (Task 3).
5. Un dossier Brouillons absent ou un brouillon supprimé manuellement doit échouer sans création silencieuse de dossier ou de doublon (Tasks 2 et 3).

---

### Task 1: Contexte de réponse et lecture bornée des messages envoyés

**Files:**
- Create: `jardin-sonore-backend/src/Application/Mcp/ReplyContext.php`
- Modify: `jardin-sonore-backend/src/Application/Mcp/MailReader.php`, `jardin-sonore-backend/src/Infrastructure/Mail/ImapMailReader.php`, `jardin-sonore-backend/src/Application/Mcp/ReadToolRegistry.php`, `jardin-sonore-backend/config/parameters.yaml.dist`, `jardin-sonore-backend/.env`, `jardin-sonore-backend/.env.test`
- Test: `jardin-sonore-backend/tests/Unit/Infrastructure/Mail/ImapMailReaderTest.php`, `jardin-sonore-backend/tests/Unit/Application/Mcp/ReadToolRegistryTest.php`

**Interfaces:**
- Produces: `MailReader::replyContext(string $mailReference): ReplyContext` et `MailReader::findSentReplies(string $mailReference, int $limit): array` ; `ReplyContext` expose `sourceKey: string`, `messageId: ?string`, `subject: ?string`, `replyRecipient: string`, `references: ?string` et `mailReference: string` en propriétés `readonly`. La recherche retourne `array{matchable: bool, replies: list<array{date: string, to: string, subject: ?string, body: string}>}`.
- `ReadToolRegistry` ajoute `find_sent_replies` avec `mailReference` obligatoire et limite interne de trois ; sa sortie distingue « non comparable » d'« aucune réponse trouvée ».

- [x] **Step 1: Write failing tests.** Vérifier `Reply-To` valide, repli sur `From`, `Reply-To` multiple/injecté, auto-adressage, référence UID périmée, `Message-ID` manquant, trois réponses maximum dans Envoyés et maintien de l'état non lu.
- [x] **Step 2: Run them red.** `cd jardin-sonore-backend && ./bin/phpunit tests/Unit/Infrastructure/Mail/ImapMailReaderTest.php tests/Unit/Application/Mcp/ReadToolRegistryTest.php` — échecs attendus sur les nouveaux contrats.
- [x] **Step 3: Implement the two `MailReader` methods and register the tool.** Réutiliser le décodage de référence INBOX et le fournisseur Webklex existants ; déclarer `MCP_IMAP_SENT_FOLDER` dans la configuration et l'injecter via `#[Autowire]`, puis rechercher par `In-Reply-To`/`References` valides et borner les corps retournés à 8 000 caractères chacun.
- [x] **Step 4: Run the focused tests green.** Même commande ; tous passent.
- [x] **Step 5: Commit.** `feat(mcp): read reply context and matching sent messages`.

### Task 2: Construction MIME et accès au dossier Brouillons

**Files:**
- Create: `jardin-sonore-backend/src/Application/Mcp/MailDraftStore.php`, `jardin-sonore-backend/src/Infrastructure/Mail/ImapMailDraftStore.php`, `jardin-sonore-backend/src/Infrastructure/Mail/ReplyDraftMimeBuilder.php`
- Modify: `jardin-sonore-backend/config/parameters.yaml.dist`, `jardin-sonore-backend/.env`, `jardin-sonore-backend/.env.test`, `jardin-sonore-backend/config/services.php`
- Test: `jardin-sonore-backend/tests/Unit/Infrastructure/Mail/ReplyDraftMimeBuilderTest.php`, `jardin-sonore-backend/tests/Unit/Infrastructure/Mail/ImapMailDraftStoreTest.php`

**Interfaces:**
- Consumes: `ReplyContext` de Task 1 et `ImapClientProvider::client()`.
- Produces: `ReplyDraftMimeBuilder::build(ReplyContext $context, string $body, string $draftMessageId, string $mailboxAddress, DateTimeImmutable $createdAt): string` ; `MailDraftStore::findByMessageId(string $folder, string $messageId): ?int` et `MailDraftStore::append(string $folder, string $mime): ?int` (`int` = UID constaté, `null` = APPEND sans UID fiable).

- [x] **Step 1: Write failing tests.** Asserter `From`/`To`/`Subject`/`Message-ID`/`In-Reply-To`/`References`, UTF-8 accentué, sujet déjà `Re:`, injection CRLF, `\Draft` sur APPEND, dossier absent et recherche exacte du brouillon par `Message-ID`.
- [x] **Step 2: Run them red.** `cd jardin-sonore-backend && ./bin/phpunit tests/Unit/Infrastructure/Mail/ReplyDraftMimeBuilderTest.php tests/Unit/Infrastructure/Mail/ImapMailDraftStoreTest.php`.
- [x] **Step 3: Implement the builder and IMAP store.** Déclarer `MCP_IMAP_DRAFTS_FOLDER`, composer le MIME avec les composants déjà installés, appeler `Folder::appendMessage()` avec `\Draft`, ne créer aucun dossier et ne dépendre d'aucun SMTP. Vérifier l'existence du message par son identifiant après l'ajout.
- [x] **Step 4: Run the focused tests green.** Même commande ; tous passent.
- [x] **Step 5: Commit.** `feat(mcp): build and store IMAP reply drafts`.

### Task 3: Réservation unique et réconciliation d'un brouillon

**Files:**
- Create: `jardin-sonore-backend/src/Application/Mcp/MailDraftWriter.php`, `jardin-sonore-backend/src/Application/Mcp/MailDraftReceiptStore.php`, `jardin-sonore-backend/src/Application/Mcp/DraftReceipt.php`, `jardin-sonore-backend/src/Application/Mcp/SaveMailDraft.php`, `jardin-sonore-backend/src/Infrastructure/Doctrine/Entity/McpMailDraftEntity.php`, `jardin-sonore-backend/src/Infrastructure/Doctrine/Mapping/App.Infrastructure.Doctrine.Entity.McpMailDraftEntity.php`, `jardin-sonore-backend/src/Infrastructure/Doctrine/Mcp/DoctrineMailDraftReceiptStore.php`
- Generate and review: `jardin-sonore-backend/migrations/Version*.php` for `mcp_mail_draft` only.
- Modify: `jardin-sonore-backend/config/services.php`
- Test: `jardin-sonore-backend/tests/Unit/Application/Mcp/SaveMailDraftTest.php`, `jardin-sonore-backend/tests/Integration/Infrastructure/Doctrine/Mcp/DoctrineMailDraftReceiptStoreTest.php`

**Interfaces:**
- Consumes: `ReplyContext`, `ReplyDraftMimeBuilder`, `MailDraftStore`.
- Produces: `MailDraftWriter::save(string $mailReference, string $body): array` avec `array{status: string, folder: string, messageId: string, uid: ?int}` ; `MailDraftReceiptStore::reserve(string $sourceKey, string $draftMessageId, string $folder): DraftReceipt`, `::markSaved(string $sourceKey, ?int $uid): void` et `::markUncertain(string $sourceKey): void`.
- Table `mcp_mail_draft`: `id`, unique `source_key` (64 hex), unique `draft_message_id`, `folder`, nullable `imap_uid`, `state` (`reserved`, `saved`, `uncertain`), `created_at`, `updated_at`.

- [x] **Step 1: Write failing tests.** Réserver deux fois la même source et vérifier une seule ligne ; appel concurrent, succès, APPEND incertain puis récupération par `Message-ID`, brouillon disparu, et texte différent sur une relance après édition manuelle.
- [x] **Step 2: Run them red.** `cd jardin-sonore-backend && ./bin/phpunit tests/Unit/Application/Mcp/SaveMailDraftTest.php tests/Integration/Infrastructure/Doctrine/Mcp/DoctrineMailDraftReceiptStoreTest.php`.
- [x] **Step 3: Implement the receipt and coordinator.** Construire un `Message-ID` déterministe à partir de la clé source, réserver avant APPEND, rechercher le brouillon marqué avant toute relance, puis enregistrer son UID si connu ; si le résultat d'APPEND reste incertain et introuvable, retourner un état incertain sans second APPEND. Ne jamais modifier un brouillon existant.
- [x] **Step 4: Generate and review the migration.** `cd jardin-sonore-backend && php bin/console make:migration` ; garder uniquement la table et les index du lot, donner une description et un `down()` lisible. Demander ensuite l'accord avant de l'exécuter localement ; contrôler SQL et diff du schéma.
- [x] **Step 5: Run focused tests green.** Même commande ; tous passent après la migration locale autorisée.
- [x] **Step 6: Commit.** `feat(mcp): make draft creation idempotent`.

### Task 4: Outil MCP, configuration et refus par défaut

**Files:**
- Modify: `jardin-sonore-backend/src/Application/Mcp/WriteToolRegistry.php`, `jardin-sonore-backend/config/services.php`, `jardin-sonore-backend/config/parameters.yaml.dist`, `jardin-sonore-backend/.env`, `jardin-sonore-backend/.env.test`
- Test: `jardin-sonore-backend/tests/Unit/Application/Mcp/WriteToolRegistryTest.php`, `jardin-sonore-backend/tests/Functional/Application/Controller/McpControllerTest.php`

**Interfaces:**
- Consumes: `MailDraftWriter::save(string $mailReference, string $body): array` de Task 3.
- Produces: définition et dispatch MCP `save_mail_draft` avec seulement `mailReference` et `body`, annotation d'écriture et drapeau `MCP_DRAFT_WRITE_ENABLED` séparé de `MCP_WRITE_ENABLED`.

- [x] **Step 1: Write failing tests.** `tools/list` contient les huit outils attendus ; le brouillon est refusé par défaut avant lecture IMAP ; corps vide, trop long ou argument `to`/`folder` interdit ; appel valide dispatché une seule fois ; les deux écritures commerciales restent utilisables quand le drapeau brouillon est fermé.
- [x] **Step 2: Run them red.** `cd jardin-sonore-backend && ./bin/phpunit tests/Unit/Application/Mcp/WriteToolRegistryTest.php tests/Functional/Application/Controller/McpControllerTest.php`.
- [x] **Step 3: Implement the MCP contract and config.** Déclarer `MCP_DRAFT_WRITE_ENABLED=0` dans les fichiers de référence ; fournir les chemins IMAP réels uniquement dans la configuration locale de production après vérification.
- [x] **Step 4: Run focused and global verification.** Les tests ciblés passent, puis `./bin/phpunit`, `composer run cs-check`, `composer run stan`, `php bin/console doctrine:schema:validate --env=test` depuis `jardin-sonore-backend/` passent. Vérifier que le diff Doctrine n'inclut rien hors `mcp_mail_draft`.
- [x] **Step 5: Commit.** `feat(mcp): expose confirmed IMAP draft tool`.

### Task 5: Mise en service contrôlée

**Files:**
- Modify: `ROADMAP.md` après les résultats réels.

**Interfaces:** Les outils déployés restent désactivés pour les brouillons jusqu'à validation explicite des chemins IMAP et de la permission Mistral.

- [x] **Step 1: Verify mailbox folders read-only.** Lister les noms IMAP réels sans exposer les identifiants ; confirmer qu'un brouillon manuel Thunderbird mobile apparaît dans Roundcube et, si le PC est prêt, dans Thunderbird PC.
- [x] **Step 2: Prepare and deploy.** Contrôler statut Git, migration, tests et configuration ; commit, tag, push branche et tag avant `make deploy-backend`. Appliquer la migration autorisée et vérifier son état en production.
- [x] **Step 3: Refresh Mistral and check permissions.** Reconnecter le compte si nécessaire ; vérifier `find_sent_replies` en lecture et `save_mail_draft` sur « Demander », puis activer le drapeau serveur.
- [ ] **Step 4: Run the user-facing recipe.** Tester un refus, puis un brouillon accepté sur un mail réel choisi, son apparition dans Thunderbird/Roundcube, une répétition sans doublon et l'absence d'envoi. Mettre `ROADMAP.md` à jour et noter toute limite observée.

## Réalisation et recette — 19 octobre 2026

- Les dossiers IMAP de production sont `INBOX.Sent` et `INBOX.Drafts`. Un brouillon manuel Thunderbird mobile a été vérifié dans Roundcube.
- `save_mail_draft` est déployé et actif (`deploy-mcp-imap-draft-edit-20261019-01` inclut la lecture et le remplacement contrôlé des brouillons MCP liés à leur source). La migration `Version20261019131714` a été appliquée en local et en production ; `UIDPLUS` permet le retrait ciblé après remplacement vérifié.
- Le correctif `deploy-mcp-imap-draft-self-mail-20261019-01` traite clairement les mails de la boîte elle-même et l'absence de brouillon lié.
- `deploy-mcp-contact-replyto-compose-20261019-01` (`ac399ff`) ajoute la réponse aux mails de formulaire avec `Reply-To` externe valide, ainsi que `create_mail_draft` pour un brouillon texte libre à destinataire explicite. Les brouillons identiques sont idempotents. Aucun outil n'envoie de mail.
- Après confirmation de l'utilisateur sur la permission Mistral, `MCP_DRAFT_COMPOSE_ENABLED=1` a été activé en production et le cache Symfony vidé. Le contrôle API authentifié expose 11 outils et rejette `body: ""` avec `Invalid body.` avant toute écriture ; aucun brouillon de contrôle n'a été créé.
- Les tests backend ciblés (31 tests, 161 assertions), PHPStan, CS et lint de conteneur passent selon les vérifications de livraison du 19 octobre. La recette réelle reste à faire dans Mistral et le client IMAP : réponse entrante, réponse formulaire, édition, composition libre, présence, absence de doublons et absence d'envoi.
- Pour les pièces jointes, le choix court terme est de les ajouter à la main dans Thunderbird/Roundcube. Une première évolution pourrait copier une pièce jointe depuis un mail source explicitement sélectionné, après cadrage des limites MIME et de taille. Aucun upload de fichiers locaux au MCP n'est prévu à ce stade.
