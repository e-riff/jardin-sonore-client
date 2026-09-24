# Portail comptines et filtres partagés Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Exposer les comptines partagées et rendre les listes de séances et comptines filtrables, accessibles et partageables par URL.

**Architecture:** Symfony contrôle les droits puis filtre, trie et pagine en base. Next lit les paramètres d'URL côté serveur et ses contrôles client sérialisent un nouvel état URL.

**Tech Stack:** PHP 8.4, Symfony, Doctrine ORM, PHPUnit, Next.js App Router, React, TypeScript strict, Tailwind CSS.

**Spec:** `docs/superpowers/specs/2026-09-24-portal-comptines-filters-design.md`

## Global Constraints

- N'exposer que les éléments actifs `nursery_rhyme` et `fingerplay` référencés par une séance autorisée.
- `organization` est masqué en mono-structure et ne peut jamais étendre les droits.
- Les paramètres `theme` répétés se combinent en OU ; les autres critères se combinent en ET.
- Les changements de filtre ou tri réinitialisent `page` à 1 et sont persistés dans l'URL.
- Séances : date ou titre ; comptines : dernière mise à jour ou titre ; directions `asc` et `desc`.
- Utiliser les couleurs de thèmes persistées, ne pas ajouter de dépendance ni exposer le cookie BFF.
- Toute migration est générée, relue et explicitement approuvée avant exécution.

## Review Focus

- Une structure non autorisée renvoie zéro résultat. — Task 2.
- Plusieurs catégories gardent un contenu qui en possède au moins une. — Tasks 2 et 3.
- Une URL complète restaure tous les contrôles. — Task 4.
- Une comptine liée à plusieurs séances reste unique et porte toutes ses structures autorisées. — Task 3.
- Le total et les pages sont calculés après filtrage. — Tasks 2 et 3.

### Task 1: Persister les catégories de séances

**Files:**
- Modify: `jardin-sonore-backend/src/Infrastructure/Doctrine/Entity/SessionSummaryEntity.php`
- Modify: `jardin-sonore-backend/src/Infrastructure/Doctrine/Mapping/App.Infrastructure.Doctrine.Entity.SessionSummaryEntity.php`
- Modify: `jardin-sonore-backend/src/Application/Session/{SaveSessionSummaryInput,CreateSessionSummary,UpdateSessionSummary,SessionSummaryView}.php`
- Modify: `jardin-sonore-backend/src/Application/Form/Model/SessionSummaryFormModel.php`
- Modify: `jardin-sonore-backend/src/Application/Controller/SessionSummaryController.php`
- Create: `jardin-sonore-backend/migrations/Version<timestamp>.php`
- Test: `jardin-sonore-backend/tests/Functional/Application/Controller/SessionSummaryControllerTest.php`

**Interfaces:** `getThemes(): Collection<int, ThemeEntity>`; `SaveSessionSummaryInput` reçoit `list<string> $themeUuids`; la vue expose `list<array{uuid:string,label:string,color:string}> $themes`.

- [ ] **Step 1: Écrire le test rouge de multi-catégories.**

```php
$session = $this->saveSessionWithThemeUuids([$rainThemeUuid, $nightThemeUuid]);
self::assertSame([$rainThemeUuid, $nightThemeUuid], array_column(SessionSummaryView::fromEntity($session)->themes, 'uuid'));
```

- [ ] **Step 2: Lancer le test rouge.** Run `docker compose exec -T php php ./vendor/bin/phpunit tests/Functional/Application/Controller/SessionSummaryControllerTest.php --filter=Categories`. Expected: FAIL, car seule la chaîne `theme` existe.

- [ ] **Step 3: Ajouter relation, mapping et saisie métier.** Initialiser une collection Doctrine, mapper `session_summary_theme`, résoudre/dédupliquer les UUID via `ThemeRepositoryInterface`, et ne plus consommer le texte historique dans le portail.

```php
/** @var Collection<int, ThemeEntity> */
private Collection $themes;
```

- [ ] **Step 4: Générer et corriger la migration.** Run `docker compose exec -T php php bin/console doctrine:migrations:diff`. Créer seulement la jointure et ses clés cascade ; associer une ancienne valeur `session_summary.theme` uniquement à un thème existant de même libellé, sans créer de couleur par défaut.

- [ ] **Step 5: Vérifier sans appliquer.** Run `docker compose exec -T php php bin/console doctrine:migrations:diff --allow-empty-diff && docker compose exec -T php php bin/console doctrine:schema:validate --skip-sync`. Expected: uniquement la jointure. Demander alors l'autorisation explicite avant `make backend-migrate`.

- [ ] **Step 6: Après autorisation, appliquer, tester, committer.** Run `make backend-migrate && docker compose exec -T php php ./vendor/bin/phpunit tests/Functional/Application/Controller/SessionSummaryControllerTest.php --filter=Categories`. Expected: PASS. Commit: `feat(sessions): persist content categories`.

### Task 2: Filtrer et sérialiser les séances portail

**Files:**
- Create: `jardin-sonore-backend/src/Application/Portal/PortalListCriteria.php`
- Modify: `jardin-sonore-backend/src/Application/Portal/{PortalSessionReader,PortalSessionResponse}.php`
- Modify: `jardin-sonore-backend/src/Application/Controller/PortalApiController.php`
- Modify: `jardin-sonore-backend/tests/Functional/Application/Controller/PortalApiControllerTest.php`

**Interfaces:** `PortalListCriteria::fromRequest(Request $request, bool $allowsType): self`; `PortalSessionReader::paginated(UserEntity, PortalListCriteria): array{items:list<SessionSummaryEntity>,total:int,page:int,pageSize:int}`; réponses avec `themes` et `organizations`.

- [ ] **Step 1: Écrire les tests rouges de recherche, structure, thèmes et tri.**

```php
$client->request('GET', '/api/portal/sessions?q=pluie&theme=' . $rainThemeUuid . '&theme=' . $nightThemeUuid . '&sort=title&direction=asc');
self::assertSame(['la-pluie', 'nuit-douce'], array_column($this->responseJson($client)['items'], 'slug'));
self::assertArrayNotHasKey('theme', $this->responseJson($client)['items'][0]);
```

Couvrir structure interdite, date/titre dans les deux sens et total sur seconde page.

- [ ] **Step 2: Lancer le test rouge.** Run `docker compose exec -T php php -d memory_limit=512M ./vendor/bin/phpunit tests/Functional/Application/Controller/PortalApiControllerTest.php --filter=SessionFilter`. Expected: FAIL.

- [ ] **Step 3: Implémenter critères validés et QueryBuilder.** Joindre les thèmes seulement pour recherche/catégorie, employer `DISTINCT session`, accepter seulement les valeurs de tri prévues et filtrer une structure parmi les organisations déjà autorisées.

```php
if ([] !== $criteria->themeUuids) {
    $queryBuilder->andWhere('theme.uuid IN (:themeUuids)')->setParameter('themeUuids', $criteria->themeUuids);
}
```

- [ ] **Step 4: Émettre `themes` explicitement et retirer `theme`.** Mapper UUID, libellé et couleur de chaque `ThemeEntity`, pour listes et fiches, tout en conservant les routes slug PDF.

- [ ] **Step 5: Vérifier et committer.** Run `docker compose exec -T php php -d memory_limit=512M ./vendor/bin/phpunit tests/Functional/Application/Controller/PortalApiControllerTest.php && docker compose exec -T php composer stan`. Expected: PASS. Commit: `feat(portal): filter shared sessions`.

### Task 3: Exposer le répertoire autorisé et filtré

**Files:**
- Create: `jardin-sonore-backend/src/Application/Portal/{PortalRepertoireReader,PortalRepertoireResponse}.php`
- Modify: `jardin-sonore-backend/src/Infrastructure/Doctrine/Repository/RepertoireItemDoctrineRepository.php`
- Modify: `jardin-sonore-backend/src/Application/Controller/PortalApiController.php`
- Modify: `jardin-sonore-backend/tests/Functional/Application/Controller/PortalApiControllerTest.php`

**Interfaces:** `PortalRepertoireReader::paginated(UserEntity, PortalListCriteria): array{items:list<PortalRepertoireResponse>,total:int,page:int,pageSize:int}` and `findAuthorizedBySlug(UserEntity, string): ?PortalRepertoireResponse`.

- [ ] **Step 1: Écrire les tests rouges du répertoire.**

```php
$client->request('GET', '/api/portal/repertoire?type=fingerplay&theme=' . $rainThemeUuid . '&organization=' . $organizationUuid);
self::assertSame(['tape-tape'], array_column($this->responseJson($client)['items'], 'slug'));
self::assertSame(1, $this->responseJson($client)['pagination']['total']);
```

Couvrir déduplication, item inactif, source non-répertoire, détail 404, structure interdite, titre/`updatedAt` dans les deux directions et média non-YouTube.

- [ ] **Step 2: Lancer les tests rouges.** Run `docker compose exec -T php php -d memory_limit=512M ./vendor/bin/phpunit tests/Functional/Application/Controller/PortalApiControllerTest.php --filter=Repertoire`. Expected: FAIL.

- [ ] **Step 3: Résoudre les sources autorisées puis filtrer les items en SQL.** Extraire uniquement les sources de séquences `REPERTOIRE_ITEM`, dédupliquer les UUID, appliquer activité, type, recherche, catégories, tri et pagination en QueryBuilder. Chaque réponse rassemble les organisations autorisées dont une séance référence l'item.

```php
$sourceUuids = $this->portalSessionReader->authorizedRepertoireSourceUuids($userEntity, $criteria->organizationUuid);
$items = $this->repertoireItemDoctrineRepository->findPortalItems($sourceUuids, $criteria);
```

- [ ] **Step 4: Ajouter routes et médias sûrs.** `GET /api/portal/repertoire` utilise `PortalListCriteria::fromRequest($request, true)` ; détail absent/inactif/interdit = 404 ; vignette uniquement pour YouTube valide ; liens externes avec protections usuelles.

- [ ] **Step 5: Vérifier et committer.** Run `docker compose exec -T php php -d memory_limit=512M ./vendor/bin/phpunit tests/Functional/Application/Controller/PortalApiControllerTest.php --filter=Repertoire`. Expected: PASS. Commit: `feat(portal): expose filtered shared repertoire`.

### Task 4: Ajouter contrats Next et filtres URL accessibles

**Files:**
- Create: `jardin-sonore-client/src/lib/portal/list-query.ts`
- Modify: `jardin-sonore-client/src/lib/portal/{types.ts,api-client-core.ts,routes.ts}`
- Create: `jardin-sonore-client/src/components/portal/{PortalListFilters,PortalThemeBadges}.tsx`
- Modify: `jardin-sonore-client/src/i18n/dictionaries/fr.ts`

**Interfaces:** `PortalListQuery`, `parsePortalListQuery(searchParams)`, `portalListQueryToSearchParams(query)`, `PortalApiClient.sessions(query)`, `PortalApiClient.repertoire(query)`.

- [ ] **Step 1: Écrire le test rouge de round-trip URL.**

```ts
expect(portalListQueryToSearchParams({query: "pluie", organizationUuid: "org", themeUuids: ["rain", "night"], type: "fingerplay", sort: "updatedAt", direction: "desc", page: 2}).toString())
    .toBe("q=pluie&organization=org&theme=rain&theme=night&type=fingerplay&sort=updatedAt&direction=desc&page=2");
```

- [ ] **Step 2: Lancer le test ou lint rouge.** Run `npm run lint`. Expected: FAIL après import du helper absent.

- [ ] **Step 3: Implémenter parsing/sérialisation et contrôles.** Employer les `theme` répétés en ordre stable, omettre les défauts, remettre la page à 1 sur changement et n'afficher le sélecteur structure qu'au-delà d'une organisation.

```tsx
<button aria-pressed={query.themeUuids.includes(theme.uuid)} onClick={() => toggleTheme(theme.uuid)} type="button">{theme.label}</button>
```

Utiliser labels, boutons natifs, `router.replace()` et un bouton de réinitialisation qui supprime tous les critères.

- [ ] **Step 4: Vérifier et committer.** Run `npm run lint && npm run build`. Expected: PASS et aucune référence à `PortalSessionSummary.theme`. Commit: `feat(portal): preserve list filters in urls`.

### Task 5: Construire les pages Comptines

**Files:**
- Create: `jardin-sonore-client/src/app/portail/(authenticated)/comptines/{page.tsx,[slug]/page.tsx}`
- Create: `jardin-sonore-client/src/components/portal/{PortalRepertoireList,PortalRepertoireDetail}.tsx`
- Modify: `jardin-sonore-client/src/components/portal/PortalAccountHeader.tsx`
- Modify: `jardin-sonore-client/src/i18n/dictionaries/fr.ts`

**Interfaces:** `portalRoutes.repertoire()`; `portalRoutes.repertoireItem(slug)`; `PortalRepertoireList({account, response, query})`; `PortalRepertoireDetail({item})`.

- [ ] **Step 1: Préparer la recette rouge.** Ouvrir une URL avec catégorie, type et `updatedAt`; vérifier badges actifs, résultat, état vide, absence de vignette invalide, retour arrière après fiche.

- [ ] **Step 2: Lancer lint rouge.** Run `npm run lint`. Expected: FAIL après import des pages non créées.

- [ ] **Step 3: Implémenter pages et rendu.** Lire les paramètres dans la page serveur, appeler `PortalApiClient.repertoire(query)`, afficher type, catégories, structures, pagination, vignette YouTube valide, blocs/paroles/gestes/médias et liens sécurisés. Activer Comptines desktop/mobile, laisser Activités désactivé.

```tsx
const query = parsePortalListQuery(await searchParams);
const result = await (await PortalApiClient.fromCurrentRequest(token)).repertoire(query);
if (!result.response.ok || !result.data) notFound();
```

- [ ] **Step 4: Vérifier et committer.** Run `npm run lint && npm run build`. Expected: routes Comptines dans la sortie. Commit: `feat(portal): add shared nursery rhymes`.

### Task 6: Remplacer le tri catégorie des séances

**Files:**
- Modify: `jardin-sonore-client/src/app/portail/(authenticated)/seances/{page.tsx,[slug]/page.tsx}`
- Modify: `jardin-sonore-client/src/components/portal/PortalSessionsList.tsx`
- Modify: `jardin-sonore-client/src/i18n/dictionaries/fr.ts`

**Interfaces:** `PortalListFilters`, `PortalThemeBadges`, `PortalListQuery`, `PortalSessionSummary.themes`.

- [ ] **Step 1: Écrire la recette de régression.** Ouvrir `/portail/seances` avec deux thèmes, date descendante ; vérifier badges actifs et résultat OU ; passer au titre ascendant, recharger ; choisir structure puis réinitialiser l'URL.

- [ ] **Step 2: Lancer lint rouge après suppression de `SortKey = "date" | "title" | "theme"`.** Run `npm run lint`. Expected: FAIL tant que le scalaire `theme` est utilisé.

- [ ] **Step 3: Employer les composants communs.** Lire l'URL côté serveur, demander la liste filtrée, rendre catégories, pagination et filtres ; supprimer entièrement le tri catégorie.

```tsx
<PortalThemeBadges themes={session.themes} />
<PortalListFilters organizations={account.organizations} query={query} sortOptions={["date", "title"]} themes={response.availableThemes} />
```

- [ ] **Step 4: Vérifier et committer.** Run `docker compose exec -T php php -d memory_limit=512M ./vendor/bin/phpunit && docker compose exec -T php composer cs-check && docker compose exec -T php composer stan && npm run lint && npm run build && git diff --check`. Expected: succès sans échec de test. Commit: `feat(portal): filter shared sessions by category`.

## Final Delivery Checklist

- [ ] Migration approuvée, appliquée et schéma Doctrine synchronisé.
- [ ] Vérifications de Task 6 rejouées sur le dernier commit.
- [ ] Recette connectée : listes filtrées, retour arrière, URLs collées, fiche comptine autorisée/interdite et PDF séance.
- [ ] `git status -sb` et `git diff --check` propres avant tag, push et déploiement.
