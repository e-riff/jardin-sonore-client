# Rendez-vous de dossier et heures indicatives — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Distinguer les tâches à heure indicative des vrais rendez-vous de dossier, synchronisés avec Google Calendar, visibles dans le fil et le rapport, et gérables via MCP.

**Architecture:** Les tâches existantes gagnent une heure locale facultative. Une entité de rendez-vous distincte des interventions conserve le lien Google, l'état et le compte rendu ; un service métier unique porte les mutations utilisées par le backoffice et le MCP. La synchronisation Google actualise les rendez-vous et le rapport, tandis que le préfixe `RDV` ne sert qu'à la lisibilité et au filtrage des événements libres.

**Tech Stack:** PHP >=8.4, Symfony 8.1, Doctrine ORM 3 / Migrations 4, Twig, Google Calendar API existante, PHPUnit 12, MCP existant.

**Spec:** [Conception des rendez-vous de dossier](../specs/2026-10-20-rendez-vous-dossiers-et-heures-indicatives-design.md)

## Global Constraints

- Google Calendar fait référence pour le titre, l'heure, la durée et le lieu des RDV ; le backend conserve le dossier, le type, l'état et le compte rendu.
- Un `RDV` n'a aucun invité, ne déclenche ni envoi ni facture, et ne devient jamais une séance après renommage dans Google.
- Une tâche peut avoir une heure indicative `HH:mm` en `Europe/Paris`, jamais un événement Calendar ; la date reste obligatoire.
- Les écritures MCP exigent un dossier exact, une confirmation par opération et un drapeau serveur désactivé par défaut ; les outils déjà livrés gardent leurs contrats.
- Aucun nouvel outil d'envoi de mail, de notification ou de publication de séance. Pas de dépendance nouvelle sans nécessité démontrée.
- Nouveaux fichiers PHP strictement typés ; wording dans les traductions. Générer et relire les migrations, demander confirmation avant leur exécution et contrôler le diff Doctrine.
- Pour la suite complète : `docker compose exec -T php php -d memory_limit=1G ./bin/phpunit` depuis la racine.
- L'exécution de ce plan ne vaut pas autorisation de commit, de migration ou de déploiement : demander les accords explicites prévus dans `AGENTS.md`.

## Review Focus

1. Tâche à heure absente, effacée ou `24:00` : l'absence reste valide et une heure invalide est refusée (Task 1).
2. Création Google réussie suivie d'un échec de persistance : une relance avec la même clé retrouve le même RDV, sans doublon ni rattachement à un autre dossier (Task 2).
3. Modification ou suppression Google pendant une édition : le conflit de version interdit l'écrasement et préserve le compte rendu local (Task 2).
4. Événement libre `RDV` forgé dans le POST de rattachement : il est refusé côté serveur, même s'il était masqué dans la page (Task 3).
5. Panne de synchronisation avant le rapport : le dernier horaire connu est signalé incertain et n'est pas assimilé à une annulation (Task 3).

---

## Carte des fichiers et responsabilités

Dans les listes de fichiers ci-dessous, les chemins `src/`, `templates/`, `translations/`, `tests/` et `config/` abrégés sont relatifs à `jardin-sonore-backend/`.

- `CommercialActionEntity`, son mapping, `CommercialActionWorkflow` et les contrôleurs de tâches portent l'heure indicative. Les templates de tâche et de rapport l'affichent.
- `CommercialAppointmentEntity` et son mapping conservent le lien Google, le dossier, le compte rendu, l'état et la version ; une migration ajoute sa table. `CommercialAppointmentManager` porte les mutations, `RefreshCommercialAppointments` la lecture périodique.
- `CommercialAppointmentController` adapte les formulaires HTTP au manager ; le fil et la vue calendrier présentent les rendez-vous ; `BuildCommercialDigest` ajoute les RDV du jour.
- `CommercialCalendarInboxController` et `CommercialCalendarController` filtrent les RDV des parcours de séance.
- `ProjectContextReader`, `ReadToolRegistry`, `WriteToolRegistry` et un adaptateur MCP dédié exposent les lectures et les mutations, derrière `MCP_PROJECT_SCHEDULE_WRITE_ENABLED=0`.

### Task 1: Heure indicative des tâches

**Files:**
- Modify: `jardin-sonore-backend/src/Infrastructure/Doctrine/Entity/CommercialActionEntity.php`, `src/Infrastructure/Doctrine/Mapping/App.Infrastructure.Doctrine.Entity.CommercialActionEntity.php`, `src/Application/Commercial/CommercialActionWorkflow.php`, `src/Application/Controller/CommercialProjectController.php`, `src/Application/Controller/CommercialActionController.php`.
- Modify: `jardin-sonore-backend/templates/commercial/project/_tasks.html.twig`, `jardin-sonore-backend/templates/commercial/digest_email.html.twig`, `jardin-sonore-backend/templates/commercial/digest_email.txt.twig`, `jardin-sonore-backend/translations/commercial.fr.yaml`, `jardin-sonore-backend/translations/commercial_digest.fr.yaml`.
- Create: une migration Doctrine générée pour `commercial_action.due_time` nullable.
- Test: `jardin-sonore-backend/tests/Functional/Application/Controller/CommercialActionControllerTest.php`, `tests/Integration/Infrastructure/Commercial/BuildCommercialDigestTest.php`.

**Interfaces:**
- Produces: `CommercialActionEntity::getDueTime(): ?string` et `setDueTime(?string $dueTime): void`, où une valeur non nulle respecte `HH:mm` et `00:00`–`23:59`. Le stockage garde cinq caractères ; `null` signifie aucune heure.
- Les opérations de création, édition et report transmettent explicitement l'heure ou sa suppression au workflow. La correction d'une tâche terminée conserve l'heure existante.

- [ ] **Step 1: Write failing tests.** Créer, modifier, reporter et effacer une heure ; vérifier `14:00`, l'affichage « vers 14 h », le rapport du matin, le maintien lors d'un report sans choix d'heure et le refus de `24:00`.
- [ ] **Step 2: Run them red.** `docker compose exec -T php php -d memory_limit=1G ./bin/phpunit tests/Functional/Application/Controller/CommercialActionControllerTest.php tests/Integration/Infrastructure/Commercial/BuildCommercialDigestTest.php` ; échecs attendus sur les nouveaux champs et rendus.
- [ ] **Step 3: Implement the field and forms.** Garder la date obligatoire ; ne jamais créer d'événement Google pour une tâche. Ajouter la traduction « vers » et une présentation secondaire lisible sans la couleur.
- [ ] **Step 4: Generate and review migration.** Utiliser `docker compose exec -T php php bin/console make:migration` ; supprimer le commentaire généré, décrire la migration, vérifier son SQL et son `down()`. Demander confirmation avant de l'exécuter localement.
- [ ] **Step 5: Run focused tests green and commit this deliverable after authorization.** Reprendre la commande de Step 2 ; `git diff --check`.

### Task 2: Rendez-vous liés à Google et synchronisation

**Files:**
- Create: `jardin-sonore-backend/src/Infrastructure/Doctrine/Entity/CommercialAppointmentEntity.php`, `src/Infrastructure/Doctrine/Mapping/App.Infrastructure.Doctrine.Entity.CommercialAppointmentEntity.php`, `src/Application/Commercial/CommercialAppointmentManager.php`, `src/Application/Commercial/RefreshCommercialAppointments.php`.
- Modify: `jardin-sonore-backend/src/Application/Command/RefreshCommercialInterventionsCommand.php`, `src/Application/Commercial/DispatchCommercialDigest.php`, `src/Application/Controller/CommercialProjectController.php` pour le rafraîchissement à l'ouverture et avant le rapport.
- Create: une migration Doctrine générée pour la table de rendez-vous, avec unicité `(calendar_id, google_event_id)` et clé de requête de création unique.
- Test: `jardin-sonore-backend/tests/Unit/Application/Commercial/CommercialAppointmentManagerTest.php`, `tests/Integration/Infrastructure/Commercial/CommercialAppointmentMappingTest.php`, `tests/Unit/Application/Commercial/RefreshCommercialAppointmentsTest.php`.

**Interfaces:**
- Produces: `CommercialAppointmentManager::create(int $projectId, string $requestId, CalendarEventDraft $draft): CommercialAppointmentEntity`, `update(int $projectId, int $appointmentId, string $revision, CalendarEventDraft $draft): CommercialAppointmentEntity`, `cancel(int $projectId, int $appointmentId, string $revision): CommercialAppointmentEntity`, `setSummary(int $projectId, int $appointmentId, string $summary, string $revision): CommercialAppointmentEntity`.
- Produces: `RefreshCommercialAppointments::run(?int $projectId = null): int`. L'entité expose `getRevision(): string`, calculée depuis l'ETag Google et une version locale incrémentée lors de l'édition du compte rendu. Le manager ajoute `RDV` au titre Calendar et vérifie dossier et révision avant écriture ; le rafraîchissement conserve le compte rendu et marque annulé seulement après une réponse Google concluante.
- Consumes: `CommercialCalendarGateway` et ses opérations idempotentes existantes ; aucune invitation n'est incluse dans le brouillon Calendar.

- [ ] **Step 1: Write failing unit and mapping tests.** Création sans invité, titre `RDV`, répétition de `requestId`, autre dossier refusé, changement Google récupéré, suppression Google annulée, erreur Google incertaine, conflit ETag et compte rendu conservé.
- [ ] **Step 2: Run them red.** `docker compose exec -T php php -d memory_limit=1G ./bin/phpunit tests/Unit/Application/Commercial tests/Integration/Infrastructure/Commercial/CommercialAppointmentMappingTest.php` ; les nouveaux contrats échouent.
- [ ] **Step 3: Implement entity, manager and refresher.** Réutiliser le calendrier et le fuseau déjà configurés ; associer chaque RDV au dossier exact. Garder les créations et modifications idempotentes et détecter un conflit avant écriture Google.
- [ ] **Step 4: Generate and review migration.** Utiliser `docker compose exec -T php php bin/console make:migration` ; vérifier unicité, index dossier/date, description et `down()` ; demander confirmation avant exécution locale.
- [ ] **Step 5: Run focused tests green and commit this deliverable after authorization.** Reprendre la commande de Step 2 ; vérifier `git diff --check`.

### Task 3: Parcours backoffice, fil, calendrier et rapport

**Files:**
- Create: `jardin-sonore-backend/src/Application/Controller/CommercialAppointmentController.php`, `templates/commercial/project/_appointment_card.html.twig`, un template de formulaire RDV dans `templates/commercial/project/`.
- Modify: `jardin-sonore-backend/templates/commercial/project/_tasks.html.twig`, `_timeline.html.twig`, `show.html.twig`, `src/Application/Controller/CommercialProjectController.php`, `src/Application/Controller/CommercialCalendarInboxController.php`, `src/Application/Controller/CommercialCalendarController.php`, `src/Application/Commercial/BuildCommercialDigest.php`, `src/Application/Commercial/CommercialDigest.php`, `templates/commercial/digest_email.html.twig`, `templates/commercial/digest_email.txt.twig`, les traductions du domaine commercial.
- Test: `jardin-sonore-backend/tests/Functional/Application/Controller/CommercialAppointmentControllerTest.php`, `tests/Functional/Application/Controller/CommercialCalendarControllerTest.php`, `tests/Integration/Infrastructure/Commercial/BuildCommercialDigestTest.php`.

**Interfaces:**
- Consumes: le manager et le rafraîchissement de Task 2. Les mutations HTTP portent un token CSRF ; le bouton d'annulation demande confirmation.
- Produces: `CommercialDigest::$todayAppointments` et un indicateur d'incertitude pour leur synchronisation ; le fil fusionne les RDV et événements du dossier par date de début, avec prochain RDV visible dans l'en-tête replié.
- Filtre: reconnaître le préfixe libre `RDV` en début de titre, sans sensibilité à la casse et suivi d'un séparateur ou d'un espace ; exclure aussi les identifiants de RDV liés, même renommés.

- [ ] **Step 1: Write failing functional and digest tests.** Création/édition/annulation via dossier, vue du prochain RDV fil replié, compte rendu conservé, RDV du jour dans le rapport, RDV non compté comme séance ou facture, titre `RDV` libre absent de l'écran et POST forcé rejeté.
- [ ] **Step 2: Run them red.** `docker compose exec -T php php -d memory_limit=1G ./bin/phpunit tests/Functional/Application/Controller/CommercialAppointmentControllerTest.php tests/Functional/Application/Controller/CommercialCalendarControllerTest.php tests/Integration/Infrastructure/Commercial/BuildCommercialDigestTest.php`.
- [ ] **Step 3: Implement the UI and read models.** Ajouter « Rendez-vous » près d'« Ajouter une tâche », champs titre/début/fin/lieu, état de synchronisation, compte rendu et version ; utiliser les composants et icônes du backoffice. Distinguer RDV à venir et passés dans le fil sans dupliquer l'entrée à chaque rafraîchissement.
- [ ] **Step 4: Wire calendar filtering and morning report.** Exclure les RDV au rendu et au POST de rattachement ; séparer la rubrique RDV du jour des séances et tâches. En cas d'échec Google, signaler l'horaire incertain.
- [ ] **Step 5: Run focused tests green and commit this deliverable after authorization.** Reprendre la commande de Step 2 ; vérifier sur téléphone et ordinateur authentifiés.

### Task 4: Outils MCP pour tâches et rendez-vous

**Files:**
- Create: `jardin-sonore-backend/src/Application/Mcp/ProjectScheduleManager.php` (port), `jardin-sonore-backend/src/Infrastructure/Doctrine/Commercial/DoctrineProjectScheduleManager.php` (adaptateur du manager de Task 2 et du workflow des tâches).
- Modify: `jardin-sonore-backend/src/Infrastructure/Doctrine/Commercial/DoctrineProjectContextReader.php`, `jardin-sonore-backend/src/Application/Mcp/ReadToolRegistry.php`, `jardin-sonore-backend/src/Application/Mcp/WriteToolRegistry.php`, `jardin-sonore-backend/src/Application/Mcp/ProjectFollowupWriter.php`, `jardin-sonore-backend/src/Infrastructure/Doctrine/Commercial/DoctrineProjectFollowupWriter.php`, `jardin-sonore-backend/config/parameters.yaml.dist`, `jardin-sonore-backend/.env`, `jardin-sonore-backend/.env.test`, `jardin-sonore-backend/config/services.php` pour l'alias du port.
- Test: `jardin-sonore-backend/tests/Unit/Application/Mcp/ReadToolRegistryTest.php`, `tests/Unit/Application/Mcp/WriteToolRegistryTest.php`, `tests/Functional/Application/Controller/McpControllerTest.php`, `tests/Integration/Infrastructure/Commercial/DoctrineProjectContextReaderTest.php`.

**Interfaces:**
- Consumes: `CommercialAppointmentManager` de Task 2, les tâches de Task 1 et la lecture de dossier existante.
- Produces: contexte de dossier avec `openActions[].dueTime` et `appointments[]` bornés ; lecture exacte `get_project_appointment` avec révision ; écritures `create_project_appointment`, `update_project_appointment`, `cancel_project_appointment`, `set_project_appointment_summary`, `create_project_task`, `update_project_task`, `complete_project_task`. L'outil `create_project_reminder` existant accepte `dueTime` optionnel, en gardant `mailReference` obligatoire.
- `MCP_PROJECT_SCHEDULE_WRITE_ENABLED=0` par défaut garde ces nouvelles écritures fermées ; chaque outil annonce la nécessité d'une confirmation dans Mistral. Toute modification refuse une révision périmée.

- [ ] **Step 1: Write failing MCP and adapter tests.** Schémas JSON valides, lecture bornée, refus sans drapeau, identifiants de dossier exacts, heure invalide, clé de création répétée, révision obsolète, aucune invitation ni action sur une séance.
- [ ] **Step 2: Run them red.** `docker compose exec -T php php -d memory_limit=1G ./bin/phpunit tests/Unit/Application/Mcp/ReadToolRegistryTest.php tests/Unit/Application/Mcp/WriteToolRegistryTest.php tests/Functional/Application/Controller/McpControllerTest.php tests/Integration/Infrastructure/Commercial/DoctrineProjectContextReaderTest.php`.
- [ ] **Step 3: Implement MCP contracts and default-off config.** Partager la logique métier avec le backoffice ; ne pas appeler Google directement depuis le registre MCP. Garder l'ancien outil fondé sur mail distinct des tâches créées directement.
- [ ] **Step 4: Run focused tests green, then full quality checks.** Reprendre Step 2 ; lancer la suite complète avec 1 Go, `make backend-lint`, `php bin/console lint:twig templates` et `git diff --check`.
- [ ] **Step 5: Commit this deliverable after authorization.** Utiliser un message conventional commit adapté.

### Task 5: Migration finale, recette et livraison

**Files:**
- Modify: `ROADMAP.md` pour consigner uniquement les résultats effectivement vérifiés et le point de reprise.
- Review: migrations des Tasks 1 et 2 ; les fusionner si aucune n'a encore été appliquée sur une base partagée.

**Interfaces:**
- Produit: une base locale et une production dont le schéma correspond exactement aux deux nouveaux besoins ; outils MCP laissés fermés jusqu'à validation des confirmations « Demander ».

- [ ] **Step 1: Verify schema and migrations.** Relire `up()`/`down()` et SQL ; exécuter localement après accord, puis vérifier `doctrine:schema:validate` et l'absence de diff parasite.
- [ ] **Step 2: Run full verification.** `docker compose exec -T php php -d memory_limit=1G ./bin/phpunit`, `make backend-lint`, lint Twig et `git diff --check` ; noter les notices existantes sans les masquer.
- [ ] **Step 3: Perform authenticated visual recipe.** Tester tâche sans/avec heure, RDV créé et déplacé dans les deux sens, supprimé dans Google, compte rendu, agenda, rapport du matin, exclusion du rattachement sur ordinateur et téléphone.
- [ ] **Step 4: Prepare delivery after explicit authorization.** Vérifier Git, commit, tag et pousser branche et tag ; appliquer la migration approuvée puis déployer le backend. Contrôler les routes et le schéma en production.
- [ ] **Step 5: Validate MCP in Mistral.** Vérifier les définitions, régler chaque écriture sur « Demander », tester refus et acceptation sur un dossier choisi, absence de doublon et d'invité ; activer ensuite le drapeau serveur si autorisé. Mettre la roadmap à jour avec le résultat réel.
