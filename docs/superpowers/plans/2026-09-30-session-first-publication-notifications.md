# Notifications de première publication — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task in this session. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Envoyer un e-mail Jardin Sonore soigné aux comptes éligibles lors de la première publication d'une séance, sans envoi rétroactif ou nouvelle notification à la republication.

**Architecture:** Une date persistée mémorise la première publication. La sauvegarde et les destinataires sont enregistrés atomiquement, puis une commande distribue les livraisons durables à Messenger ; le handler contrôle encore les droits avant l'envoi. Les mails de service partagent une présentation, et la connexion au portail conserve la destination de la fiche.

**Tech Stack:** PHP >= 8.4, Symfony 8.1, Doctrine ORM 3/DBAL, Symfony Messenger et Mailer, Twig, PHPUnit 12 ; Next.js, React et TypeScript pour le retour après connexion.

**Spec:** `docs/superpowers/specs/2026-09-30-session-notifications-newsletter-design.md`, sections « Lot 1 » et « Présentation des e-mails de service ».

## Global Constraints

- « Une notification de séance est envoyée uniquement lors de sa première mise en ligne. Les éditions suivantes ne produisent pas de notification. »
- « Les séances déjà publiées au moment du déploiement sont initialisées comme ayant déjà franchi cette étape, sans notification rétroactive. »
- « La publication et la programmation durable des notifications sont enregistrées dans la même transaction ».
- « Les notifications de séances en attente utilisent l'adresse actuelle du compte au moment de l'envoi. »
- « Le message n'expose ni notes privées, ni noms d'autres structures sans accès, ni document en pièce jointe. »
- « Une panne SMTP ne bloque pas la publication. »
- « Le contenu essentiel reste compréhensible si les images sont bloquées. »
- Respecter les choix de newsletter séparément ; ce plan ne réalise pas l'abonnement newsletter ou le formulaire footer, qui auront leurs propres plans.
- Déclarations PHP strictes, constantes typées, noms anglais, imports des classes natives, traductions par domaine et conventions Doctrine existantes.
- Aucun ajout de dépendance ni serveur local parallèle.
- Générer et relire les migrations ; obtenir l'accord explicite avant leur exécution. Ne pas committer les étapes de code ou déployer sans nouvelle instruction.

## Review Focus

- Première publication sans compte éligible : une activation ultérieure ne produit pas de rattrapage ; test en partie 2.
- Deux demandes de publication concurrentes ou sauvegarde d'un domaine périmé : une seule préparation de destinataires ; test transactionnel en partie 2.
- Changement d'adresse, de droits ou de préférence avant consommation : utiliser l'adresse courante ou abandonner l'envoi ; tests en partie 3.
- Interruption entre programmation et mise en file : les destinataires durables restent distribuables ; test de reprise en partie 3.
- Retour après connexion manipulé dans un lien ou un formulaire : seule une route interne de fiche de séance valide est acceptée ; test en partie 5.

## Rythme et vérifications

Exécution native dans cette session, sans délégation. Une partie par passage utilisateur : livrable concret, tests ciblés, état restant et arrêt explicite pour laisser l'utilisateur consulter les tokens. Les cases du plan servent de point de reprise si le contexte est compacté.

Runner backend : depuis la racine, `docker compose exec -T php php bin/phpunit <chemin>` ; les fichiers de test sont relatifs à `jardin-sonore-backend/`. Employer le conteneur existant. Les checks globaux sont `make backend-cs-check` et `make backend-stan`. Pour le front : `npm run lint` et `npm run build` depuis `jardin-sonore-client/`.

Les tests d'intégration nécessitent un schéma de test adapté ; le préparer séparément de la base de développement et signaler toute migration qui attend son autorisation. Ne pas présenter une étape comme validée si ses vérifications sont bloquées.

## Task 1: Partie 1 — Mémoire de la première publication

**Files:**
- Modify: `jardin-sonore-backend/src/Domain/Model/Session/SessionSummary.php`.
- Modify: `jardin-sonore-backend/src/Infrastructure/Doctrine/Entity/SessionSummaryEntity.php`.
- Modify: `jardin-sonore-backend/src/Infrastructure/Doctrine/Mapping/App.Infrastructure.Doctrine.Entity.SessionSummaryEntity.php`.
- Modify: `jardin-sonore-backend/src/Infrastructure/Doctrine/Mapper/SessionSummaryMapper.php`.
- Create: `jardin-sonore-backend/tests/Unit/Domain/Model/Session/SessionFirstPublicationTest.php`.
- Create: migration générée via `make:migration` ; conserver son nom horodaté réel dans le compte rendu.

**Interfaces:**
- Consumes: `SessionSummary::setPublished(bool $published): void` et les conversions du mapper existant.
- Produces: `SessionSummary::getFirstPublishedAt(): ?DateTimeImmutable`, argument constructeur facultatif `?DateTimeImmutable $firstPublishedAt = null`, accesseurs équivalents sur l'entité avec `setFirstPublishedAt(?DateTimeImmutable $firstPublishedAt): static`.
- `setPublished(true)` fixe la date une seule fois ; `setPublished(false)` la conserve. La reconstruction depuis la persistence conserve la date fournie sans la recalculer.

- [x] **Step 1: Écrire les tests métier.** `testFirstPublicationSetsItsDate`, `testRepublishingPreservesItsOriginalDate`, `testRestoringPublishedSessionPreservesItsDate`, `testChangingPublicationDoesNotRegenerateReadyPdf`. Vérifier date initiale nulle, date présente après première publication, même instance/valeur après republication et état PDF inchangé.
- [x] **Step 2: Exécuter le test et constater l'échec attendu.** `docker compose exec -T php php bin/phpunit tests/Unit/Domain/Model/Session/SessionFirstPublicationTest.php` : échec sur l'interface encore absente.
- [x] **Step 3: Implémenter le champ, ses conversions et son comportement.** Colonne nullable `first_published_at`. Ne pas changer les signatures utilisées pour publier, ni le flux documentaire.
- [x] **Step 4: Générer et relire la migration.** `docker compose exec -T php php bin/console make:migration`. Backfill des seules séances actuellement publiées avec leur date historique disponible (`updated_at`) ; ce marqueur est une initialisation de reprise, pas une affirmation de date exacte. Aucun envoi ni table newsletter. Retirer les écarts de schéma étrangers à la tâche. Ne pas exécuter la migration.
- [x] **Step 5: Valider et rendre compte.** Tests de cette partie + `SessionSummaryDocumentTest` et `SetSessionPublicationTest`, style et analyse statique. Présenter le diff, la migration en attente et le fait que cette étape mémorise la publication sans encore envoyer de mail.

**Résultat partie 1 — 30 septembre 2026 :** modèle, entité, mapping et mapper mis à jour ; tests de restauration d'une séance dépubliée et aller-retour mapper ajoutés. 12 tests ciblés / 34 assertions et 147 tests unitaires / 562 assertions passent. Une notice PHPUnit préexistante concerne le mock de logger dans `GenerateSessionDocumentHandlerTest::testItSkipsAnAlreadyGeneratedDocumentWhenDuplicateMessagesRemainInTheQueue` ; aucun échec. `composer cs-check`, `composer stan`, `lint:container` et validation du mapping Doctrine réussis. Le dump SQL ne propose que `first_published_at`. Migration `Version20260930071935` générée, relue, avec initialisation des séances déjà publiées, **non exécutée**. Aucun mail envoyé, aucun commit d'implémentation. Les lectures ORM des séances nécessiteront cette migration avant utilisation locale de cette version. Arrêt demandé avant la partie 2.

**Reprise autorisée :** simulation de `Version20260930071935` réussie, puis migration appliquée aux bases locales de développement et de test sur accord utilisateur. Le schéma de développement était synchronisé après cette migration, avant ajout de la table de la partie 2.

## Task 2: Partie 2 — Destinataires durables et programmation atomique

**Files:**
- Create: `jardin-sonore-backend/src/Infrastructure/Doctrine/Entity/SessionNotificationDeliveryEntity.php`.
- Create: `jardin-sonore-backend/src/Infrastructure/Doctrine/Mapping/App.Infrastructure.Doctrine.Entity.SessionNotificationDeliveryEntity.php`.
- Create: `jardin-sonore-backend/src/Infrastructure/Session/SessionNotificationRecipientReader.php`.
- Create: `jardin-sonore-backend/src/Infrastructure/Session/SessionNotificationDeliveryStore.php`.
- Modify: `jardin-sonore-backend/src/Infrastructure/Doctrine/Repository/SessionSummaryDoctrineRepository.php`.
- Create: `jardin-sonore-backend/tests/Integration/Infrastructure/Session/SessionNotificationSchedulingTest.php`.
- Create: migration des livraisons, générée et relue.
- Modify: `jardin-sonore-backend/src/Domain/Model/Session/SessionSummary.php` pour distinguer un choix explicite de publication d'une sauvegarde documentaire.

**Interfaces:**
- Consumes: date de première publication de la partie 1 ; entités `UserEntity`, `UserOrganizationAccessEntity` et `SessionSummaryOrganizationEntity`.
- Produces: `SessionNotificationRecipientReader::eligibleUsers(SessionSummaryEntity $sessionSummaryEntity): array` (`list<UserEntity>` uniques).
- Produces: `SessionNotificationDeliveryStore::schedule(SessionSummaryEntity $sessionSummaryEntity, array $userEntities): void`.
- Table `session_notification_delivery` : séance, compte, état, dates création/mise en file/envoi, nombre de tentatives et erreur. Unicité séance/compte ; suppression en cascade des livraisons lorsque leur séance ou compte disparaît. États `pending`, `queued`, `sent`, `skipped`, `failed`.

- [x] **Step 1: Écrire les tests d'intégration.** `testBothPublicationPathsScheduleSameEligibleRecipients`, `testMultipleStructuresProduceOneDeliveryPerUser`, `testInactivePendingAndOptedOutUsersAreExcluded`, `testPublicationWithoutRecipientsStillConsumesFirstPublication`, `testRepeatedAndConcurrentPublicationDoesNotReschedule`, `testSchedulingFailureRollsBackPublication`.
- [x] **Step 2: Vérifier l'échec des tests sur la table/service absent** dans la base de test isolée.
- [x] **Step 3: Implémenter la sélection.** Exiger compte actif et statut `UserStatus::ACTIVE`, préférence activée et au moins un accès actif à une structure partagée. Ne pas ajouter une règle d'accès différente du portail existant. Dédupliquer avant insertion.
- [x] **Step 4: Intégrer la préparation dans la sauvegarde.** Transaction, verrou et relecture de la ligne de séance existante avant de comparer la date persistée à l'état demandé ; ne pas se fier à une entité déjà présente dans l'identity map ORM. Préserver la date déjà enregistrée si un objet périmé tente de la remplacer ; programmer uniquement le premier passage. Mapper, enregistrer la séance puis les livraisons dans cette transaction. Conserver la génération PDF après sauvegarde suivant son comportement actuel.
- [x] **Step 5: Générer et relire la migration, puis valider.** Tests ciblés, contrôle du diff de schéma, style et analyse statique. Obtenir l'accord avant de jouer une migration. Cette étape crée des livraisons sans encore envoyer d'e-mails.

**Protection complémentaire :** les deux tests de sauvegarde documentaire périmée échouaient en écrasant la publication courante. `setPublished()` marque désormais une intention explicite ; une sauvegarde sans ce choix préserve l'état relu sous verrou. Aucun changement du mécanisme de génération PDF. Les 9 tests d'intégration passent (34 assertions), dont deux processus de publication réellement concurrents et un échec de programmation avec rollback.

**Migration partie 2 :** `Version20260930074508` générée par `make:migration`, relue et simulée avec succès. Le diff ne contient que `session_notification_delivery` et ses deux clés étrangères. Le schéma de test a été préparé séparément ; la migration reste **en attente d'accord pour la base de développement**. Aucun e-mail envoyé, aucun cron installé, aucun commit d'implémentation. Suite : partie 3, distribution Messenger et commande périodique.

**Reprise suivante autorisée :** `Version20260930074508` appliquée à la base locale de développement sur accord utilisateur : 1 migration, 3 requêtes, résultat réussi. `doctrine:schema:update --dump-sql` confirme ensuite un schéma synchronisé.

**Vérifications finales partie 2 :** suite backend complète, `php -d memory_limit=512M bin/phpunit --display-deprecations --display-phpunit-notices` : 204 tests / 1 155 assertions, aucun échec. La limite PHP par défaut de 128 Mo était insuffisante pour le test préexistant d'upload d'avatar ; seule la commande de test reçoit 512 Mo. Deux dépréciations préexistantes (`$http_response_header` dans les commandes de synchronisation géographique) et la notice de mock déjà signalée restent présentes. `composer cs-check` et `composer stan` réussis ; `lint:container` et validation du mapping Doctrine réussis après ajout des services et de l'entité.

## Task 3: Partie 3 — Distribution Messenger et contrôle avant envoi

**Files:**
- Create: `jardin-sonore-backend/src/Application/Session/Message/SendSessionNotificationMessage.php`.
- Create: `jardin-sonore-backend/src/Application/Session/MessageHandler/SendSessionNotificationHandler.php`.
- Create: `jardin-sonore-backend/src/Application/Command/DispatchSessionNotificationsCommand.php`.
- Create: `jardin-sonore-backend/src/Application/Session/SessionNotificationMailSenderInterface.php`.
- Create: `jardin-sonore-backend/src/Application/Session/SessionNotificationMailView.php`.
- Modify: `jardin-sonore-backend/src/Infrastructure/Session/SessionNotificationRecipientReader.php`.
- Modify: `jardin-sonore-backend/src/Infrastructure/Session/SessionNotificationDeliveryStore.php`.
- Modify: `jardin-sonore-backend/config/packages/messenger.php`.
- Create: `jardin-sonore-backend/tests/Integration/Infrastructure/Session/SessionNotificationDeliveryTest.php`.
- Create: `jardin-sonore-backend/tests/Functional/Application/Command/DispatchSessionNotificationsCommandTest.php`.

**Interfaces:**
- Consumes: livraisons de la partie 2 ; `MessageBusInterface` et transport `async` existant.
- Produces: `SendSessionNotificationMessage::__construct(public int $deliveryId)` ; `SendSessionNotificationHandler::__invoke(SendSessionNotificationMessage $message): void`.
- Produces: `SessionNotificationMailSenderInterface::send(SessionNotificationMailView $sessionNotificationMailView): void`.
- View : `string $email`, `?string $firstName`, `string $sessionTitle`, `DateTimeImmutable $sessionDate`, `string $sessionSlug`, `array $organizationNames` (`list<string>` autorisées).
- Commande `app:sessions:dispatch-notifications` ; lot de 100 maximum, récupération des livraisons `pending` et des `queued` sans traitement après 15 minutes.

- [ ] **Step 1: Écrire les tests.** `testDispatcherQueuesPendingDeliveries`, `testDispatcherRecoversInterruptedQueueing`, `testDuplicateMessagesSendOnlyOnce`, `testChangedAccountEmailIsUsed`, `testRevokedAccessDisabledPreferenceAndUnpublishedSessionSkipDelivery`, `testOnlyAuthorizedOrganizationNamesEnterMail`, `testSmtpFailureRemainsRetryable`.
- [ ] **Step 2: Constater les échecs attendus** avec sender factice ; aucun e-mail vers un tiers.
- [x] **Step 3: Implémenter distribution et traitement.** Distribuer les enregistrements durables ; verrouiller chaque livraison lors du contrôle et de l'envoi pour empêcher deux workers de l'envoyer simultanément. Les états terminaux `sent`/`skipped` n'envoient rien. En cas d'échec SMTP, enregistrer erreur/tentative après rollback, puis laisser Messenger assurer ses reprises ; un message de reprise peut traiter l'état `failed`.
- [x] **Step 4: Brancher le routage `async` et la reprise périodique.** Documenter la commande et sa cadence proposée d'une minute dans le README backend ; réserver l'installation du cron réel à un déploiement autorisé. La publication n'appelle jamais SMTP.
- [ ] **Step 5: Valider.** Tests dispatcher/handler et programmation, style/analyse statique ; vérifier que publier fonctionne avec un sender en échec et que les livraisons restent traçables. La garantie SMTP limitée de la spec reste documentée.

**Implémentation partie 3 — cPanel :** commande `app:sessions:dispatch-notifications`, messages sur `async`, contrôle des données actuelles par hydratation scalaire et verrou de livraison pendant le traitement. Erreurs persistées après rollback puis propagées à Messenger ; les états `failed` utilisent les reprises Messenger, sans recyclage infini par le cron. Le délai de récupération des `queued` est réglable par `--recover-after` (15 minutes par défaut, supérieur à l'intervalle du cron et au retard de consommation). Le README décrit l'ajout de la distribution avant le worker cPanel existant, sans Supervisor. Aucun cron installé ou modifié.

**Branchement progressif :** le sender de mail appartient à la partie 4. En son absence, la commande refuse la mise en file et le handler refuse l'envoi ; l'injection optionnelle sera résolue par l'alias de la partie 4. `--dry-run` reste disponible pour contrôler la sélection sans mutation. Aucun faux envoi ne passe à `sent`.

**Contrôles effectués :** `composer stan` sans erreur ; `composer cs-check` réussi après correction de l'ordre d'un import ; `lint:container` réussi ; `app:sessions:dispatch-notifications --dry-run --recover-after=30` réussi (0 livraison dans la base locale). `git diff --check` propre. Aucun test automatique ajouté ni exécuté pour cette partie ; les étapes de tests restent ouvertes. Aucun mail envoyé, aucun commit d'implémentation. Arrêt à cette frontière ; prochaine partie : sender, templates et présentation des mails de service.

## Task 4: Partie 4 — Présentation des mails de service

**Files:**
- Create: `jardin-sonore-backend/templates/service_email/base.html.twig`.
- Create: `jardin-sonore-backend/templates/service_email/base.txt.twig`.
- Create: `jardin-sonore-backend/templates/session_notification/email.html.twig` et `email.txt.twig`.
- Create: `jardin-sonore-backend/src/Infrastructure/Mailer/SymfonySessionNotificationMailSender.php`.
- Create: `jardin-sonore-backend/translations/service_email.fr.yaml`.
- Modify: `jardin-sonore-backend/templates/portal_password/email.html.twig`.
- Create: `jardin-sonore-backend/templates/portal_password/email.txt.twig`.
- Modify: `jardin-sonore-backend/src/Infrastructure/Mailer/SymfonyPortalAccountMailSender.php`.
- Modify: `jardin-sonore-backend/config/services.php`.
- Create: `jardin-sonore-backend/tests/Integration/Infrastructure/Mailer/ServiceEmailRenderingTest.php`.

**Interfaces:**
- Consumes: `SessionNotificationMailView` et `SessionNotificationMailSenderInterface` de la partie 3 ; paramètres `%app.portal.public_base_url%`, `%app.mailing.from_email%`, `%app.mailing.from_name%` existants.
- Produces: implémentation du sender avec versions HTML et texte ; template partagé sans dépendance à une campagne newsletter.

- [ ] **Step 1: Écrire les tests de rendu.** `testSessionEmailIncludesDateTitleAndAuthorizedStructures`, `testAllActionLinksAreAbsolute`, `testAllServiceEmailsHavePlainText`, `testUntrustedTitlesAndNamesAreEscaped`, `testInvitationDescribesAvailablePortal`. Capturer les `Email` avec mailer factice.
- [ ] **Step 2: Constater les échecs attendus** avant création des templates et de la version texte.
- [x] **Step 3: Implémenter et brancher le sender.** Fond crème `#f3ede9`, surface claire `#fffdfa`, vert `#47664b`, terracotta `#A64D43`, largeur fluide plafonnée à 600 px, contenu structuré et bouton « Découvrir la séance ». Les textes vont dans `service_email.fr.yaml`. L'aperçu de boîte mail est explicite, le titre/date/structures sont en texte ; aucun PDF annoncé prêt. Invitation et reset utilisent le même socle visuel.
- [x] **Step 4: Préparer des aperçus locaux avec données fictives.** Montrer les variantes séance, invitation et reset sur mobile/desktop ; réutiliser les services existants, ne pas démarrer un serveur parallèle. Prévoir la confirmation newsletter via le même socle dans son lot futur.
- [ ] **Step 5: Valider.** Tests de rendu, lint Twig/traductions et qualité backend. Faire relire le mail de séance ; préparer les essais Gmail/Outlook avant livraison, sans envoyer de message à un tiers sans instruction.

**Partie 4 — prête pour revue :** bases HTML et texte partagées, templates séance/invitation/reset, domaine de traductions `service_email`, sender de notification branché par alias. Date française explicite, salutation personnalisée avec fallback, contenu lisible sans images, liens absolus et lien de préférence de notifications. L'invitation décrit désormais l'espace disponible. Le test unitaire préexistant du sender d'invitation a été adapté à son constructeur et au contexte des deux templates ; aucun nouveau test ni exécution PHPUnit pour cette partie.

**Contrôles :** lint des 9 fichiers Twig, lint YAML après correction d'une valeur contenant deux-points, `lint:container`, `composer stan` et `composer cs-check` réussis. Aperçus générés avec les classes de sender et les templates réels, données fictives et SMTP explicitement dirigé vers `mailpit:1025`. L'API Mailpit confirme les trois messages à `exemples@jardin-sonore.test` : séance (`9h3eaz82dwnFaadBvyc8Jn`), invitation (`BnbsCtjtY3xSheXUAmVVQP`) et reset (`5VUxiQuHNzHkyns4R24oi7`). Six captures mobile/ordinateur dans le répertoire de suivi ignoré, HTML/texte dans `jardin-sonore-backend/var/service-email-previews/`. Aperçu de séance contrôlé à 390 px : document et viewport de même largeur, aucun débordement horizontal.

**Suite et limites :** revue visuelle utilisateur dans Mailpit, puis partie 5 (retour à la fiche après connexion). Les boutons des exemples utilisent des slugs/tokens fictifs. Aucun message envoyé vers un serveur SMTP externe, aucun compte créé, aucune distribution de notifications réelles déclenchée, aucune modification du cron ou déploiement. Gmail/Outlook et les tests automatiques restent à vérifier. Le choix cron commun/séparé et d'éventuels workers distincts reste ouvert jusqu'à clarification de l'exploitation cPanel. Arrêt à cette partie pour revue et suivi des tokens.

**Correction après revue utilisateur :** retrait des deux titres de contenu des mails invitation/reset et du surtitre de notification. Le bloc séance affiche uniquement son titre, sa date et, si le compte possède plusieurs accès actifs à des structures distinctes, les noms des structures concernées auxquelles il a accès. `SessionNotificationMailView::hasMultipleOrganizations` transporte ce choix, calculé depuis les accès actifs actuels du compte ; il ne dépend pas du nombre de structures partagées pour la seule séance. HTML et texte corrigés, traductions devenues inutiles retirées. Objet et preheader restent distincts. Quatre exemples corrigés renvoyés dans Mailpit : invitation/reset, notification multi-structures et notification mono-structure (titre « Un voyage au pays des sons »). Contrôles Twig, style et analyse statique réussis ; les captures de la première proposition restent historiques, les derniers mails Mailpit font référence.

## Task 5: Partie 5 — Accès à la fiche après connexion

**Files:**
- Create: `jardin-sonore-client/src/lib/portal/login-destination.ts`.
- Create: `jardin-sonore-client/src/lib/portal/login-destination.test.mjs` (suivre le runner des tests `.mjs` existants).
- Modify: `jardin-sonore-client/src/app/portail/connexion/page.tsx`.
- Modify: `jardin-sonore-client/src/app/portail/actions.ts`.
- Modify: `jardin-sonore-backend/src/Infrastructure/Mailer/SymfonySessionNotificationMailSender.php`.

**Interfaces:**
- Consumes: `portalRoutes.session(slug)` et `portalRoutes.sessions` existants.
- Produces: `resolvePortalLoginDestination(value: unknown): string`, acceptant exclusivement `/portail/seances/<slug>` canonique avec un seul segment de slug non vide, sans query, fragment, antislash ou origine ; sinon retour vers la liste.
- Le lien du mail conduit au point d'entrée de connexion avec `next` interne : un compte déjà connecté rejoint directement la fiche ; un compte déconnecté y revient après authentification. La query, le champ caché et l'action serveur sont tous validés.

- [ ] **Step 1: Écrire les tests de destination.** Route valide, valeur absente, URL externe, `//host`, traversée et séparateurs encodés, fragment/query et valeur de formulaire manipulée.
- [ ] **Step 2: Exécuter les tests et constater l'échec sur le helper absent.** Depuis `jardin-sonore-client/` : `node --test src/lib/portal/login-destination.test.mjs`. Suivre la transpilation TypeScript utilisée par `list-query.test.mjs` ; garder le helper sans import runtime de module frère pour pouvoir le charger de la même façon.
- [x] **Step 3: Implémenter le helper et l'intégration.** Conserver `next` lors d'un échec de connexion ; ne pas toucher aux autres redirections du portail. Ajouter le retour immédiat d'un compte connecté et adapter le lien du sender.
- [ ] **Step 4: Valider le parcours avec le navigateur existant.** Lien connecté, lien déconnecté puis connexion, droits absents et paramètre malveillant. Aucun droit d'accès n'est accordé par le lien.
- [ ] **Step 5: Exécuter lint et build front**, tests du helper et tests de liens du sender ; rendre compte des effets de navigation.

**Implémentation partie 5 :** helper pur `resolvePortalLoginDestination` utilisé pour la query et le champ caché relu par l'action serveur. Seule une fiche sous `/portail/seances/` avec un segment canonique est acceptée : origines, séparateurs, traversée, query/fragment, encodages invalides ou doubles et caractères de contrôle sont refusés ; destination par défaut = liste des séances. L'erreur de connexion conserve la destination. La page vérifie le compte courant avec `/me` avant le retour direct ; un cookie expiré (401) laisse le formulaire accessible, une indisponibilité suit le comportement existant du portail. Aucun changement des droits backend ni des autres actions de redirection.

**Lien du sender :** URL absolue `/portail/connexion?next=<fiche encodée>` dans les versions HTML et texte. Deux nouveaux exemples de séance envoyés dans Mailpit avec ce lien, données fictives et transport local explicite.

**Contrôles et arrêt :** `npm run lint` et `npm run build` depuis `jardin-sonore-client/` réussis ; compilation TypeScript et routes Next.js incluses dans le build. `composer cs-check` et `composer stan` réussis pour le sender modifié. Aucun test automatique ajouté ou lancé, aucune recette de connexion navigateur effectuée ; les étapes correspondantes restent ouvertes. Aucun serveur supplémentaire, commit ou déploiement. Arrêt à la partie 5 ; suite = recette finale du lot, revue et clarification du cron cPanel avant livraison.

## Task 6: Partie 6 — Recette du lot et préparation de livraison

**Files:**
- Modify: `jardin-sonore-backend/README.md`.
- Modify: `ROADMAP.md` après recette concluante, en décrivant précisément le statut local et le déploiement encore attendu.
- Modify: ce plan avec les cases et résultats réels.

**Interfaces:**
- Consumes: parties 1–5 validées ; aucune interface nouvelle.
- Produces: compte rendu du lot, aperçu validé, commandes d'exploitation et migrations relues.

- [ ] **Step 1: Faire une recette locale complète.** Publier une séance fictive avec destinataires éligibles ; contrôler une livraison et le contenu dans le transport de test/local, puis modifier/dépublier/republier sans nouvel envoi. Vérifier une publication avec PDF pending et SMTP indisponible.
- [ ] **Step 2: Exécuter les vérifications adaptées au diff final.** Tests des parties modifiées, style/analyse backend, lint/build front ; ne répéter que ce qui a changé ou présente un doute non résolu.
- [ ] **Step 3: Vérifier le schéma et les opérations.** Migrations nécessaires uniquement, pas de variation parasite, commande périodique documentée, workers/reprises et échec SMTP décrits. Signaler les validations qui nécessitent encore une action utilisateur.
- [ ] **Step 4: Présenter le résultat et le message de commit.** Arrêt pour revue utilisateur ; commit et déploiement uniquement sur instruction explicite. Les plans newsletter et footer seront préparés séparément après ce lot.
