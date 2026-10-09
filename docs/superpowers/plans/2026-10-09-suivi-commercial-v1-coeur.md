# Suivi commercial V1 — cœur métier Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Livrer un suivi utilisable des demandes, structures, personnes, dossiers, notes et actions dans le backoffice métier, avec capture automatique des demandes du site.

**Architecture:** Symfony conserve les données et les règles métier ; Next.js valide le formulaire public puis transmet la demande au backend. L'annuaire existant est réutilisé sans fusion automatique. Les pages métier Symfony suivent les maquettes mobiles ; les événements de dossier constituent l'historique.

**Tech Stack:** PHP 8.4, Symfony 8.1, Doctrine ORM, Twig et Symfony UX ; Next.js 16 et TypeScript ; PHPUnit 12 et `node --test`.

**Spec:** [Suivi commercial V1 — conception](../specs/2026-10-08-suivi-commercial-v1-design.md). Le [plan finance et récapitulatif](2026-10-09-suivi-commercial-v1-finances-recapitulatif.md) complète ensuite cette V1.

## Global Constraints

- Pages dans le backoffice métier existant, protégées par `ROLE_ADMIN` ; aucun écran de suivi dans le portail public.
- Conception mobile first ; maquettes simples Stitch avant les écrans, cohérentes avec l'identité du backoffice métier. Aucun défilement horizontal dans la qualification, la liste d'actions et la fiche dossier.
- Courriels complets dans Gmail, PDF dans Drive, séances dans Google Calendar ; aucune ressaisie de ces éléments en V1.
- Structures et personnes existantes toujours choisies ou modifiées après validation humaine ; références historiques préservées.
- Textes d'interface dans des fichiers de traduction ; nouveaux fichiers PHP en typage strict ; réutiliser les patterns Doctrine, Symfony UX et de sécurité du dépôt.
- Aucun commit sans accord explicite. Après création d'une migration, demander confirmation avant de l'exécuter ; contrôler que le diff de schéma ne contient que ce lot.

## Review Focus

1. Deux personnes ou structures de noms proches : la qualification n'en fusionne ou modifie aucune sans choix explicite — test de la tâche 2.
2. Soumission publique rejouée après un délai ou un échec SMTP : une seule demande et une seule notification durable — test de la tâche 4.
3. Contact devenu ancien pendant qu'un dossier reste actif : l'histoire le montre, les nouvelles actions ne le proposent plus — test de la tâche 3.
4. Dernière action terminée sans suivante : le dossier reste ouvert et visible dans « Sans prochaine action » — tests des tâches 3 et 5.
5. Ouverture d'un lien ou requête non authentifiée : aucune mutation et aucun accès aux données du dossier — test de la tâche 5.

---

## Carte des fichiers

- `src/Infrastructure/Doctrine/Entity/Commercial*Entity.php` et leurs mappings : demandes, dossiers, personnes rattachées, faits historiques, actions. Un seul modèle de chaque responsabilité.
- `src/Application/Commercial/` : opérations de qualification, changement d'état, écriture de l'historique et gestion des actions ; les contrôleurs y délèguent les règles métier.
- `src/Application/Controller/Commercial*Controller.php` et `templates/commercial/` : pages du backoffice métier et formulaires. `translations/commercial+intl-icu.fr.yaml` contient leurs libellés.
- `src/Application/Controller/CommercialContactApiController.php` et `config/packages/security.php` : entrée serveur pour le formulaire du site ; une garde BFF limite son accès à Next.js.
- `jardin-sonore-client/src/components/CtaContactPanel.tsx` et `src/app/api/contact/route.ts` : transmettent une clé stable de soumission, conservent les validations d'origine et ALTCHA, puis passent la demande au backend ; plus d'envoi direct susceptible de diverger de la persistance.
- `src/Infrastructure/Commercial/` : stockage et distribution durable du courriel de contact, en suivant les reprises de notification déjà présentes.
- `assets/styles/app.css` et composants UX strictement nécessaires : adaptation mobile aux maquettes, sans nouvelle dépendance UI.

Les chemins backend ci-dessous sont relatifs à `jardin-sonore-backend/`, sauf mention contraire.

### Task 0: Maquettes de parcours mobiles

**Files:** Create `docs/suivi-commercial-maquettes-brief.md` ; référencer les sorties Stitch retenues depuis ce fichier et le plan.

**Interfaces:** Produit un brief et des captures ou liens de maquettes pour l'accueil, « À qualifier », la fiche dossier et l'action dépliée ; les tâches 2, 3 et 5 utilisent ces décisions visuelles.

- [ ] Rédiger le brief à partir de la spec, avec largeur téléphone d'abord, identité du backoffice, informations visibles pendant un appel et états d'action.
- [ ] Demander les maquettes simples à Stitch ; conserver les sorties sans données personnelles réelles. Si Stitch n'est pas accessible dans l'environnement d'exécution, obtenir leur export ou accès avant de commencer les écrans.
- [ ] Relire avec l'utilisateur les parcours téléphone et bureau, puis noter les choix retenus dans le brief. Critère : les quatre gestes « qualifier », « noter un appel », « terminer ou reporter », « ouvrir un dossier » sont visibles sans inventer de nouveau parcours.

### Task 1: Stockage et règles de cycle de vie

**Files:** Create `src/Infrastructure/Doctrine/Entity/CommercialRequestEntity.php`, `CommercialProjectEntity.php`, `CommercialProjectPersonEntity.php`, `CommercialEventEntity.php`, `CommercialActionEntity.php` et leurs mappings ; create `src/Application/Commercial/CommercialProjectWorkflow.php`, `CommercialActionWorkflow.php` ; create une migration Doctrine générée ; test `tests/Integration/Infrastructure/Commercial/CommercialPersistenceTest.php`, `tests/Unit/Application/Commercial/CommercialWorkflowTest.php`.

**Interfaces:** `CommercialProjectWorkflow::qualify(CommercialRequestEntity, OrganizationEntity, string $title): CommercialProjectEntity`, `confirm(CommercialProjectEntity): void`, `closeWithoutResult(CommercialProjectEntity): void`, `complete(CommercialProjectEntity): void` ; `CommercialActionWorkflow::complete(CommercialActionEntity, ?string $note): void`, `postpone(CommercialActionEntity, DateTimeImmutable $dueOn): void`, `cancel(CommercialActionEntity): void`.

- [ ] Écrire les tests d'état : une demande peut rester sans structure, un projet « En discussion » ne se ferme pas quand sa dernière action se termine, « Sans suite » conserve ses événements, un projet « Confirmé » avec action ouverte ne se clôture pas, un report garde ancienne et nouvelle dates.
- [ ] Lancer `./bin/phpunit tests/Unit/Application/Commercial/CommercialWorkflowTest.php` depuis le backend ; vérifier l'échec attendu avant implémentation.
- [ ] Auditer les suppressions et l'indicateur `active` de l'annuaire avant de figer les relations : retirer une personne du projet doit préserver les références historiques sans modifier implicitement son éligibilité aux mailings.
- [ ] Implémenter les modèles et workflows minimaux. Une demande peut être liée à plusieurs dossiers et sa clé de soumission publique est unique ; un dossier garde une structure porteuse, une personne principale facultative et plusieurs personnes rattachées avec état courant/ancien. Les événements conservent leur date, auteur/type et texte, sans copier les courriels.
- [ ] Générer et relire la migration avec `make:migration` ou la commande backend du dépôt. Demander confirmation avant toute exécution ; après accord, appliquer en environnement de développement/test et vérifier le diff de schéma ciblé.
- [ ] Exécuter les deux tests ciblés, puis `composer cs-check` et `composer stan` ; attendre des résultats sans erreur avant la tâche suivante.

### Task 2: Saisie et qualification des demandes

**Files:** Create `src/Application/Commercial/QualifyCommercialRequest.php`, `src/Application/Controller/CommercialRequestController.php`, `src/Application/Form/CommercialRequestType.php`, `src/Application/Form/CommercialQualificationType.php`, `templates/commercial/request/*.html.twig` ; modify `templates/internal/base.html.twig` pour l'accès ; test `tests/Functional/Application/Controller/CommercialRequestControllerTest.php`.

**Interfaces:** `QualifyCommercialRequest::qualify(CommercialRequestEntity, OrganizationEntity, array $personIds, string $projectTitle): CommercialProjectEntity` réutilise la tâche 1 ; le contrôleur ne crée ou ne met à jour une fiche d'annuaire qu'après soumission explicite du formulaire.

- [ ] Écrire les tests fonctionnels : demande manuelle sans structure en « À qualifier » ; recherche de deux structures ressemblantes sans rattachement automatique ; choix d'une structure existante puis correction explicite de son téléphone ; création d'une personne pendant qualification ; classement sans suite consultable ; une demande liée à deux dossiers.
- [ ] Lancer `./bin/phpunit tests/Functional/Application/Controller/CommercialRequestControllerTest.php` et constater l'échec attendu.
- [ ] Implémenter formulaires, recherche d'annuaire et contrôleur suivant les maquettes ; préserver les valeurs et le message d'origine si une validation échoue. Aucun clic de suggestion ne modifie l'annuaire avant enregistrement explicite.
- [ ] Relancer le test ciblé et `composer cs-check` ; contrôler sur téléphone que les coordonnées sont lisibles pendant la qualification.

### Task 3: Fiche dossier, notes et actions

**Files:** Create `src/Application/Controller/CommercialProjectController.php`, `CommercialActionController.php`, `src/Application/Form/CommercialNoteType.php`, `CommercialActionType.php`, `templates/commercial/project/*.html.twig`, `templates/commercial/action/*.html.twig` ; test `tests/Functional/Application/Controller/CommercialProjectControllerTest.php`, `CommercialActionControllerTest.php`.

**Interfaces:** Utilise les workflows de la tâche 1. Une action possède titre, date, détails et interlocuteur facultatifs ; la suggestion de relance est `+7` jours calendaires, modifiable. Les opérations écrivent les événements du dossier dans la même transaction que le changement d'état.

- [ ] Écrire les tests : note libre avec interlocuteur facultatif et prochaine action facultative ; interlocuteur principal proposé mais modifiable ; une personne rattachée devenue ancienne reste dans l'historique mais n'est plus un choix d'action ; report journalisé ; action annulée distincte de terminée ; devis et factures absents n'empêchent pas la prise de note.
- [ ] Lancer les deux fichiers de tests ciblés et vérifier l'échec attendu.
- [ ] Implémenter la fiche avec coordonnées actionnables, édition rapide sans perte de note et historique chronologique ; ajouter les opérations terminer, reporter, annuler et classer « Sans suite » avec avertissement sur actions ouvertes.
- [ ] Relancer les tests ciblés ; vérifier sur téléphone qu'une note en cours reste présente après édition d'un contact. Tester un lien de conversation Gmail avec le compte personnel qui reçoit le courrier professionnel ; ne l'afficher que si le retour à la bonne conversation fonctionne. Lancer `composer cs-check` et `composer stan`.

### Task 4: Formulaire du site et notification durable

**Files:** Create `src/Application/Controller/CommercialContactApiController.php`, `src/Infrastructure/Doctrine/Entity/CommercialContactDeliveryEntity.php`, `src/Infrastructure/Commercial/ContactRequestDeliveryStore.php`, `src/Application/Command/DispatchCommercialContactRequestsCommand.php`, `src/Infrastructure/Mailer/SymfonyCommercialContactSender.php`, `src/Infrastructure/Security/CommercialContactBffRequestGuard.php` et une migration générée ; modify `config/packages/security.php`, `jardin-sonore-client/src/components/CtaContactPanel.tsx`, `jardin-sonore-client/src/app/api/contact/route.ts` et la configuration BFF ; test `tests/Functional/Application/Controller/CommercialContactApiControllerTest.php`, `tests/Integration/Infrastructure/Commercial/ContactRequestDeliveryTest.php`, `jardin-sonore-client/tests/contact-route.test.ts`.

**Interfaces:** `POST /api/commercial/contact-requests` reçoit identifiant stable de soumission, nom, email, téléphone, structure, ville, message ; il retourne l'identifiant de demande et ne nécessite pas de session administrateur, mais exige le secret BFF serveur. La demande et sa notification sont durables avant réponse de succès. La même clé et le même contenu retournent la même demande sans renvoi ; la même clé avec un contenu différent est refusée.

- [ ] Écrire les tests : appel backend sans secret refusé ; payload invalide refusé ; deux appels avec même clé/contenu créent une demande et une notification ; même clé avec contenu changé refusée ; panne SMTP conserve la demande et permet la reprise ; la route Next valide origine/ALTCHA avant le backend ; panne backend ne retourne pas `ok`.
- [ ] Lancer les tests ciblés et vérifier l'échec attendu.
- [ ] Implémenter le stockage et la distribution en reprenant les patterns de notification et Messenger existants ; garder le même identifiant de soumission lors d'une nouvelle tentative depuis le formulaire jusqu'au succès ; autoriser cette seule route publique dans `security.php`, puis y imposer le secret BFF du serveur Next (comparaison en temps constant). Ne jamais exposer ce secret au navigateur ; préserver le destinataire et le `replyTo` du courriel actuel.
- [ ] Générer et relire la migration de livraison durable ; demander confirmation avant exécution, l'appliquer après accord en développement/test et vérifier le diff de schéma.
- [ ] Relancer PHPUnit et `node --test tests/contact-route.test.ts` ; lancer `npm run lint` et `npm run build` dans le client, ainsi que `composer cs-check` et `composer stan` dans le backend.

### Task 5: Accueil « Suivi clients » et recette du cœur

**Files:** Create `src/Application/Controller/CommercialDashboardController.php`, `templates/commercial/dashboard.html.twig`, `translations/commercial+intl-icu.fr.yaml` ; modify `templates/internal/base.html.twig`, `assets/styles/app.css` et uniquement les contrôleurs Symfony UX nécessaires ; test `tests/Functional/Application/Controller/CommercialDashboardControllerTest.php`.

**Interfaces:** La page expose demandes à qualifier, actions en retard/aujourd'hui/prochaines et dossiers actifs sans action ouverte ; ses liens ciblent les routes authentifiées des tâches 2 et 3.

- [ ] Écrire les tests : une demande à qualifier apparaît sans action en doublon ; une action due un dimanche apparaît en retard le lundi ; terminer la dernière action laisse le projet dans « Sans prochaine action » ; un invité est redirigé vers la connexion ; ouvrir un lien GET ne change aucune donnée.
- [ ] Lancer le test ciblé et vérifier l'échec attendu.
- [ ] Implémenter la page et les actions rapides selon les maquettes ; faire apparaître le nouvel accès dans la navigation métier, avec libellés traduits.
- [ ] Relancer les tests ciblés, `composer cs-check`, `composer stan` et le build d'assets pertinent ; faire une recette téléphone puis bureau, sans défilement horizontal.

## Fin de ce plan

Mettre à jour la documentation d'exploitation et `ROADMAP.md` après la recette. Proposer un message de commit conventional commit, sans exécuter `git commit` sans accord explicite. La V1 complète exige ensuite le plan finance/récapitulatif lié en tête du document ; les migrations et tout déploiement gardent leurs validations habituelles.
