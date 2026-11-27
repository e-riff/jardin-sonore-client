# Audience newsletter et préférences — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Distinguer les préférences newsletter/démarchage sur l'adresse existante et réunir cœur de cible et abonnés confirmés dans les newsletters.

**Architecture:** Extension additive de `email_contact`, politique d'éligibilité selon le type et service transactionnel commun aux écritures. Un masque identifié par rôle sert de modèle aux nouvelles newsletters. Les destinataires historiques et leurs snapshots ne sont pas reconstruits.

**Tech Stack:** Symfony, Doctrine ORM/DBAL, Twig, Symfony Forms, Messenger ; Next.js pour les préférences du portail et traductions existantes. Aucune dépendance nouvelle.

**Spec:** [Conception approuvée](../specs/2026-11-27-newsletter-preferences-design.md). Les précisions après relecture priment sur les formulations initiales. [Audit](../../audit-newsletter-audience-preferences-2026-11-27.md).

## Global Constraints

- Une adresse unique ; conserver UUID, liens, jetons, activité, historiques et preuves d'inscription.
- Aucun accord historique converti en abonnement volontaire ; aucun refus ni blocage levé par migration/import.
- Refus newsletter et refus démarchage indépendants ; dates inconnues restent nulles.
- Réinscription newsletter sans levée du refus démarchage ; aucun abonnement ajouté implicitement au démarchage.
- Anciennes campagnes conservatrices ; anciens liens globaux toujours globaux ; notifications de service indépendantes.
- Pas de nouvel élargissement territorial automatique ; masque non configuré bloque les nouvelles newsletters.
- Pas d'exécution de migration sans accord distinct ; pas de commit ni déploiement sans autorisation correspondante.
- Exécuter dans ce dossier sur une branche dédiée selon la préférence utilisateur déjà exprimée ; préserver les deux documents de conception/audit non commités.

## Review Focus

- Refus concurrent avec confirmation : verrou commun et demandes consommées (tâche 2).
- Abonné hors zone partagé entre plusieurs établissements : un envoi, seules associations ciblées figées (tâche 3).
- Ancienne campagne déjà en file : refus ciblé récent honoré sans réinterprétation historique (tâche 3).
- Masque configuré sans limite géographique versus masque non configuré : états distincts (tâche 4).
- Adresse invalide ou sans admission historique : inscription ne réactive pas le blocage technique ; réadmission professionnelle explicite (tâches 1, 2 et 5).

## Cartographie des fichiers

Les chemins backend ci-dessous sont relatifs à `jardin-sonore-backend/`. Conserver les patterns du domaine, mapper et mapping PHP existants.

- États : `src/Domain/Model/AddressBook/EmailContact.php`, `src/Infrastructure/Doctrine/Entity/EmailContactEntity.php`, `src/Infrastructure/Doctrine/Mapper/EmailContactMapper.php`, mapping `App.Infrastructure.Doctrine.Entity.EmailContactEntity.php`.
- Écritures : nouveau `src/Infrastructure/Mailing/MailingPreferenceManager.php`, managers d'inscription existants, repository EmailContact, traitement des adresses invalides et liens de coordonnées.
- Lecture : `DoctrineNewsletterAudienceResolver`, `DoctrineNewsletterRecipientEligibility`, interfaces applicatives et tous leurs appelants web/MCP/file.
- Masque : modèle/entité/mapper/mapping `MailingAudienceMask`, services de création/modification/suppression et création de campagne.
- HTTP : nouveau `MailingPreferencesController`, formulaire et template ; contrôleurs abonnés, portail, API et leurs DTO existants.
- Présentation : formulaires et templates mailing/annuaire/portail, traductions backend et dictionnaire FR du client si le profil y expose ces préférences.
- Migration : un fichier final généré avec `make:migration`, regroupant colonnes et rôle du masque avant première mise en production.

## Tâche 1 — États persistés et transition historique

**Interfaces produites :** état `directoryMailingAllowed: bool`, `newsletterRefused: bool`, `newsletterRefusedAt: ?DateTimeImmutable`, `outreachRefused: bool`, `outreachRefusedAt: ?DateTimeImmutable`, accesseurs explicites sur domaine/entité. Ajouter une opération commune de recalcul de la projection historique ; ne pas exposer les anciens setters comme voie métier indépendante.

- [x] Ajouter dans `tests/Unit/Infrastructure/Doctrine/Mapper/EmailContactMapperTest.php` une régression de conservation des nouveaux états, dates nulles et `updatedAt`.
- [x] Ajouter un test de transition couvrant les combinaisons accord historique/date globale/activité/abonnement confirmé : admission = ancien accord vrai et absence de date globale ; refus des deux si accord faux ou date présente ; date reprise seulement si connue.
- [x] Exécuter ces tests ciblés, constater l'absence des nouveaux états, puis implémenter domaine/entité/mapper/mapping.
- [x] Définir la projection : accord historique vrai si actif, (admission ou abonnement confirmé) et aucun refus ; date historique = première date connue des refus actifs, sinon null. Un refus sans date coupe l'accord. La projection ne fabrique pas d'abonnement.
- [x] Tester la projection après refus ciblé, refus global et réinscription. Les lecteurs modernes ignorent ces deux champs historiques.
- [x] Reporter la génération finale de migration après le rôle du masque (tâche 4) ; aucune application de schéma à ce stade.

## Tâche 2 — Opérations de préférences atomiques

**Interface produite :** `MailingPreferenceManager` expose `refuse(string $emailContactUuid, ?MailingCampaignType $scope): void` (null = tout), `confirmNewsletter(string $emailContactUuid, string $origin): void`, `allowDirectoryMailing(string $emailContactUuid): void`, `allowOutreach(string $emailContactUuid): void`. Si une transaction existe déjà, y participer sans commit indépendant. Toutes les écritures prennent le même verrou de contact.

- [x] Compléter `FreeNewsletterSubscriptionManagerTest`, `NewsletterSubscriptionConcurrencyTest` et `PortalNewsletterSubscriptionManagerTest` : inscription d'une adresse liée crée l'abonnement indépendant ; réinscription newsletter maintient refus démarchage ; adresse inactive reste bloquée.
- [x] Exécuter le groupe ciblé et constater les différences avec le comportement actuel.
- [x] Implémenter le manager ; intégrer demande/confirmation publique, backoffice attesté, portail authentifié, refus de domaine et batch invalides. Un refus consomme toutes les demandes antérieures non consommées ; un refus technique n'est pas une permission de réactivation.
- [x] Adapter les formulaires de liens et `DirectoryEstablishmentUpserter` : nouvelle adresse importée admise avec origine import, nouvelle adresse sans autorisation non admise ; adresse connue réutilisée sans changer refus/admission. Toute modification par ancien setter doit être remplacée ou limitée à l'hydratation.
- [x] Ajouter la régression batch : demande créée, refus groupé, tentative de confirmation → aucune réactivation. Tester concurrence réelle avec les verrous existants.
- [x] Réexécuter les tests du groupe ; vérifier que lever refus démarchage ne change ni admission ni abonnement et que réadmission ne lève aucun refus.

## Tâche 3 — Politique de sélection et contrôle avant SMTP

**Interfaces modifiées :** ajouter `?MailingCampaignType $campaignType` explicitement à `NewsletterAudienceResolverInterface::resolve` et `NewsletterRecipientEligibilityInterface::isEligible`. Adapter tous les appelants et doubles de test. Null correspond uniquement aux campagnes historiques ; nouvelles campagnes restent obligatoirement typées.

- [x] Compléter `NewsletterAudienceResolverTest`, `NewsletterRecipientEligibilityTest`, `MailingWorkerGuardTest` et `NewsletterAudienceCampaignFlowTest` avec une matrice newsletter/démarchage/historique et refus indépendants.
- [x] Vérifier les échecs ciblés puis adapter résolveur, prévisualisation LiveComponent, préparation, mise en file, extensions et worker.
- [x] Newsletter : branche annuaire exige admission ; branche abonnés exige confirmation ; les deux exigent actif et absence de refus newsletter. Inclusion des abonnés imposée pour une campagne typée newsletter, rejet explicite des paramètres contradictoires via MCP.
- [x] Démarchage : admission et absence de refus démarchage ; pas de branche abonnés. Maintenir l'exclusion glissante et l'union des snapshots réellement ciblés.
- [x] Historique : projection globale et aucun refus ; pas de changement de type ni reconstruction des destinataires anciens.
- [x] Tester refus intervenu après mise en file et réinscription newsletter d'une adresse sans admission : newsletter possible, démarchage impossible. Le worker relit la préférence actuelle, sans recalcul géographique.
- [x] Vérifier une adresse retenue à la fois par abonnement et deux structures : un destinataire et uniquement les structures retenues par le ciblage ; extension sans réécriture d'un destinataire déjà présent.

## Tâche 4 — Masque réservé et migration finale

**Interfaces produites :** rôle nullable `newsletter` unique sur masque ; absence de rôle = masque courant. `MailingAudienceMaskRepositoryInterface::findNewsletterMask(): ?MailingAudienceMask`. Masque absent = non configuré ; masque configuré sans restriction géographique reste valide. La création du rôle passe par une action explicite choisissant/créant ses critères.

- [x] Compléter `ManageMailingAudienceMaskTest`, `MailingAudienceMaskControllerTest`, `CreateTypedMailingCampaignTest` et `MailingDraftManagerTest` : protection de suppression/retrait du rôle, unicité, absence de masque et copie indépendante des critères.
- [x] Implémenter rôle, requête et validations serveur communes web/MCP. Un masque réservé ne peut changer de rôle ni être supprimé. Ses critères peuvent évoluer.
- [x] Nouvelle newsletter : copier le masque et inclure les abonnés ; masque absent → erreur traduite orientant vers sa configuration. Les anciennes campagnes ne sont pas bloquées faute de masque. Réapplication manuelle sur brouillon seulement, avec récap avant sauvegarde ; ne pas recopier le masque à l'envoi.
- [x] Générer avec `make:migration`, relire description, SQL et down : colonnes préférences, reprise déterministe et rôle unique uniquement. Aucun masque national ou copie implicite de Forez et Gier, aucune suppression de données historiques.
- [x] Présenter la migration et demander l'accord requis pour l'appliquer en développement/test. Après accord : appliquer, vérifier les scénarios de reprise et les comptes, schema validate et nouvelle génération vide. Ne pas appliquer les anciennes migrations en attente.
- [x] Vérifier le retour arrière en simulation ; tout essai destructif sur base dédiée demande son autorisation. Ne pas réécrire une migration déjà appliquée en production.

## Tâche 5 — Préférences publiques, portail et administration

**Interfaces produites :** GET `/mailing/preferences/{token}` sans mutation ; POST même route avec formulaire CSRF, portée/action validées serveur. Ne pas exposer une adresse dans l'URL. L'ancien GET `/newsletter/unsubscribe/{token}` conserve son refus global.

- [x] Ajouter `MailingPreferencesControllerTest` : GET neutre, jeton invalide, POST sans CSRF rejeté, refus ciblé/global, confirmation lisible et aucune réactivation technique.
- [x] Implémenter contrôleur/formulaire/template traduits : « Ne plus recevoir la newsletter », « Ne plus recevoir de propositions », « Tout arrêter ». Page publique permet les refus ; réinscription newsletter renvoie au parcours de confirmation existant.
- [x] Adapter profil portail : activer newsletter crée l'abonnement origine portail ; préférence démarchage séparée avec portée sur l'adresse partagée expliquée. Ne jamais coupler notifications de service et mailings. Adapter DTO/API/BFF/types et dictionnaire client ensemble si le profil les consomme.
- [x] Adapter catalogue abonnés et détail de l'adresse : afficher séparément admission, abonnement et refus ; dates inconnues marquées comme telles. Action d'autorisation du ciblage explicite et confirmée, sans inscription ou levée de refus implicite.
- [x] Adapter outils MCP concernés : lecture des états, validation des actions autorisées, révision stable et conflit sur modification concurrente. Les opérations de masse ne réactivent pas les adresses.
- [x] Tester portail authentifié, backoffice et API publique avec la même adresse partagée ; conserver attestation administrative, confirmation publique et réponses non révélatrices d'existence.

## Tâche 6 — Liens envoyés et récapitulatif d'audience

**Fichiers :** `TwigNewsletterRenderer`, `SymfonyNewsletterMailSender`, templates HTML/texte existants et tests de rendu ; contrats de rendu adaptés pour transporter le type sans l'inférer du template.

- [x] Inventorier les en-têtes `List-Unsubscribe` éventuels et tous les constructeurs d'URL de désabonnement. Tout nouveau lien ou en-tête doit avoir une portée cohérente avec le type ; conserver les contrats globaux historiques.
- [x] Compléter `TwigNewsletterRendererTest` et les tests de mail : newsletter/outreach/historique, HTML/texte, même effet annoncé, URL opaque et contenu masqué absent.
- [x] Implémenter lien principal vers la page de refus ciblé, plus gestion des préférences ; requête GET neutre pour les nouveaux liens. Si un mécanisme one-click existe, conserver son protocole POST et sa portée explicite, sans en créer un implicitement.
- [x] Récap avant envoi : afficher taille union, contributions annuaire/abonnés (recouvrement annoncé), doublons et exclusions sans présenter une somme trompeuse. Distinguer « sélectionné par masque » et « abonné » dans l'administration.
- [x] Recette navigateur des formulaires réels à 390 px et sur ordinateur, fermeture des dialogues, clavier et noms accessibles ; pas d'envoi à des tiers.

## Tâche 7 — Vérification et préparation de livraison

- [x] Relire les exigences de la conception et les chemins d'écriture : aucun accès métier restant qui ne met à jour que les champs historiques.
- [x] Exécuter les tests ciblés de chaque tâche, puis suite backend `docker compose exec -T php php -d memory_limit=1G ./bin/phpunit`, `make backend-lint`, Twig/YAML/container et compilation `make symfony-assets`.
- [x] Si client modifié, exécuter lint et build dans `jardin-sonore-client/` ; expliquer tout contrôle impossible.
- [x] Vérifier migration sans diff parasite, comptes d'éligibilité avant/après et absence de réactivation historique. Contrôler les messages en file et la stratégie d'arrêt/reprise des workers.
- [x] Revue indépendante finale, corriger les problèmes concrets et relancer seulement les vérifications concernées.
- [x] Mettre ROADMAP à jour, présenter résultat et risques restants. Pas de commit automatique pendant l'exécution : proposer des commits par sujet selon AGENTS.md. Déploiement seulement après demande explicite, commit/tag/push et application ciblée de migration approuvée.

## État initial

Plan rédigé après approbation de la conception, sans code produit ni schéma modifié. Méthode d'exécution proposée : implémentation directe dans cette session, revue indépendante finale ; les tâches partagent fortement leurs contrats et leur transaction de préférences.

## Bilan d’implémentation en local — 27 novembre

Les sept tâches sont implémentées. Les deux migrations additives générées sont appliquées après accords distincts aux bases locales dev/test ; aucune migration de production jouée. `make:migration` ne détecte aucun changement en développement. La base de test conserve ses différences antérieures hors sujet, sans nouveau diff lié à ce lot. Matrice d'intégration du SQL réel de reprise : huit combinaisons accord/date/activité, 49 assertions, aucune fabrication d’abonnement ou de date.

La revue indépendante a identifié puis permis de corriger : annuaire élargi par filtre vide, préférences périmées resoumises depuis le profil, rôle réservé perdu/supprimé via objet périmé, adresses nouvelles sans admission dans l’ancien régime et confusion inscription/ciblage dans l’administration. Les régressions couvrent leurs effets. Le profil ne transmet plus les préférences inchangées ; une sauvegarde personnelle ne crée donc pas d’inscription volontaire et ne lève pas un refus récent. Les nouvelles audiences n’exécutent la branche annuaire que sur critères annuaire ; les sélections explicites et abonnés gardent leurs branches propres.

Autres finitions : première préférence portail créée par upsert unique, confirmation consommant les demandes en attente, dates distinctes lors d’un ancien refus global, liens typés avec ancre vers l’action annoncée, compteurs de source conservés dans le récap final, masque initial imposé côté MCP et réapplication après aperçu avec révision.

Commandes et preuves : suite `docker compose exec -T php php -d memory_limit=1G bin/phpunit --log-junit var/preferences-tests.xml` : 827 tests / 4 310 assertions, zéro échec (2 dépréciations, 15 notices) ; régression supplémentaire date du lien global 1 / 3 ; `make backend-lint` et style final propres ; 36 Twig, 16 YAML, conteneur et schéma dev valides. `npm run lint`, `npm run build` et 9 tests client via Node du conteneur réussis. Build Next.js avec alerte sécurité sur version préexistante, aucune mise à jour de dépendance. Assets recompilés ; cache local nécessite la limite mémoire de 1 Go.

Recette navigateur : page publique réelle sur contact fictif `.test`, GET neutre, POST de refus newsletter conservant l’autorisation de propositions, viewport et largeur document 390 px ; donnée fictive retirée, navigateur fermé, aucun mail envoyé. Les formulaires internes, le portail et le rendu sont également couverts par HTTP/intégration ; pas de recette visuelle authentifiée complète du profil portail dans cette session.

Arbitrages : travail conservé sans commit conformément à AGENTS.md ; migration des préférences générée avant le masque pour permettre les tests d’intégration ; deux migrations locales conservées, chacune limitée à son besoin. Lecture des campagnes historiques renforcée par admission/abonnement, afin de ne pas sélectionner de nouvelles adresses manuelles sans autorisation. Contrat de jeton opaque dans le chemin conservé comme approuvé : Monolog le masque mais les logs HTTP hébergeur peuvent le retenir.

Livraison ultérieure : commit/tag/push après demande ; prévenir les écritures de préférences avec ancien code pendant la reprise (fenêtre de maintenance coordonnée backend et workers), appliquer exactement les deux versions puis vérifier schéma/comptes. Ne pas lancer les migrations historiques en attente. Choisir le masque réservé via action explicite, sans copie arbitraire de Forez et Gier ni nouveau territoire. Articles publics et calendrier éditorial restent des chantiers séparés.
