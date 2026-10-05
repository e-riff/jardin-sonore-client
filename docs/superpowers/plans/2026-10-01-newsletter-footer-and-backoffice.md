# Newsletter Footer and Backoffice Implementation Plan

**Archive de réalisation.** Le résultat du 1er octobre décrit la recette avant livraison. Le lot a ensuite été livré sous `deploy-newsletter-footer-20261001-01` et les deux tags de correction de présentation de la confirmation. Consulter [ROADMAP.md](../../../ROADMAP.md) pour l'état et les priorités actuels.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. Exécution dans cette session, méthode conservée du lot précédent.

**Goal:** Permettre l'inscription newsletter publique avec confirmation, la gestion des abonnés dans E-mails et leur sélection dans les campagnes.

**Architecture:** Symfony conserve les contacts, les demandes temporaires et le consentement. Le BFF Next.js protège le formulaire ALTCHA et relaie les demandes vers des routes Symfony exigeant le secret BFF. Un même gestionnaire active les inscriptions publiques confirmées et les inscriptions administratives attestées.

**Tech Stack:** Symfony/Doctrine/EasyAdmin/UX LiveComponent, Mailer/Twig, Next.js/React/TypeScript, ALTCHA ; outils existants, sans dépendance nouvelle.

**Spec:** [Conception validée le 1er octobre](../specs/2026-10-01-newsletter-footer-and-backoffice-design.md).

## Global Constraints

- Jetons valables 48 heures ; seul leur SHA-256 est persisté, jamais le jeton brut dans une file ou un journal.
- Lien de confirmation avec jeton dans le fragment URL, jamais dans le chemin ; confirmation par POST avec jeton dans le corps, hors journaux d’accès HTTP.
- Demandes : cinq par minute et par IP, trois par heure par adresse normalisée, délai minimal de 60 secondes entre deux envois par adresse.
- Nouveau contact public : `optInNewsletter=false`. Confirmation publique par POST seulement. Origines `footer` et `backoffice`.
- Activation administrative directe avec attestation obligatoire du consentement préalable. Une adresse techniquement inactive reste bloquée.
- Une adresse ayant un historique d'inscription libre n'est pas réécrite en place. Ajouter l'adresse corrigée avec une nouvelle attestation ; désabonner l'ancienne séparément.
- Conserver contacts, liens partagés, historique d'inscription et mécanisme de désabonnement existants. Option d'audience libre désactivée par défaut.
- Textes front dans `src/i18n/dictionaries/fr.ts`, textes backend dans les domaines de traduction existants ou un domaine newsletter dédié.
- Recette avec données fictives et Mailpit uniquement ; services Docker existants, aucun serveur supplémentaire.
- Appliquer une migration uniquement après confirmation ; commits supplémentaires et livraison uniquement dans le périmètre autorisé par l'utilisateur. Les deux commits et le déploiement précédents sont déjà réalisés.

## Review Focus

1. Une confirmation ancienne après désabonnement doit rester consommée et ne jamais réactiver le contact — tâche 2.
2. Un JSON malformé, une adresse trop longue ou un jeton au format incorrect doivent être rejetés avant accès métier — tâches 3 et 5.
3. Un secret BFF absent/vide ou incorrect ne doit jamais ouvrir le backend public — tâche 3.
4. Une modification EasyAdmin ou une bascule AJAX ne doit pas contourner l'attestation ni transférer un consentement vers une autre adresse — tâche 4.
5. Deux demandes concurrentes, ou une confirmation après remplacement du jeton, ne doivent pas produire deux contacts ni accepter l'ancien jeton — tâche 2.

## Commandes de référence

Depuis la racine, utiliser le service PHP existant :

```bash
docker compose exec -T php php bin/phpunit --filter Newsletter
docker compose exec -T php php bin/console lint:container
docker compose exec -T php php bin/console lint:twig templates
docker compose exec -T php php bin/console lint:yaml translations
make backend-cs-check
make backend-stan
```

Les tests front utilisent `node:test`, comme `tests/portal-security.test.ts` :
`cd jardin-sonore-client && node --experimental-strip-types --test tests/newsletter-security.test.ts`.
Les dépendances PHP/Node et leurs versions viennent des lockfiles existants.

### Task 1 : option d'audience, masques et extensions

**Files — Modify:**
- `jardin-sonore-backend/src/Application/Form/Model/MailingAudienceFormModel.php`
- `jardin-sonore-backend/src/Application/Form/MailingAudienceType.php`
- `jardin-sonore-backend/src/Application/Twig/Component/MailingAudience.php`
- `jardin-sonore-backend/src/Application/Controller/MailingAudienceMaskController.php`
- `jardin-sonore-backend/templates/components/MailingAudience.html.twig`
- `jardin-sonore-backend/translations/mailing+intl-icu.fr.yaml`

**Files — Test/Create:** `jardin-sonore-backend/tests/Functional/Application/Form/MailingAudienceTypeTest.php`, `jardin-sonore-backend/tests/Integration/Application/Mailing/NewsletterAudienceCampaignFlowTest.php`.

**Interfaces:** Le modèle expose `public bool $includeFreeSubscribers = false`. `fromAudienceFilter()` et `toAudienceFilter()` transportent `NewsletterAudienceFilter::includesFreeSubscribers()`. Aucun changement de signature des use cases de campagne.

Test nommé `testFreeSubscribersSurviveFormRoundTrip` :
```php
$model = MailingAudienceFormModel::fromAudienceFilter(new NewsletterAudienceFilter(includeFreeSubscribers: true));
self::assertTrue($model->toAudienceFilter()->includesFreeSubscribers());
```
La duplication désigne ici la conservation du filtre lors d'une copie/rehydratation
de campagne ; aucun nouveau bouton de duplication n'est ajouté à ce lot.

- [ ] Écrire les tests de round-trip modèle/filtre, case décochée par défaut, soumission cochée et formulaire verrouillé. Assertions : `assertFalse($defaultFilter->includesFreeSubscribers())`, puis `assertTrue($submittedFilter->includesFreeSubscribers())`.
- [ ] Écrire les tests masque sauvegardé/appliqué, duplication de campagne, extension avec filtre libre et géographie matérialisée. Vérifier le total dédupliqué et zéro renvoi aux adresses déjà figées ; un ancien masque sans clé reste à `false`.
- [ ] Lancer les deux fichiers avec PHPUnit et constater l'échec sur l'option absente du formulaire ou perdue à la reconstruction.
- [ ] Ajouter le `CheckboxType`, son aide traduite et le récapitulatif. Propager le booléen dans toutes les reconstructions ; pour le snapshot de masque, accepter uniquement les représentations réelles d'une case Symfony cochée (`true`, `1`, `'1'`), jamais une conversion aveugle de `'false'`.
- [ ] Relancer les tests ciblés, puis les tests existants `NewsletterAudienceResolverTest` et `NewsletterAudienceFilterArrayMapperTest` ; tous doivent passer.

### Task 2 : stockage et cycle de consentement des abonnés libres

**Files — Create:**
- `jardin-sonore-backend/src/Infrastructure/Doctrine/Entity/NewsletterSubscriptionRequestEntity.php`
- `jardin-sonore-backend/src/Infrastructure/Doctrine/Mapping/App.Infrastructure.Doctrine.Entity.NewsletterSubscriptionRequestEntity.php`
- `jardin-sonore-backend/src/Infrastructure/Doctrine/Repository/NewsletterSubscriptionRequestDoctrineRepository.php`
- `jardin-sonore-backend/src/Infrastructure/Mailing/FreeNewsletterSubscriptionManager.php`
- `jardin-sonore-backend/src/Application/Mailing/NewsletterConfirmationState.php`
- `jardin-sonore-backend/src/Application/Mailing/NewsletterConfirmationMailSenderInterface.php`
- `jardin-sonore-backend/tests/Integration/Infrastructure/Mailing/FreeNewsletterSubscriptionManagerTest.php`
- Migration dans `jardin-sonore-backend/migrations/`, nom horodaté produit par `make:migration`.

**Interfaces — Produces:**
- Enum `NewsletterConfirmationState: string` : `READY='ready'`, `CONFIRMED='confirmed'`, `CONSUMED='consumed'`, `UNAVAILABLE='unavailable'`.
- `FreeNewsletterSubscriptionManager::requestSubscription(string $emailAddress): void` : réponse métier opaque ; envoie uniquement si éligible et hors délai minimal.
- `FreeNewsletterSubscriptionManager::confirmationState(string $rawToken): NewsletterConfirmationState` : lecture seule.
- `FreeNewsletterSubscriptionManager::confirm(string $rawToken): NewsletterConfirmationState` : transaction ; `CONFIRMED` uniquement pour une activation effectivement consommée.
- `FreeNewsletterSubscriptionManager::subscribeFromBackoffice(string $emailAddress, bool $consentAttested): EmailContactEntity` : refuse l'absence d'attestation, l'adresse invalide et un contact inactif par `DomainException` ; conserve date/origine si déjà actif.
- `FreeNewsletterSubscriptionManager::unsubscribeFromBackoffice(string $emailContactUuid): void` : conserve l'historique et enregistre le retrait courant ; contact absent traité explicitement par `DomainException`.
- `NewsletterConfirmationMailSenderInterface::sendConfirmation(string $emailAddress, string $rawToken): void`.

Test nommé `testConsumedTokenCannotReactivateUnsubscribedContact` :
```php
self::assertSame(NewsletterConfirmationState::CONFIRMED, $manager->confirm($rawToken));
$manager->unsubscribeFromBackoffice($emailContactUuid);
self::assertSame(NewsletterConfirmationState::CONSUMED, $manager->confirm($rawToken));
self::assertFalse($refreshedEmailContactEntity->hasOptInNewsletter());
```

- [ ] Écrire des tests avec horloge contrôlée : création publique sans opt-in, adresse existante réutilisée, activation avec origine `footer`, activation administrative avec origine `backoffice`, attestation obligatoire, blocage technique, idempotence, retrait puis réinscription.
- [ ] Écrire les tests jeton brut non stocké, expiration à exactement 48 heures, renvoi avant 60 secondes sans nouvel envoi, renvoi après délai invalidant l'ancien jeton, GET sans mutation et jeton consommé après retrait sans réactivation. Assertion centrale : après retrait et nouvel appel `confirm($consumedToken)`, consentement toujours faux.
- [ ] Ajouter un test de concurrence utilisant deux connexions/processus de test et des transactions réellement validées, avec nettoyage des seuls fixtures : une seule adresse et une seule demande ; ancien jeton refusé après remplacement. Couvrir aussi une panne SMTP : consentement toujours faux, nouvelle demande possible après le délai.
- [ ] Lancer le fichier ciblé et constater l'échec lié aux classes absentes.
- [ ] Implémenter l'entité : identifiant, relation unique au contact, empreinte unique 64 caractères, dates demande/expiration/consommation et origine. Repository `ServiceEntityRepository` avec recherche par empreinte et par contact.
- [ ] Implémenter le gestionnaire avec `ClockInterface`, validation de l'adresse normalisée (255 caractères maximum), token `bin2hex(random_bytes(32))`, empreinte SHA-256 et expiration `+48 hours`. Réutiliser le pattern d'insertion concurrente du gestionnaire portail ; verrouiller le contact puis sa demande et rafraîchir leur état avant mutation. La confirmation relit l'empreinte sous verrou afin de détecter un remplacement entre lookup et verrouillage. Activation publique et administrative appellent la même méthode privée. Envoi SMTP après commit ; aucune sérialisation du jeton brut.
- [ ] Générer la migration, relire son SQL et sa description, contrôler l'absence de modifications hors scope. Présenter le SQL et demander confirmation avant application en développement/test ; ne pas contourner cette étape par `schema:update`.
- [ ] Après application autorisée, lancer les tests et `doctrine:schema:validate` en développement et test. Le schéma doit être synchronisé et les tests passer.

### Task 3 : messages et API Symfony protégée

**Files — Create:**
- `jardin-sonore-backend/src/Infrastructure/Mailer/SymfonyNewsletterConfirmationMailSender.php`
- `jardin-sonore-backend/src/Infrastructure/Security/NewsletterBffRequestGuard.php`
- `jardin-sonore-backend/src/Application/Controller/NewsletterSubscriptionApiController.php`
- `jardin-sonore-backend/src/Application/Mailing/NewsletterSubscriptionRequestInput.php`
- `jardin-sonore-backend/templates/newsletter/confirmation_email.html.twig` et `confirmation_email.txt.twig`
- `jardin-sonore-backend/translations/newsletter+intl-icu.fr.yaml`
- `jardin-sonore-backend/tests/Functional/Application/Controller/NewsletterSubscriptionApiControllerTest.php`
- `jardin-sonore-backend/tests/Unit/Infrastructure/Mailer/SymfonyNewsletterConfirmationMailSenderTest.php`

**Files — Modify:** `jardin-sonore-backend/config/packages/security.php`, `config/packages/rate_limiter.php`, `config/services.php`.

**Interfaces — Produces:**
- `POST /api/newsletter/subscription-requests` : JSON `{emailAddress: string}`, succès opaque HTTP 202 `{status: 'accepted'}`.
- `GET /api/newsletter/confirmations/{token}` : HTTP 200 `{state: 'ready'|'consumed'|'unavailable'}` ; aucune mutation.
- `POST /api/newsletter/confirmations/{token}` : HTTP 200 `{state: 'confirmed'|'consumed'|'unavailable'}`.
- Secret absent/incorrect : 403 ; entrée malformée : 400/422 ; limite IP : 429 ; panne d'envoi : 503. Limite d'adresse : réponse générique 202 sans envoi.
- `NewsletterBffRequestGuard::assertAllowed(Request $request): void` vérifie `X-Portal-Bff-Secret` contre `PORTAL_BFF_SHARED_SECRET`, non vide, via `hash_equals`.

Test nommé `testPublicEndpointRejectsMissingBffSecret` :
```php
$client->jsonRequest('POST', '/api/newsletter/subscription-requests', ['emailAddress' => 'fixture@example.test']);
self::assertResponseStatusCodeSame(403);
```

- [ ] Écrire les tests : routes accessibles sans compte seulement avec secret valide, secret absent/vide/faux refusé, JSON invalide et tableau à la place de l'adresse refusés, 256 caractères refusés, jeton hors format hexadécimal 64 caractères refusé avant lookup, GET sans opt-in, POST confirmant une seule fois.
- [ ] Tester les limites : sixième demande par IP en une minute refusée, quatrième par adresse en une heure opaque et sans envoi même avec IP différente ; absence d'adresse en clair dans les clés de limitation.
- [ ] Tester le mail texte/HTML, expéditeur existant, lien public `/newsletter/confirmer/{token}` et exception SMTP propagée sans fuite du jeton dans les messages d'erreur.
- [ ] Lancer ces fichiers et constater leurs échecs avant implémentation.
- [ ] Implémenter sender et gabarits avec `service_email/base.*.twig`, paramètres expéditeur/URL publique existants, alias de l'interface explicite. Routes sur firewall dédié stateless, garde BFF appelée avant lecture métier ; aucune ouverture d'autres routes. DTO validé via `MapRequestPayload`, rate limiters nommés `newsletter_subscription_ip` et `newsletter_subscription_address`, résolution d'IP BFF existante.
- [ ] Relancer les tests ciblés et `lint:container`, `lint:twig`, `lint:yaml` ; tout doit passer.

### Task 4 : ajout, modification et filtres dans E-mails

**Files — Create:**
- `jardin-sonore-backend/src/Application/Controller/NewsletterSubscriberAdminController.php`
- `jardin-sonore-backend/src/Application/Form/NewsletterSubscriberType.php`
- `jardin-sonore-backend/src/Application/Form/Model/NewsletterSubscriberFormModel.php`
- `jardin-sonore-backend/templates/newsletter/subscriber_form.html.twig`
- `jardin-sonore-backend/tests/Functional/Application/Controller/NewsletterSubscriberAdminControllerTest.php`

**Files — Modify:** `jardin-sonore-backend/src/Infrastructure/Admin/EmailContactCrudController.php`, `jardin-sonore-backend/translations/backoffice+intl-icu.fr.yaml`.

**Interfaces — Consumes:** gestionnaire de la tâche 2 ; modèle avec `string $emailAddress = ''`, `bool $consentAttested = false`. Routes administrateur sous `/newsletter/subscribers/new` (GET/POST) et `/newsletter/subscribers/{uuid}/unsubscribe` (POST seulement), protégées par ROLE_ADMIN et CSRF.

Tests nommés `testAdminCannotSubscribeWithoutAttestation` et
`testAdminCannotRewriteAddressWithSubscriptionHistory` : soumettre un CSRF valide
sans attestation doit rendre un formulaire invalide et ne pas créer de consentement ;
une édition forgée doit préserver strictement l'adresse et les liens en base.

- [ ] Écrire les tests login requis, CSRF incorrect refusé, attestation absente refusée, ajout/réutilisation sans doublon, retrait gardant date/origine et liens partagés. Vérifier présence des filtres groupe libre, opt-in, origine et date de retrait dans E-mails.
- [ ] Écrire un test de tentative de réécriture de l'adresse d'un contact avec historique libre et de bascule AJAX d'opt-in : aucune mutation. Vérifier aussi l'édition d'un contact historique sans inscription libre, avec les conventions de partage existantes, sans transférer un consentement acquis à une nouvelle adresse.
- [ ] Lancer les tests ciblés et constater les échecs.
- [ ] Ajouter l'action « Ajouter un abonné », formulaire traduit avec contrainte `IsTrue` sur l'attestation et validation serveur conservée dans le gestionnaire. Rediriger après succès vers le contact dans E-mails. Ajouter champs date/origine et appartenance en lecture seule, filtre d'origine indépendant de `source`, consentement affiché sans bascule AJAX.
- [ ] Remplacer toute modification directe du consentement par les actions validées ; protéger également la persistence EasyAdmin pour les requêtes forgées. Bloquer l'édition d'adresse ayant un historique libre ; fournir un lien vers l'ajout de l'adresse corrigée et une action séparée de retrait. Pour une édition d'adresse sans historique libre, ne jamais conserver un opt-in acquis pour l'ancienne adresse et ne pas déplacer silencieusement des liens partagés.
- [ ] Relancer tests fonctionnels et tests existants `EmailContactLinkEntityTest`, `EmailContactMapperTest` ; tout doit passer.

### Task 5 : BFF, formulaire footer et page de confirmation

**Files — Create:**
- `jardin-sonore-client/src/lib/newsletter/api-client.ts`
- `jardin-sonore-client/src/lib/newsletter/request-handlers.ts`
- `jardin-sonore-client/src/app/api/newsletter/subscriptions/route.ts`
- `jardin-sonore-client/src/app/api/newsletter/confirmations/[token]/route.ts`
- `jardin-sonore-client/src/app/newsletter/confirmer/[token]/page.tsx`
- `jardin-sonore-client/src/components/newsletter/NewsletterSignupForm.tsx`
- `jardin-sonore-client/src/components/newsletter/NewsletterConfirmationPanel.tsx`
- `jardin-sonore-client/tests/newsletter-security.test.ts`

**Files — Modify:** `jardin-sonore-client/src/components/navigation/Footer.tsx`, `jardin-sonore-client/src/i18n/dictionaries/fr.ts`, `jardin-sonore-client/next.config.ts` (politique de référent limitée aux pages de confirmation).

**Interfaces — Produces:**
- `NewsletterApiClient::requestSubscription(emailAddress: string, clientIp?: string): Promise<{status: number}>`.
- `NewsletterApiClient::confirmationState(token: string): Promise<NewsletterConfirmationState>` et `confirm(token: string): Promise<NewsletterConfirmationState>` ; type TS union des quatre états de la tâche 2.
- `handleNewsletterSubscription(request: Request): Promise<Response>` et `handleNewsletterConfirmation(request: Request, token: string): Promise<Response>` ; les fichiers de route délèguent à ces fonctions testables avec les outils Node existants.
- POST BFF inscription reçoit `{emailAddress, altcha}` ; contrôle origine, entrée, captcha consommé puis appel Symfony. POST BFF confirmation contrôle origine et format de jeton. Aucun secret ni configuration backend transmis au navigateur.

Test nommé `rejectsInvalidCaptchaBeforeCallingBackend` :
```typescript
assert.equal(response.status, 403);
assert.equal(backendRequests.length, 0);
```

- [ ] Écrire les tests `node:test` avec fetch/ALTCHA contrôlés : origine étrangère, JSON invalide, mauvaise forme, adresse trop longue, captcha faux ou rejoué sans appel backend, secret présent dans les seuls headers serveur, absence de bearer token de portail, cache `no-store`, timeout et erreurs backend traduits en réponse générique sans fuite de détails. GET de page ne doit jamais appeler `confirm`.
- [ ] Lancer `node --experimental-strip-types --test tests/newsletter-security.test.ts` depuis le front et constater les échecs.
- [ ] Implémenter le client dédié utilisant `PORTAL_API_BASE_URL` et `PORTAL_BFF_SHARED_SECRET`, timeout 10 secondes, header IP existant. Réutiliser `isAllowedRequestOrigin`, `verifyAltchaPayload` et `AltchaWidget` sans modifier leur fonctionnement global.
- [ ] Implémenter le formulaire compact dans le footer : champ email requis, ALTCHA, soumission désactivée pendant envoi, réponse générique, erreur réessayable avec nouveau captcha, labels/états traduits et `aria-live`.
- [ ] Implémenter la page serveur dynamique sans cache : état initial lu seulement, bouton client envoyant le POST et affichant le résultat. Lien expiré/invalide : invitation à redemander depuis le footer ; consommé : état informatif sans prétendre que le contact est encore abonné. Politique `Referrer-Policy: no-referrer`, aucune ressource externe ajoutée.
- [ ] Relancer le test Node, puis `npm run lint` et `npm run build` ; ils doivent passer.

### Task 6 : recette du parcours complet et clôture

**Files — Modify:** `ROADMAP.md`, `jardin-sonore-backend/docs/mailing.md`, ce plan et la conception pour consigner leurs statuts et les résultats effectifs.

- [ ] Lancer la suite backend complète (`docker compose exec -T php php bin/phpunit`), style, PHPStan, validations conteneur/Twig/YAML, tests Node newsletter, lint et build front. Consigner les compteurs et distinguer les notices préexistantes des nouvelles.
- [ ] Vérifier `doctrine:schema:validate` et le diff de schéma : aucune migration parasite à générer.
- [ ] Faire la recette navigateur sur les services existants avec Mailpit : inscription fictive, aperçu texte/HTML, GET sans activation puis POST, état visible dans E-mails, filtres, activation administrative attestée, retrait, case d'audience et total dédupliqué. Vérifier mobile et clavier ; ne pas lancer de serveur parallèle.
- [ ] Faire relire le diff complet par un reviewer indépendant conformément au skill d'exécution ; corriger et revérifier les constats pertinents avant de déclarer le lot terminé.
- [ ] Actualiser roadmap et documentation avec résultats réels ; `git diff --check` propre. Présenter fichiers touchés, commandes lancées, résultat et éventuelles limites. Proposer des commits conventional distincts pour audiences et inscription/gestion ; ne pas les créer sans autorisation couvrant ce nouveau lot.
- [ ] Pour une livraison autorisée ensuite : migrations contrôlées, commit/tag/push branche et tag, artefact propre, protection des fichiers runtime locaux, déploiement puis contrôles HTTP/schéma/files. Aucun déploiement depuis un workspace non commité.

## Revue du plan

Couverture : tâche 1 = partie 5 audiences ; tâche 2 = stockage, concurrence et
consentement ; tâche 3 = mail et API ; tâche 4 = E-mails ; tâche 5 = footer et
confirmation ; tâche 6 = vérification et livraison. Les cinq risques de revue
ont chacun des tests nommés dans la tâche propriétaire. Les noms et états des
interfaces sont communs au backend et au BFF. Plan validé par l’utilisateur (« go ») ; implémentation terminée et vérifiée localement.


## Résultat effectif — 1er octobre 2026

Les six tâches sont implémentées et vérifiées localement. Les cases ci-dessus conservent le détail prévu ; les preuves effectives sont résumées ici.

- Backend : `docker compose exec -T php php -d memory_limit=512M bin/phpunit` — 311 tests, 1 706 assertions, aucune erreur, une notice PHPUnit préexistante. Les deux dépréciations déjà connues apparaissent à froid ; aucune nouvelle dépréciation sur la dernière exécution avec cache chaud.
- `composer run cs-fix` et `composer run stan` dans PHP : style propre, PHPStan sans erreur. `lint:container`, `lint:twig templates` (77 fichiers), `lint:yaml translations` (12 fichiers) réussis.
- Front : `docker compose exec -T client-front node --experimental-strip-types --test tests/newsletter-security.test.ts tests/portal-security.test.ts` — 14 tests réussis. `npm run lint` réussi. `docker compose exec -T client-front npm run build` réussi ; le build hôte est bloqué par la création du port temporaire Turbopack, même après demande d’escalade.
- Migration générée avec `make:migration`, relue et limitée à la table des demandes. Accord reçu pour développement et test, migration appliquée. Le test avait une ancienne table présente mais sa migration absente de la métadonnée : seule `Version20261001101645` a été exécutée explicitement, sans altérer cet historique hors périmètre. `doctrine:schema:validate` réussi dans les deux environnements ; `doctrine:schema:update --dump-sql` sans SQL à appliquer.
- Recette navigateur sur les services existants : ALTCHA valide, demande fictive acceptée, versions HTML et texte dans Mailpit, GET sans activation (opt-in et groupe libre à 0), clic explicite activant les deux drapeaux avec origine footer. Mobile 390 × 844 sans débordement, captcha atteignable au clavier. Ajout administrateur attesté, retrait préservant origine/date, filtres E-mails chargés. Audience libre décochée : 2 114 destinataires ; cochée : 2 115, incluant le seul abonné libre fictif actif.
- Revue indépendante : trois constats importants corrigés avec régressions. Adresses immuables dans E-mails, correction des liens d’annuaire séparant les contacts, retrait portail/public invalidant les demandes sous verrou du contact ; un ancien lien ne peut pas réactiver l’adresse.
- Recette ayant révélé deux collations différentes dans la base de développement : comparaison binaire après normalisation de casse/espaces. Test reproduit le conflit avec une table temporaire de connexion avant correction ; 22 tests resolver / 68 assertions ensuite réussis. Aucun changement de schéma nécessaire.
- Navigateur fermé, données fictives locales nettoyées ; aucun serveur supplémentaire, aucun envoi externe ou campagne lancée. Les références navigateur ont été rafraîchies et les clics vérifiés sur le DOM réel ; aucun contournement ajouté au produit.

Les jetons sont masqués dans Monolog et exclus des logs entrants Next. Contrôle cPanel du 1er octobre : les journaux d’accès Apache accessibles incluent le chemin demandé. Le lien utilise donc `/newsletter/confirmer/confirmation#<jeton>` : le fragment n’est transmis ni à Apache, ni à Next ou dans le référent. Le clic explicite envoie ensuite le jeton dans le corps JSON d’un POST dont le chemin est constant ; le corps n’est pas consigné par les journaux d’accès. Le stockage anti-rejeu ALTCHA existant reste en mémoire pour l’instance unique actuelle.

Aucun commit supplémentaire ni déploiement effectué pour ce lot. Propositions : `feat(mailing): expose free subscribers in audience controls` puis `feat(newsletter): add confirmed footer signup and subscriber management`. La livraison ultérieure doit inclure l’accord pour la migration production, les commits, tag et push de branche/tag avant déploiement.
