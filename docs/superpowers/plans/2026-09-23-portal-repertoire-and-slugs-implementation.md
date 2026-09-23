# Portail structures — répertoire et slugs Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Donner aux structures accès aux comptines et jeux de doigts employés dans leurs séances et utiliser des URLs portail par slug métier.

**Architecture:** Symfony reste l’autorité des slugs et des droits. Next.js rend les données récupérées côté serveur via le BFF ; seule la recherche, les filtres et le tri du catalogue sont des états React clients.

**Tech Stack:** PHP 8.4, Symfony, Doctrine ORM, EasyAdmin, PHPUnit, Next.js App Router, React, TypeScript strict, Tailwind.

**Spec:** `docs/superpowers/specs/2026-09-23-portal-repertoire-and-slugs-design.md`

## Global Constraints

- Exposer uniquement les éléments actifs `nursery_rhyme` et `fingerplay` présents dans une séance d’une organisation autorisée.
- Conserver des slugs uniques, persistants et éditables ; ne jamais les modifier à la mise à jour d’un titre.
- Employer des slugs dans tous les liens, routes et appels de fiche portail.
- Afficher les thèmes par badges colorés dans chaque liste et fiche de séance ou de répertoire.
- Placer tous les textes frontend dans `jardin-sonore-client/src/i18n/dictionaries/fr.ts`.
- Ajouter aucune dépendance et conserver le BFF avec cookie HTTP-only.
- Générer puis relire toute migration ; demander l’autorisation avant de l’exécuter.

## Review Focus

- Les titres qui normalisent vers le même slug obtiennent des suffixes déterministes (`-2`, `-3`). — Task 1.
- Une comptine d’une autre structure répond 404, sans indication d’existence. — Task 4.
- Une comptine présente dans plusieurs séances autorisées est dédupliquée. — Task 4.
- Une séquence sans source `repertoire_item` ne donne aucun accès au catalogue. — Task 4.
- Une ressource non-YouTube ne produit ni iframe ni emplacement de vignette invalide. — Task 6.

## File Structure

- `jardin-sonore-backend/src/Application/Slug/SlugGenerator.php` : normalisation et unicité des slugs.
- `jardin-sonore-backend/src/Infrastructure/Doctrine/Entity/{SessionSummaryEntity,RepertoireItemEntity}.php` : attribut slug persistant.
- `jardin-sonore-backend/src/Infrastructure/Doctrine/Mapping/*.php` et migration : colonnes, index et contraintes.
- `jardin-sonore-backend/src/Application/Portal/{PortalRepertoireReader,PortalRepertoireResponse}.php` : catalogue autorisé, dédupliqué et sérialisé.
- `jardin-sonore-backend/src/Application/Controller/PortalApiController.php` : routes par slug.
- `jardin-sonore-client/src/lib/portal/{types.ts,api-client-core.ts,routes.ts}` : contrats et routes frontend.
- `jardin-sonore-client/src/app/portail/(authenticated)/comptines/**` : liste et fiche serveur.
- `jardin-sonore-client/src/components/portal/{PortalRepertoireList,PortalRepertoireDetail,PortalThemeBadges}.tsx` : UI catalogue et thèmes.

### Task 1: Ajouter des slugs persistants et uniques

**Files:**
- Create: `jardin-sonore-backend/src/Application/Slug/SlugGenerator.php`
- Create: `jardin-sonore-backend/tests/Unit/Application/Slug/SlugGeneratorTest.php`
- Modify: `jardin-sonore-backend/src/Infrastructure/Doctrine/Entity/SessionSummaryEntity.php`
- Modify: `jardin-sonore-backend/src/Infrastructure/Doctrine/Entity/RepertoireItemEntity.php`
- Modify: les deux mappings Doctrine correspondants
- Create: `jardin-sonore-backend/migrations/Version<timestamp>.php`

**Interfaces:** `SlugGenerator::forTitle(string $title, callable(string): bool $isTaken): string`; les deux entités exposent `getSlug(): string` et `setSlug(string): static`.

- [ ] **Step 1: Écrire le test rouge de collision.** Couvrir `Au clair de la lune` avec `au-clair-de-la-lune` et `au-clair-de-la-lune-2` déjà pris ; attendre `au-clair-de-la-lune-3`.
- [ ] **Step 2: Exécuter `./bin/phpunit tests/Unit/Application/Slug/SlugGeneratorTest.php`.** Attendu : échec, classe inexistante.
- [ ] **Step 3: Implémenter le générateur avec `AsciiSlugger`, ajouter les champs, index de recherche et contraintes uniques.** Le slug vide devient `contenu`; les candidats s’incrémentent jusqu’à ce que `$isTaken($candidate)` soit faux.
- [ ] **Step 4: Générer puis corriger la migration.** Elle renseigne les enregistrements existants dans un ordre déterministe avant les contraintes `NOT NULL` et uniques.
- [ ] **Step 5: Exécuter `./bin/phpunit tests/Unit/Application/Slug/SlugGeneratorTest.php && php bin/console doctrine:schema:validate --skip-sync`.** Attendu : succès.
- [ ] **Step 6: Commit proposé : `feat(catalog): add persistent slugs`.**

### Task 2: Gérer les slugs dans le back-office sans casser les liens

**Files:**
- Modify: `jardin-sonore-backend/src/Infrastructure/Admin/RepertoireItemCrudController.php`
- Modify: `jardin-sonore-backend/src/Application/Controller/SessionSummaryController.php` et son form model/template concerné
- Test: `jardin-sonore-backend/tests/Functional/Infrastructure/Admin/RepertoireItemCrudControllerTest.php` (créer si absent)

**Interfaces:** consomme Task 1 ; produit un slug créé automatiquement seulement si le champ est vide, sinon préserve le slug saisi sur une modification du titre.

- [ ] **Step 1: Écrire les tests rouges.** Créer un item titré `La pluie` sans slug et attendre `la-pluie`; changer son titre en `La pluie douce` et vérifier que le slug reste `la-pluie`.
- [ ] **Step 2: Exécuter `./bin/phpunit tests/Functional/Infrastructure/Admin/RepertoireItemCrudControllerTest.php`.** Attendu : échec.
- [ ] **Step 3: Exposer `slug` dans les écrans création/édition, le normaliser, l’uniciser et le générer seulement quand il est vide.** Appliquer la même règle aux séances.
- [ ] **Step 4: Exécuter les tests unitaires/formulaires et fonctionnels concernés.** Attendu : succès.
- [ ] **Step 5: Commit proposé : `feat(backoffice): manage stable content slugs`.**

### Task 3: Migrer le contrat portail des séances vers slug et thèmes

**Files:**
- Modify: `jardin-sonore-backend/src/Application/Portal/PortalSessionResponse.php`
- Modify: `jardin-sonore-backend/src/Application/Portal/PortalSessionAccessService.php`
- Modify: `jardin-sonore-backend/src/Application/Controller/PortalApiController.php`
- Modify: `jardin-sonore-backend/tests/Functional/Application/Controller/PortalApiControllerTest.php`

**Interfaces:** les résumés et fiches exposent `slug: string` et `themes: Array<{uuid,label,color}>`; détails et documents se résolvent par slug après contrôle d’organisation.

- [ ] **Step 1: Écrire les tests rouges pour `GET /api/portal/sessions/seance-automne`, le slug retourné et deux thèmes colorés.**
- [ ] **Step 2: Exécuter `./bin/phpunit tests/Functional/Application/Controller/PortalApiControllerTest.php --filter='Session.*Slug|Session.*Theme'`.** Attendu : échec.
- [ ] **Step 3: Résoudre les séances autorisées par slug et remplacer `theme` par une liste de thèmes du catalogue.** Si la séance ne porte qu’un thème texte historique, établir la relation minimale et migrer les valeurs : ne jamais inventer de couleur dans Next.js.
- [ ] **Step 4: Exécuter le fichier complet `PortalApiControllerTest.php`.** Attendu : succès, y compris les refus et PDF adaptés au slug.
- [ ] **Step 5: Commit proposé : `feat(portal): expose session slugs and themes`.**

### Task 4: Exposer le répertoire autorisé par les séances partagées

**Files:**
- Create: `jardin-sonore-backend/src/Application/Portal/PortalRepertoireReader.php`
- Create: `jardin-sonore-backend/src/Application/Portal/PortalRepertoireResponse.php`
- Modify: `jardin-sonore-backend/src/Application/Controller/PortalApiController.php`
- Modify: `jardin-sonore-backend/tests/Functional/Application/Controller/PortalApiControllerTest.php`

**Interfaces:** `PortalRepertoireReader::listFor(UserEntity $user): array` et `findFor(UserEntity $user, string $slug): ?PortalRepertoireResponse`; endpoints `GET /api/portal/repertoire` et `/api/portal/repertoire/{slug}`.

- [ ] **Step 1: Écrire les tests rouges.** Couvrir une entrée autorisée dans deux séances, une entrée autre-structure, une inactive, et une séquence sans `sourceKind: repertoire_item`.
- [ ] **Step 2: Exécuter `./bin/phpunit tests/Functional/Application/Controller/PortalApiControllerTest.php --filter=Repertoire`.** Attendu : échec, routes absentes.
- [ ] **Step 3: Extraire les `sourceUuid` uniques de toutes les séances autorisées, conserver les items actifs des deux types, les trier par titre.** Le résumé contient slug, type, titre, thèmes et première vidéo YouTube ; la fiche contient blocs, paroles, gestes, consignes, notes et tous les médias. Retourner 404 pour slug absent/non autorisé.
- [ ] **Step 4: Réexécuter le filtre Repertoire.** Attendu : succès.
- [ ] **Step 5: Commit proposé : `feat(portal): expose shared repertoire`.**

### Task 5: Adapter les contrats et la navigation Next.js

**Files:**
- Modify: `jardin-sonore-client/src/lib/portal/types.ts`
- Modify: `jardin-sonore-client/src/lib/portal/api-client-core.ts`
- Modify: `jardin-sonore-client/src/lib/portal/routes.ts`
- Rename: `jardin-sonore-client/src/app/portail/(authenticated)/seances/[uuid]` → `[slug]`
- Modify: `jardin-sonore-client/src/components/portal/PortalSessionActions.tsx`

**Interfaces:** `portalRoutes.session(slug)` et `portalRoutes.repertoire(slug)` ; types `PortalTheme`, `PortalRepertoireSummary`, `PortalRepertoireDetail`.

- [ ] **Step 1: Ajouter un test de routes (si le runner est déjà configuré) ou une assertion TypeScript :** `portalRoutes.session('seance-automne') === '/portail/seances/seance-automne'` et même résultat pour repertoire.
- [ ] **Step 2: Exécuter `npm run lint`.** Attendu : échec avant remplacement de `uuid` par `slug`.
- [ ] **Step 3: Ajouter les méthodes BFF `repertoire()` et `repertoireItem(slug)`; remplacer les paramètres de routes, pages et actions de séance par slug.** Garder l’UUID seulement pour les traitements backend qui le requièrent explicitement.
- [ ] **Step 4: Réexécuter `npm run lint`.** Attendu : succès.
- [ ] **Step 5: Commit proposé : `feat(portal): navigate content by slug`.**

### Task 6: Construire la liste et fiche Comptines

**Files:**
- Create: `jardin-sonore-client/src/app/portail/(authenticated)/comptines/page.tsx`
- Create: `jardin-sonore-client/src/app/portail/(authenticated)/comptines/[slug]/page.tsx`
- Create: `jardin-sonore-client/src/components/portal/PortalRepertoireList.tsx`
- Create: `jardin-sonore-client/src/components/portal/PortalRepertoireDetail.tsx`
- Create: `jardin-sonore-client/src/components/portal/PortalThemeBadges.tsx`
- Modify: `jardin-sonore-client/src/components/portal/PortalAccountHeader.tsx`
- Modify: `jardin-sonore-client/src/i18n/dictionaries/fr.ts`

**Interfaces:** `PortalThemeBadges({themes}: {themes: PortalTheme[]})`; le composant client de liste reçoit les items et tient `{query, type, theme, sortKey}`.

- [ ] **Step 1: Écrire un test composant s’il existe déjà un runner, sinon préparer une recette Playwright sans ajouter de dépendance.** Saisir `pluie` doit garder seulement l’item dont le titre ou un thème contient ce texte ; vérifier filtre type, thème, tri, état vide et absence de miniature pour média non-YouTube.
- [ ] **Step 2: Exécuter le test configuré ou `npm run lint`.** Attendu : échec, composants inexistants.
- [ ] **Step 3: Implémenter pages serveur et composants.** La liste filtre titre + `theme.label` avec `Intl.Collator('fr', {sensitivity: 'base'})`; la vignette n’est rendue que pour un ID YouTube validé. La fiche rend les blocs paroles/gestes, les iframes YouTube sans cookie et les liens externes sécurisés ; les sections vides sont omises. Activer Comptines dans les deux menus, laisser Activités désactivé.
- [ ] **Step 4: Exécuter `npm run lint && npm run build`, puis effectuer une recette navigateur : recherche, chaque filtre/tri, état vide, item sans vidéo, URL de fiche collée directement.** Attendu : succès.
- [ ] **Step 5: Commit proposé : `feat(portal): add shared nursery rhymes`.**

### Task 7: Afficher les badges de thèmes sur les séances et finaliser

**Files:**
- Modify: `jardin-sonore-client/src/components/portal/PortalSessionsList.tsx`
- Modify: `jardin-sonore-client/src/app/portail/(authenticated)/seances/[slug]/page.tsx`
- Modify: `jardin-sonore-client/src/i18n/dictionaries/fr.ts`
- Test: `jardin-sonore-backend/tests/Functional/Application/Controller/PortalApiControllerTest.php`

**Interfaces:** consomme `PortalSessionSummary.themes` de Task 3 et `PortalThemeBadges` de Task 6.

- [ ] **Step 1: Ajouter l’assertion API de deux badges de thèmes sur une séance.**
- [ ] **Step 2: Exécuter `./bin/phpunit tests/Functional/Application/Controller/PortalApiControllerTest.php --filter=Theme`.** Attendu : succès contractuel avant adaptation React.
- [ ] **Step 3: Remplacer les rendus `theme: string | null` par `PortalThemeBadges`; conserver le tri de séance en comparant les libellés de thèmes joints.** N’afficher aucun conteneur vide sans thème.
- [ ] **Step 4: Exécuter `./bin/phpunit tests/Functional/Application/Controller/PortalApiControllerTest.php && npm run lint && npm run build && git diff --check`.** Attendu : succès intégral.
- [ ] **Step 5: Relire le diff de migration avec `php bin/console doctrine:migrations:diff --allow-empty-diff`; vérifier qu’aucun changement hors sujet n’est proposé, demander l’accord pour l’exécuter, puis proposer le commit `feat(portal): show shared repertoire and theme badges`.**
