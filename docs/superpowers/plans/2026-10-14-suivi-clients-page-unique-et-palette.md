# Suivi clients : page unique et palette apaisée — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Une seule page « Dossiers » lisible, avec une fiche proche des propositions approuvées et un fond de backoffice plus clair.

**Architecture:** Conserver `CommercialProjectTable` et sa logique de regroupement ; rendre `/commercial/projects` canonique et `/commercial` compatible par redirection. Corriger la hiérarchie visuelle dans les styles et les partiels Twig existants, sans toucher au modèle métier.

**Tech Stack:** Symfony, Twig, Symfony UX LiveComponent et Stimulus, CSS, PHPUnit.

**Spec:** [Page unique et palette](../specs/2026-10-14-suivi-clients-page-unique-et-palette-design.md), en complément de la [conception dossier d'abord](../specs/2026-10-13-suivi-clients-dossier-first-design.md).

## Global Constraints

- `/commercial/projects` est l'unique entrée visible « Dossiers » ; `/commercial` et `commercial_dashboard` restent utilisables par redirection.
- Les groupes « Prioritaires », « À venir », « Sans prochaine tâche » et le filtre des dossiers clos restent disponibles, sans doublon de dossier.
- Les actions rapides reviennent à la page Dossiers, y compris les anciens formulaires `returnTo=dashboard` et `returnTo=index`.
- Conserver les données, tâches, devis, factures, documents et événements ; aucune migration ni nouveau package.
- Textes d'interface en traduction française ; priorité au téléphone ; pas de déploiement dans ce lot.
- Respecter les consignes de `AGENTS.md` et `.codex/jardin-sonore-guidelines.md` ; ne pas supprimer de fichier sans confirmation.

## Review Focus

- Ancien favori `/commercial` : après connexion, une seule redirection vers `/commercial/projects`, avec les filtres présents (Task 1).
- Ancien `returnTo=dashboard` : compléter ou reporter une tâche ramène directement à `/commercial/projects` (Task 1).
- Dossier clos : le filtre permet toujours de le retrouver sans mélanger les priorités actives (Task 1).
- Dossier provisoire : le nom et les coordonnées brutes restent lisibles quand la structure n'est pas encore liée (Task 3).
- Petit écran et clavier : ni débordement horizontal ni commande cachée ; dépliants, liens et actions restent accessibles (Task 2 et Task 3).

---

### Task 1: Unifier l'entrée et les retours Dossiers

**Files:**
- Modify: `jardin-sonore-backend/src/Application/Controller/CommercialDashboardController.php`, `jardin-sonore-backend/src/Application/Controller/CommercialActionController.php`, `jardin-sonore-backend/src/Application/Twig/Component/CommercialProjectTable.php`.
- Modify: `jardin-sonore-backend/templates/internal/base.html.twig`, `jardin-sonore-backend/templates/commercial/project/index.html.twig`, `jardin-sonore-backend/templates/commercial/project/new.html.twig`, `jardin-sonore-backend/templates/commercial/digest/settings.html.twig`, `jardin-sonore-backend/templates/commercial/digest_email.html.twig`, `jardin-sonore-backend/templates/commercial/digest_email.txt.twig`, `jardin-sonore-backend/templates/internal/dashboard.html.twig`, `jardin-sonore-backend/templates/components/CommercialProjectTable.html.twig`.
- Modify: `jardin-sonore-backend/translations/internal+intl-icu.fr.yaml`, `jardin-sonore-backend/translations/commercial.fr.yaml`.
- Test: `jardin-sonore-backend/tests/Functional/Application/Controller/CommercialDashboardControllerTest.php`, `jardin-sonore-backend/tests/Functional/Application/Controller/CommercialActionControllerTest.php`, `jardin-sonore-backend/tests/Unit/Template/InternalNavigationTemplateTest.php`.

**Interfaces:** `CommercialDashboardController::index(): Response` redirige vers `commercial_project_index`. `CommercialActionController::change()` accepte encore `dashboard|index` comme valeurs héritées de `returnTo`, mais les deux ciblent `commercial_project_index` ; l'absence de valeur garde le retour à la fiche. Le LiveComponent garde recherche, statut et tri ; sa valeur `mode` n'a plus de rôle une fois les formulaires unifiés.

- [ ] **Step 1: Écrire les tests en échec.** Dans `CommercialDashboardControllerTest`, remplacer l'attente de rendu direct de `/commercial` par `assertResponseRedirects('/commercial/projects')`, suivre cette redirection et vérifier une seule ligne par dossier ainsi que les trois contrôles LiveComponent ; vérifier aussi que la navigation ne contient qu'un lien commercial vers les dossiers et que le filtre `completed` affiche les dossiers clos. Dans `CommercialActionControllerTest`, attendre `/commercial/projects` pour les deux valeurs héritées de `returnTo`.
- [ ] **Step 2: Exécuter les tests ciblés pour voir les échecs attendus.** `docker compose exec -T php php -d memory_limit=512M ./bin/phpunit tests/Functional/Application/Controller/CommercialDashboardControllerTest.php tests/Functional/Application/Controller/CommercialActionControllerTest.php tests/Unit/Template/InternalNavigationTemplateTest.php`.
- [ ] **Step 3: Implémenter la destination unique.** Rediriger `/commercial`, pointer le menu, l'accueil interne, les retours de création et de réglages et les liens du courriel vers `commercial_project_index`. Retirer de `project/index.html.twig` le bouton de retour vers « À faire » ; retirer de la sous-navigation le doublon. Conserver le nom de route historique et les traductions encore utilisées par les anciens courriels. Simplifier la valeur de retour des formulaires et la propriété `mode` du composant si elle n'a plus d'autre usage. Ne pas supprimer `templates/commercial/dashboard.html.twig` sans l'accord prévu par `AGENTS.md`.
- [ ] **Step 4: Relancer les tests ciblés et le lint Twig.** Attendre toutes les assertions vertes et `docker compose exec -T php php bin/console lint:twig templates/commercial templates/internal templates/components` sans erreur.
- [ ] **Step 5: Commit.** `feat(commercial): use one dossier page for follow-up`.

### Task 2: Apaiser le fond et les états de la page Dossiers

**Files:**
- Modify: `jardin-sonore-backend/assets/styles/app.css` (tokens et règles `.internal-shell`, `.commercial-project-*`).
- Test: recette navigateur sur `/commercial/projects` à 390 px et 1280 px.

**Interfaces:** La page canonique de Task 1 garde les mêmes données et contrôles LiveComponent. Le shell interne prend un fond crème presque blanc, sans halo rose marqué. Rouge réservé aux actions principales et échéances urgentes ; liens et actions secondaires du suivi clients en vert, texte principal foncé.

- [ ] **Step 1: Capturer l'état actuel.** Avec une session authentifiée, relever captures mobile et ordinateur et noter les éléments rouges sans signification d'urgence, la hauteur au-dessus de la première ligne et tout débordement horizontal.
- [ ] **Step 2: Modifier les tokens et styles ciblés.** Ajuster `--color-background` et le fond `.internal-shell` sans dégrader les autres rubriques. Réduire les teintes roses des surfaces et survols commerciaux ; garder le contraste visible, le repère d'urgence et le focus clavier. Ne pas recolorer globalement les actions destructives.
- [ ] **Step 3: Vérifier dans le navigateur.** À 390 px et 1280 px, les groupes et leurs comptes restent distincts, les liens et actions rapides sont lisibles au repos, au survol et au focus ; `document.documentElement.scrollWidth <= innerWidth`. Tester recherche, statut, tri, ouverture des groupes, clic de ligne et boutons indépendants.
- [ ] **Step 4: Compiler les assets et contrôler le diff.** `make symfony-assets` puis `git diff --check` ; attendre zéro erreur.
- [ ] **Step 5: Commit.** `style(internal): calm dossier palette and workspace background`.

### Task 3: Retrouver la hiérarchie visuelle de la fiche dossier

**Files:**
- Modify: `jardin-sonore-backend/templates/commercial/project/show.html.twig`, `templates/commercial/project/_contacts.html.twig`, `templates/commercial/project/_create_controls.html.twig`, `templates/commercial/project/_documents.html.twig`, `templates/commercial/project/_tasks.html.twig`, `templates/commercial/project/_timeline.html.twig` seulement selon le besoin de structure ou de libellé.
- Modify: `jardin-sonore-backend/assets/styles/app.css`, `translations/commercial.fr.yaml` si un libellé change.
- Test: `jardin-sonore-backend/tests/Functional/Application/Controller/CommercialProjectControllerTest.php` et recette navigateur authentifiée.

**Interfaces:** Garder les formulaires, routes, CSRF, données et contrôleurs Stimulus de la fiche. Structure et téléphone essentiels visibles sur mobile ; interlocuteurs repliables. Documents repliés ; dernière entrée du fil ouverte, autres textes longs limités à deux lignes avec commande séparée du crayon.

- [ ] **Step 1: Écrire les assertions de structure qui manquent.** Dans `CommercialProjectControllerTest`, couvrir le dossier provisoire avec coordonnées brutes, les liens `tel:`/`mailto:` de structure et personne, les deux commandes d'ajout, le résumé documents fermé, une tâche datée avec terminer et reporter, et l'ordre du fil. Garder les tests de mutation existants.
- [ ] **Step 2: Exécuter le test ciblé.** `docker compose exec -T php php -d memory_limit=512M ./bin/phpunit tests/Functional/Application/Controller/CommercialProjectControllerTest.php` ; relever les assertions manquantes avant de modifier le rendu.
- [ ] **Step 3: Réduire la hauteur et rétablir la hiérarchie.** Sur ordinateur, remplacer la grande carte de contacts par un bandeau clair et compact où structure, interlocuteur et coordonnées se lisent d'emblée ; placer les commandes d'ajout à portée du regard. Sur mobile, garder le téléphone de structure visible, alléger la carte et conserver les détails des personnes repliables. Donner aux tâches et au fil des surfaces distinctes, avec texte sombre, échéances visibles, pictogrammes légers et peu de rouge.
- [ ] **Step 4: Vérifier comportement et visuel.** Tests ciblés, lint Twig, puis recette authentifiée à 390 px et 1280 px comparée aux captures approuvées. Vérifier le premier événement ouvert, « Voir plus/Voir moins » uniquement pour les textes longs, crayon hors du dépliant, documents fermés, liens appel/courriel, tabulation et absence de débordement.
- [ ] **Step 5: Vérification finale et commit.** `make backend-cs-check`, `make backend-stan`, `make symfony-assets`, suite `./bin/phpunit` dans le conteneur, lint Twig/YAML et `git diff --check`. Mettre à jour `ROADMAP.md` avec la validation réelle ou les écarts restants ; commit `style(commercial): restore dossier detail hierarchy`.
