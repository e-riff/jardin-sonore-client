# Abonnements et audiences newsletter — Implementation Plan

**Archive de réalisation.** Les statuts intermédiaires et cases ouvertes ci-dessous retracent l'exécution du lot en septembre et octobre ; les parties 1 à 5 et l'inscription publique sont livrées. Consulter [ROADMAP.md](../../../ROADMAP.md) pour l'état et les priorités actuels.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task in this session. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permettre aux comptes du portail de gérer leur consentement newsletter et aux campagnes de cibler les comptes éligibles ainsi que les abonnés libres, y compris les personnes sans structure, sans doublons.

**Architecture:** `EmailContactEntity` reste la référence unique du consentement par adresse. Le profil lit et modifie cette référence, tandis que le resolver combine annuaire, comptes du portail et inscriptions libres confirmées. Le contrôle du consentement est renouvelé avant SMTP ; le formulaire public et son mécanisme de confirmation appartiennent au lot suivant.

**Tech Stack:** PHP >= 8.4, Symfony 8.1, Doctrine ORM 3/DBAL, Symfony Messenger, Twig/LiveComponent, PHPUnit 12 ; Next.js, React et TypeScript.

**Spec:** [Cadrage du 30 septembre](../specs/2026-09-30-session-notifications-newsletter-design.md), lot 2 et vérifications associées. [ROADMAP.md](../../../ROADMAP.md) définit l'ordre actif.

**Statut :** plan approuvé par l’utilisateur ; parties 1–2 committées et déployées sous `deploy-availability-newsletter-20260930-01` (`afbcece`), branche/tag poussés, migrations production appliquées par le script backend. Parties 3–4 implémentées et vérifiées localement le 1er octobre, préparées pour la livraison autorisée le 1er octobre sous le tag `deploy-newsletter-audiences-consent-20261001-01` ; partie 5 à réaliser. Exécution directe dans cette session, partie par partie avec arrêt entre parties, conformément au point de reprise.

## Global Constraints

- « L'état newsletter et la désinscription restent portés par l'adresse. » Aucun second booléen de consentement sur `UserEntity`.
- « Retirer l'abonnement newsletter ne désactive pas les notifications de séance. »
- « Le groupe supplémentaire dépend de cette inscription explicite, pas simplement de l'absence de structure ni de la source initiale du contact. »
- « Les valeurs historiques d'abonnement sont préservées » ; aucun abonnement créé par une lecture du profil ou une création de compte.
- « Une même adresse reçoit un seul exemplaire d'une campagne, même si plusieurs chemins la sélectionnent. » Normaliser casse et espaces, conserver points et suffixes `+`.
- Groupe libre désactivé par défaut dans les filtres historiques ; ne pas réécrire les destinataires déjà figés.
- Pas de formulaire footer, demande temporaire, jeton de confirmation, mail de confirmation ou changement d'adresse de compte dans ce lot.
- Traductions front dans `src/i18n/dictionaries/fr.ts`, backoffice dans le domaine `mailing`. Pas de nouvelle dépendance, serveur parallèle ou refonte générale.
- Générer la migration avec `make:migration`, relire le diff et exécution des migrations autorisée par l’utilisateur pour cette session après revue. Commit, envoi externe et déploiement uniquement sur instruction utilisateur pour ces actions.

## Review Focus

- Un contact partagé conserve l'appartenance au groupe libre après une désinscription et un passage domaine/ORM : testé en partie 1.
- Profil consulté ou sauvegardé sans le champ newsletter : aucune création ou modification de consentement ; contact techniquement bloqué jamais réactivé : testé en partie 2.
- Structure sélectionnée explicitement hors zone, sans e-mail propre : son compte abonné est retenu comme les contacts explicitement ajoutés aujourd'hui ; accès révoqué exclu : testé en partie 3.
- Consentement retiré depuis une autre connexion après mise en file : lecture actuelle avant envoi, aucun SMTP, livraison terminée sans bloquer la campagne : testé en partie 4.
- Filtre reconstruit par le composant ou le contrôleur de masque : la nouvelle option survit à la matérialisation géographique, duplication et extension : testé en partie 5.

## Constat du code avant implémentation

- `EmailContactDoctrineRepository::findEntityByEmailAddress()` existe ; l'unicité `email_address` et le jeton de désinscription sont déjà présents.
- `PortalApiController::me()` et `updateProfile()` exposent uniquement `newSessionNotificationsEnabled`. Ajouter le consentement sans changer la gestion d'avatar.
- `DoctrineNewsletterAudienceResolver` sélectionne les contacts liés à l'annuaire ; les structures explicites sont ajoutées en union, indépendamment des autres filtres.
- `MailingAudience::buildAudienceFilter()` et `MailingAudienceMaskController::buildAudienceFilterFromSnapshot()` reconstruisent un filtre : y transporter la nouvelle option.
- `mailing_delivery_recipient` possède déjà l'unicité campagne/adresse et un état `cancelled`. Le handler et le sender ne vérifient pas le consentement actuel avant un envoi de campagne.

## Partie 1 — Stockage de l'inscription libre et conservation du consentement

**Files (préfixe backend : `jardin-sonore-backend/`) :**
- Modify: `src/Infrastructure/Doctrine/Entity/EmailContactEntity.php`, `src/Infrastructure/Doctrine/Mapping/App.Infrastructure.Doctrine.Entity.EmailContactEntity.php`.
- Modify: `src/Domain/Model/AddressBook/EmailContact.php`, `src/Infrastructure/Doctrine/Mapper/EmailContactMapper.php`.
- Create: migration générée dans `migrations/` ; son nom horodaté sera consigné après génération.
- Create: `tests/Unit/Infrastructure/Doctrine/Mapper/EmailContactMapperTest.php`.

**Interfaces :** ajouter aux modèles domaine et ORM `hasFreeNewsletterSubscription(): bool`, `getFreeNewsletterSubscriptionConfirmedAt(): ?DateTimeImmutable`, `getFreeNewsletterSubscriptionOrigin(): ?string`. L'entité offre `confirmFreeNewsletterSubscription(DateTimeImmutable $confirmedAt, string $origin): void`, utilisé par le lot public futur. Champs persistés : `freeNewsletterSubscription` = false, `freeNewsletterSubscriptionConfirmedAt` = null, `freeNewsletterSubscriptionOrigin` = null ; colonnes snake_case, origine limitée à 64 caractères. Ajouter les arguments optionnels du domaine à la fin du constructeur pour préserver les appels existants.

- [x] **1. Tests :** aller-retour domaine/ORM d'une inscription confirmée, puis `unsubscribe()` et sauvegarde : date/origine/appartenance conservées, opt-in false et date de désinscription conservée. Contact historique inchangé, non classé libre. Confirmation du groupe libre seule ne réactive pas l'abonnement : le futur cas d'usage public effectuera les deux actions dans sa transaction.
- [x] **2. RED :** `docker compose exec -T php php bin/phpunit tests/Unit/Infrastructure/Doctrine/Mapper/EmailContactMapperTest.php` échoue sur l'interface absente.
- [x] **3. Implémenter** les champs et les deux sens du mapper. Ne pas changer le défaut historique `optInNewsletter = true` ; les nouveaux parcours devront fixer explicitement leur état initial.
- [x] **4. GREEN :** même commande, puis `make backend-cs-check` et `make backend-stan`.
- [x] **5. Générer et relire :** `docker compose exec -T php php bin/console make:migration`. Uniquement les trois colonnes d'inscription libre, aucun backfill de consentement ou d'inscription confirmée. Donner une description explicite et retirer le commentaire généré inutile. Présenter la migration avant de demander son exécution ; préparer la base de test selon les conventions existantes.

**Résultat partie 1 :** cinq tests écrits et exécutés avant implémentation : échecs sur les méthodes absentes, puis succès (5 tests / 25 assertions). `make backend-cs-check` et `make backend-stan` réussis. Migration `Version20260930130613` générée, commentaire automatique retiré, description renseignée, simulation réussie. `doctrine:schema:update --dump-sql` contient uniquement les trois colonnes prévues. Migration appliquée sur les bases locales de développement et de test après accord utilisateur ; schéma de développement synchronisé. Aucun consentement historique modifié. Arrêt avant partie 2 pour le déploiement demandé du lot notifications. À cet arrêt historique, les changements étaient non committés et exclus de la première livraison notifications. Les parties 1–2 ont ensuite été livrées lors de la clôture de session.

## Partie 2 — Consentement newsletter du profil

**Files :**
- Create: `jardin-sonore-backend/src/Infrastructure/Portal/PortalNewsletterSubscriptionManager.php`.
- Modify: `jardin-sonore-backend/src/Application/Controller/PortalApiController.php`.
- Modify: `jardin-sonore-backend/tests/Functional/Application/Controller/PortalApiControllerTest.php`.
- Create: `jardin-sonore-backend/tests/Integration/Infrastructure/Portal/PortalNewsletterSubscriptionManagerTest.php`.
- Modify: `jardin-sonore-client/src/lib/portal/types.ts`, `src/app/portail/actions.ts`, `src/components/portal/PortalProfileForm.tsx`, `src/i18n/dictionaries/fr.ts` (chemins relatifs au client).
- Adapter les fixtures `PortalAccount` des tests front existants repérées par recherche du champ `newSessionNotificationsEnabled`.

**Interfaces :** `PortalNewsletterSubscriptionManager::isEnabled(UserEntity $userEntity): bool` et `setEnabled(UserEntity $userEntity, bool $enabled): void`. Le GET expose `newsletterSubscribed: bool`; le PATCH accepte un champ optionnel `newsletterSubscribed: bool`. Type non booléen présent : 422, sans sauvegarde partielle. Le manager recherche le contact par adresse normalisée et utilise le repository infrastructure existant ; le contrôleur conserve la transaction de sauvegarde du profil.

- [x] **1. Tests :** GET sans contact => false et aucune création ; consentement historique actif => true ; adresse désinscrite/inactive => false. PATCH sans le champ ne touche pas le consentement. Opt-in explicite => réutilisation de la fiche ou création si absente ; aucune appartenance libre créée. Opt-out => opt-in false et date de retrait. Réinscription volontaire depuis le profil => suppression de `unsubscribedAt` si contact actif, conservation du jeton. Contact inactif + demande d'activation => 422, sans réactivation ni changement des noms/préférences. Une adresse partagée reflète le même état dans le profil et la désinscription publique ; préférence de séance inchangée.
- [x] **2. RED :** `docker compose exec -T php php bin/phpunit --filter 'PortalNewsletterSubscriptionManagerTest|PortalApiControllerTest'`.
- [x] **3. Implémenter** la lecture sans mutation et l'écriture explicite. Un opt-out sans contact ne crée rien. Pour un nouveau contact créé par opt-in, fixer l'état par l'action explicite ; ne pas attribuer de confirmation libre. Traiter une collision d'unicité via transaction et relecture, sans continuer avec un EntityManager fermé. Retourner une erreur temporaire contrôlée si la sauvegarde concurrente ne peut être reprise.
- [x] **4. Brancher** la seconde coche, sa traduction et l'action serveur (`formData.get('newsletterSubscribed') === 'on'`). Expliquer que la désinscription concerne cette adresse ; les notifications restent séparées. Ne pas ajouter de parcours de changement d'adresse ni modifier la gestion du succès partiel de l'avatar.
- [x] **5. GREEN :** mêmes tests ; `make backend-cs-check`, `make backend-stan`, puis `npm run lint` et `npm run build` depuis le client. Recette sur le portail existant : deux préférences indépendantes, affichage après rechargement et désinscription depuis un lien de test local.

**Résultat partie 2 :** tests écrits avant implémentation, échecs attendus sur le service/champ absents, puis succès. `PortalNewsletterSubscriptionManager` partage le consentement par adresse ; création concurrente par insertion atomique et relecture sous verrou, sans collision ORM fermant le manager. Profil sauvegardé dans une transaction, 422 sur choix invalide/blocage, 503 sur erreur DBAL. Le nettoyage des contacts sans liens supprimait aussi les abonnés : conservation de leurs données, sans changer le nettoyage des téléphones. Le lien public de désabonnement redirigeait vers `/login` : test RED 302 puis GREEN 200 après règle `PUBLIC_ACCESS` limitée à cette route. Deux cases indépendantes et textes traduits ; fixture du test client complétée.

Recette navigateur locale avec compte fictif `recette-newsletter-20260930@example.test` : newsletter activée seule, état conservé au rechargement, notifications activées séparément, lien public de désabonnement suivi puis profil affichant newsletter false et notifications true. Soumission via `requestSubmit()` pendant la recette. Relais HTTPS temporaire nécessaire aux cookies `__Host-`, arrêté après recette ; navigateur fermé. Aucun mail externe. Lint/build front réussis, 9 tests Node ciblés réussis dans le conteneur ; le lancement global direct rencontre le test TSX préexistant `portal-lyrics` non compatible avec ce runner, sans modification de son outillage. Suite backend finale : 238 tests / 1 371 assertions réussis, avec deux dépréciations et une notice préexistantes. Style et PHPStan réussis ; schéma local synchronisé. Arrêt à la frontière de partie avant les audiences.

## Partie 3 — Résolution des trois sources et option sauvegardée

**Files (relatifs au backend) :**
- Modify: `src/Domain/Model/Mailing/NewsletterAudienceFilter.php`, `src/Infrastructure/Doctrine/Mapper/NewsletterAudienceFilterArrayMapper.php`.
- Modify: `src/Infrastructure/Mailing/DoctrineNewsletterAudienceResolver.php`.
- Create: `tests/Unit/Infrastructure/Doctrine/Mapper/NewsletterAudienceFilterArrayMapperTest.php`, `tests/Integration/Infrastructure/Mailing/NewsletterAudienceResolverTest.php`.

**Interfaces :** argument final `bool $includeFreeSubscribers = false` et `NewsletterAudienceFilter::includesFreeSubscribers(): bool`. Clé JSON `includeFreeSubscribers` : absente => false ; présente non booléenne => exception de validation. `hasActiveCriteria()` tient compte de cette option. Le contrat public `resolve(NewsletterAudienceFilter $newsletterAudienceFilter, ?int $limit = null): NewsletterAudienceResolution` est conservé.

- [x] **1. Tests :** filtre historique/aller-retour false et true, type invalide rejeté. Matrice annuaire seul, portail seul, libre seul, union des trois, limite appliquée après déduplication et total réel conservé. Adresse avec casse/espaces fusionnée ; variantes `+` et points distinctes. Groupe libre exclu par défaut, excluant les adresses sans inscription confirmée même sans structure. Contact libre rattaché ensuite à une structure conserve son appartenance. Compte actif avec mot de passe et accès actif à une structure active retenu même si cette structure n'a aucun e-mail ; plusieurs accès => un destinataire ; compte/accès/structure inactifs exclus de la source portail. Opt-in false, désinscription ou contact inactif exclus de toutes les sources.
- [x] **2. RED :** `docker compose exec -T php php bin/phpunit --filter 'NewsletterAudienceFilterArrayMapperTest|NewsletterAudienceResolverTest'`.
- [x] **3. Implémenter** l'option et le mapping. Conserver les règles de l'annuaire. Déterminer les structures du portail depuis leurs entrées et coordonnées, sans passer par leurs liens e-mail ; appliquer type/secteur/statut/tags/géographie avec la sémantique existante et ajouter les structures explicites en union. Une source libre n'applique pas ces filtres géographiques. Réutiliser les expressions de sélection par helpers privés lorsque cela évite leur divergence ; ne pas ajouter une abstraction générale.
- [x] **4. Compléter la matrice** avec structures explicites hors zone, tags, rayon/communes et structure sans adresse postale : exclue si un critère géographique s'applique, incluse si ajout explicite. Une inscription libre confirmée reste sélectionnable hors zone. Une adresse portail sans `EmailContactEntity` n'est pas abonnée et n'est pas créée par le resolver.
- [x] **5. GREEN :** mêmes tests ; style/analyse backend. Vérifier une seule clé normalisée par destinataire dans chaque résolution et préserver l'unicité campagne/adresse existante.

**Résultat partie 3 — 1er octobre :** option `includeFreeSubscribers` ajoutée en argument final avec défaut false, aller-retour JSON conservé et valeur présente non booléenne rejetée, y compris null. Le resolver réunit les contacts éligibles de l'annuaire, les comptes actifs avec mot de passe utilisable et accès actif à une structure active, et les inscriptions libres explicitement confirmées quand l'option est active. La sélection du portail ne dépend pas des liens e-mail de la structure. Les mêmes helpers appliquent type/secteur/statut/tags et géographie, avec les ajouts explicites en union hors critères ; la source libre ignore ces critères. La source annuaire conserve sa priorité pour le nom affiché lors d'un doublon, et les abonnés libres n'effacent pas ce nom. Aucun consentement créé ni modifié par la résolution, aucun destinataire figé réécrit.

Deux nouveaux fichiers de tests, 28 cas : échecs constatés avant implémentation sur l'option absente, la validation et l'absence des destinataires portail, puis succès. Matrice des trois sources, déduplication avant limite/total réel, casse/espaces, variantes `+` et points, consentements retirés/inactifs, comptes inutilisables, accès révoqués, structure sans e-mail ou sans adresse postale, filtres hérités par les personnes de l'annuaire, tags, communes/départements/régions et rayon depuis un point ou une commune, structures explicites hors zone et inscription libre conservée après rattachement. Suite finale : `docker compose exec -T php php -d memory_limit=512M bin/phpunit --display-deprecations --display-phpunit-notices`, **266 tests / 1 449 assertions réussis**, avec la notice préexistante du mock logger de `GenerateSessionDocumentHandlerTest`. Premier lancement global arrêté par la limite PHP de 128 Mo lors de la compilation du conteneur ; relance avec 512 Mo réussie. Les deux dépréciations déjà connues sont apparues à froid, absentes de la dernière exécution après réchauffement du cache. `make backend-cs-check`, `make backend-stan` réussis. Aucun changement front ni de schéma, aucune migration ; branche de reprise existante conservée. Aucun commit, envoi externe ou déploiement. Arrêt avant partie 4 ; l'option UI et la conservation lors des reconstructions de filtres restent en partie 5.

Relecture indépendante finale de la partie 3 : aucun problème important détecté sur le filtre, le mapping, le resolver et les deux nouveaux fichiers de tests. Relecture en lecture seule, sans relance des tests par le relecteur.

## Partie 4 — Consentement courant avant livraison

**Files (relatifs au backend) :**
- Create: `src/Application/Mailing/NewsletterRecipientEligibilityInterface.php`, `src/Infrastructure/Mailing/DoctrineNewsletterRecipientEligibility.php`.
- Modify: `src/Application/Mailing/MessageHandler/SendMailingCampaignRecipientMessageHandler.php`, `src/Application/Mailing/MailingDeliveryQueueInterface.php`, `src/Infrastructure/Mailing/DoctrineMailingDeliveryQueue.php` et `config/services.php` pour l'alias d'interface.
- Create: `tests/Integration/Infrastructure/Mailing/NewsletterRecipientEligibilityTest.php`, `tests/Unit/Application/Mailing/MessageHandler/SendMailingCampaignRecipientMessageHandlerTest.php`.
- Adapter les doubles de `MailingDeliveryQueueInterface` existants après recherche des implémentations.

**Interfaces :** `NewsletterRecipientEligibilityInterface::isEligible(string $emailAddress): bool` ; lecture scalaire DBAL courante de `active`, `opt_in_newsletter`, `unsubscribed_at`, sans entité ORM déjà hydratée ni changement d'adresse. `MailingDeliveryQueueInterface::markCancelled(int $deliveryRecipientId): void` réutilise l'état existant, sans migration.

- [x] **1. Tests :** message mis en file puis retrait du consentement via une seconde connexion => aucun appel au renderer/sender, livraison cancelled. Contact absent/inactif => même résultat ; contact éligible => envoi et sent ; SMTP en échec => failed et reprise inchangée. Une annulation du dernier destinataire termine la campagne sans la laisser en processing ; une campagne stoppée reste stoppée ; une campagne avec une autre livraison failed conserve son état d'échec.
- [x] **2. RED :** `docker compose exec -T php php bin/phpunit --filter 'NewsletterRecipientEligibilityTest|SendMailingCampaignRecipientMessageHandlerTest'`.
- [x] **3. Implémenter** le contrôle immédiatement avant la préparation/envoi et la branche cancelled. Les branches sent et cancelled passent par la même logique finale de progression de campagne ; pas de retour anticipé qui oublie cette finalisation. Ne pas convertir une erreur de base en annulation réussie. L'envoi de test explicite reste inchangé.
- [x] **4. GREEN :** mêmes tests ; contrôles backend. Documenter la fenêtre SMTP résiduelle : un retrait concurrent après le dernier contrôle ne peut pas annuler un message déjà accepté par le serveur.

**Résultat partie 4 — 1er octobre :** contrôle scalaire DBAL par adresse normalisée, sans relecture d'une entité ORM hydratée, avant rendu/SMTP. Contact absent, inactif, sans opt-in ou désinscrit : livraison `cancelled`, sans rendu/envoi. Les branches envoyée et annulée partagent la finalisation existante : dernière annulation => campagne terminée, campagne stoppée conservée, autres livraisons échouées => campagne en échec. Une erreur DBAL reste une erreur propagée pour reprise, comme l'échec SMTP. Aucun changement de l'envoi de test explicite ni de l'instantané des destinataires. Une désinscription concurrente après le dernier contrôle ne peut pas annuler un message déjà accepté par SMTP.

Deux nouveaux fichiers de tests, **14 cas / 106 assertions** : échecs observés sur service/classe/méthode absents avant implémentation, puis succès. Test réel de mise en file et de retrait via une seconde connexion, contact ORM resté hydraté à l'ancien consentement, aucun renderer/sender appelé et livraison annulée ; fixtures fictives committées uniquement dans la base de test pour cette vérification, nettoyées après test. Couverture contact absent/inactif/désinscrit, normalisation, envoi éligible, dernière annulation, destinataires restants, campagne stoppée, autres échecs et propagation des erreurs SMTP/lecture pour reprise. Recherche de toutes les implémentations/doubles de la queue : seule l'implémentation Doctrine existante à compléter. Alias d'interface ajouté en configuration et testé via le conteneur.

Vérifications : `docker compose exec -T php php -d memory_limit=512M bin/phpunit --filter 'NewsletterRecipientEligibilityTest|SendMailingCampaignRecipientMessageHandlerTest'`, puis suite complète avec `--display-deprecations --display-phpunit-notices` : **280 tests / 1 555 assertions réussis**. Deux dépréciations PHP 8.5 préexistantes (`$http_response_header` dans les deux commandes de synchronisation des communes) et une notice PHPUnit du mock logger déjà connue. `make backend-cs-check`, `make backend-stan`, `docker compose exec -T php php -d memory_limit=512M bin/console lint:container` et `git diff --check` réussis. Relecture indépendante sans défaut important. Aucun changement de schéma, migration, envoi externe, commit ou déploiement. Arrêt avant partie 5.

## Partie 5 — Backoffice, masques, extensions et recette du lot

**Complément demandé le 1er octobre :** prévoir dans la rubrique E-mails du backoffice la gestion des abonnés libres (ajout et modification), avec des filtres permettant de distinguer ces inscriptions et l'état d'abonnement. Réutiliser le contact e-mail partagé ; distinguer l'inscription manuelle de la confirmation publique à venir et conserver l'historique du consentement. Examiner les écrans/formulaires existants et préciser les actions avant cette extension de la partie 5. Cette demande reste à implémenter ; aucune interface de gestion nouvelle livrée en partie 4.

**Files (relatifs au backend) :**
- Modify: `src/Application/Form/Model/MailingAudienceFormModel.php`, `src/Application/Form/MailingAudienceType.php`, `src/Application/Twig/Component/MailingAudience.php`.
- Modify: `src/Application/Controller/MailingAudienceMaskController.php`, `templates/components/MailingAudience.html.twig`, `translations/mailing.fr.yaml`, `docs/mailing.md`.
- Create: `tests/Functional/Application/Form/MailingAudienceTypeTest.php`, `tests/Integration/Application/Mailing/NewsletterAudienceCampaignFlowTest.php`.
- Modify: [ROADMAP.md](../../../ROADMAP.md), ce plan et le cadrage avec les résultats réels et les limites.

**Interfaces :** `MailingAudienceFormModel::$includeFreeSubscribers = false`, transporté dans `fromAudienceFilter()` et `toAudienceFilter()`. Checkbox `includeFreeSubscribers`, libellé traduit « Inclure les abonnés libres » et aide indiquant qu'ils peuvent être sans structure et sont inclus indépendamment de la zone. Respecter `$locked`. Transporter l'option dans les reconstructions du composant et du contrôleur de masque.

- [ ] **1. Tests :** création/édition de campagne, masque sauvegardé puis appliqué, duplication et extension conservent true ; filtres historiques restent false. Prévisualisation et préparation réelle produisent le même total dédupliqué. Extension d'une campagne sélectionnant la même adresse via une nouvelle source => aucun réenvoi ; seules les adresses nouvelles sont ajoutées. Formulaire verrouillé ne peut pas modifier l'option. Transport de l'option lors de la matérialisation des communes ; éviter tout élargissement de la géographie existante.
- [ ] **2. RED :** `docker compose exec -T php php bin/phpunit --filter 'MailingAudienceTypeTest|NewsletterAudienceCampaignFlowTest'`.
- [ ] **3. Implémenter** la case et l'explication dans les campagnes et les masques ; afficher son activation dans le récapitulatif d'audience et le total après déduplication. Ne pas ajouter un décompte par source qui compterait plusieurs fois les mêmes adresses. Utiliser les composants/formulaires existants ; vérifier les reconstructions dans tous les `new NewsletterAudienceFilter` concernés.
- [ ] **4. GREEN et recette :** mêmes tests ; suite backend complète `docker compose exec -T php php -d memory_limit=512M bin/phpunit --display-deprecations --display-phpunit-notices`, style/analyse backend et lint Twig/YAML des fichiers modifiés. Recette navigateur sur les services existants avec données fictives : profil, groupe libre, total, masque et extension. Aucun envoi à un tiers ; transport de test ou Mailpit uniquement.
- [ ] **5. Clôture :** documenter les changements et les commandes réellement exécutées. Contrôler le diff de schéma : aucune variation parasite ; si la migration n'a pas été autorisée, expliciter cette limite. Mettre à jour la roadmap : prochain lot = inscription publique avec confirmation, pas encore réalisée. Présenter un message de commit, sans commit/déploiement automatique.

## Revue du plan

- Lot 2 couvert : consentement partagé, groupe libre, trois sources, conservation du ciblage, profils, masques et extensions.
- Les changements d'adresse de comptes ne sont pas proposés dans l'interface. Les règles déjà décrites restent applicables au futur parcours ; ici, les lectures utilisent l'adresse actuelle et ne réécrivent pas les destinataires figés.
- Lot 3 distinct : formulaire footer, anti-robot, limite de fréquence, demande temporaire, mail et POST de confirmation feront l'objet d'un autre plan. Les données de confirmation de la partie 1 sont préparées sans fabriquer de confirmations historiques.
- Résultats réels des parties 1–4 consignés ci-dessus ; parties 1–2 déployées, parties 3–4 préparées pour la livraison autorisée du 1er octobre, partie 5 ouverte.

## Préparation de livraison — 1er octobre

Deux commits distincts autorisés : résolution des audiences (partie 3), puis consentement avant envoi (partie 4) et suivi documentaire. Tag de référence prévu : `deploy-newsletter-audiences-consent-20261001-01`, à pousser avec la branche avant déploiement backend. Front inchangé. Vérifications avant commit : 280 tests / 1 555 assertions, style et PHPStan réussis ; seule la notice PHPUnit préexistante apparaît dans cette exécution. Production contrôlée en lecture seule : migrations à jour, schéma synchronisé, files Messenger `async`/`failed` vides. Le déploiement utilisera un export propre du tag, afin d'exclure les configurations locales et caches non versionnés. Le résultat de l'exécution et les contrôles HTTP seront rapportés après déploiement.
