# Mailing

## Vue macro

Le module de mailing permet de preparer une newsletter dans le backoffice, de definir son audience a partir de l'annuaire, d'envoyer un test, puis de lancer un envoi reel cadence dans le temps.

L'architecture se decoupe en trois couches fonctionnelles :

1. la campagne stocke le contenu, les recommandations et les regles de ciblage ;
2. la resolution d'audience determine quels contacts sont eligibles ;
3. la livraison met en file, cadence et trace chaque envoi recipient par recipient.

Cette separation permet :

- de preparer une campagne sans envoyer immediatement ;
- de mesurer et verifier l'audience avant lancement ;
- de throttler les envois sur une fenetre glissante ;
- de reprendre un envoi sans recalculer toute la campagne ;
- d'isoler les erreurs par destinataire.

## Parcours fonctionnel

### 1. Creation

Une campagne est creee dans le backoffice avec un titre interne.

Etat initial :

- statut `draft` ;
- audience vide ;
- aucune queue d'envoi.

### 2. Edition du contenu

Le contenu comprend notamment :

- sujet d'e-mail ;
- titre public ;
- sous-titre ;
- texte principal ;
- image hero ;
- CTA ;
- recommandations associees.

Le rendu HTML et texte s'appuie directement sur ce contenu.

### 3. Configuration de l'audience

L'audience peut combiner :

- types d'etablissement ;
- secteurs ;
- statuts client ;
- tags ;
- regions ;
- departements ;
- communes ;
- rayon geographique autour d'un point.

Le point d'origine du rayon peut etre :

- le point maison configure par environnement ;
- une commune de depart ;
- un point personnalise.

### 4. Envoi test

L'envoi test sert a valider :

- le rendu HTML ;
- le rendu texte ;
- le transport SMTP ;
- les liens absolus ;
- le comportement du lien de desinscription.

Il ne peuple pas la table `mailing_delivery_recipient`.

### 5. Mise en file d'une campagne reelle

Quand l'utilisateur confirme l'envoi reel :

1. l'audience est resolue ;
2. la liste des recipients est figee dans `mailing_delivery_recipient` ;
3. chaque ligne est initialement en `pending`.

La campagne part ensuite sur cet instantane, pas sur un recalcul a chaque e-mail.

### 6. Dispatch par vagues

Un cron lance periodiquement la commande de dispatch :

```bash
php bin/console app:mailing:dispatch-pending-campaigns
```

La commande :

1. mesure la capacite disponible dans la fenetre glissante ;
2. reclame un lot limite de recipients `pending` ;
3. les passe en `processing` ;
4. publie un message Messenger par recipient.

### 7. Envoi unitaire par Messenger

Le worker Messenger consomme les messages.

Pour chaque recipient :

1. le rendu final est genere ;
2. le lien de desinscription personnalise est injecte ;
3. l'e-mail est envoye ;
4. le statut passe en `sent` ou `failed`.

### 8. Desinscription

Chaque e-mail embarque un token de desinscription.

Le lien appelle :

- `/newsletter/unsubscribe/{token}`

La route renseigne `unsubscribed_at` sur l'adresse concernee, ce qui l'exclut des campagnes futures.

## Regles metier importantes

### Contacts eligibles

La resolution d'audience ne retient que des e-mails :

- actifs ;
- opt-in newsletter ;
- non desinscrits ;
- avec token de desinscription ;
- rattaches a une entree active.

### Personnes et organisations

Une personne peut etre incluse a travers son organisation.

Consequence :

- les filtres d'organisation et de geographie peuvent faire remonter des personnes rattachees ;
- les campagnes ne ciblent pas seulement les e-mails directement portes par des organisations.

### Audience invalide

Certaines combinaisons de filtres geographiques sont refusees, par exemple :

- rayon actif sans point d'origine exploitable ;
- commune de depart sans code INSEE ;
- point personnalise sans latitude/longitude ;
- point maison non configure dans l'environnement.

## Vue technique

### Points d'entree code

- `src/Application/Controller/MailingController.php`
  Porte la creation, l'edition du contenu, l'audience, la preview, l'envoi test et l'envoi reel.
- `src/Application/Controller/NewsletterController.php`
  Porte la route publique de desinscription.
- `templates/mailing/*.html.twig`
  Ecrans backoffice du module.
- `templates/mailing/email/default.html.twig`
- `templates/mailing/email/default.txt.twig`
  Templates d'e-mail.

### Composants et formulaires

- `src/Application/Twig/Component/MailingAudience.php`
  Live component de configuration de l'audience.
- `src/Application/Form/MailingAudienceType.php`
  Formulaire des filtres de ciblage.
- `src/Application/Form/Model/MailingAudienceFormModel.php`
  Conversion formulaire <-> domaine.
- `src/Application/Form/*Mailing*.php`
  Autres formulaires du module.

### Domaine et cas d'usage

- `src/Domain/Model/Mailing/MailingCampaign.php`
  Aggregate principal de campagne.
- `src/Domain/Model/Mailing/NewsletterAudienceFilter.php`
  Valeur metier des regles de ciblage.
- `src/Application/Mailing/SendMailingCampaign.php`
  Fige l'audience et cree la queue d'envoi reel.
- `src/Application/Mailing/SendMailingCampaignTest.php`
  Enfile l'envoi test via Messenger.
- `src/Application/Mailing/UpdateMailingCampaignAudience.php`
  Met a jour les regles de ciblage.

### Infrastructure

- `src/Infrastructure/Mailing/DoctrineNewsletterAudienceResolver.php`
  Traduit `NewsletterAudienceFilter` en requetes SQL Doctrine DBAL et dedoublonne les destinataires par adresse.
- `src/Infrastructure/Mailing/DoctrineNewsletterAudienceOptionsProvider.php`
  Fournit les choix de formulaire pour tags, regions, departements et communes.
- `src/Infrastructure/Mailing/TwigNewsletterRenderer.php`
  Construit les versions HTML et texte et injecte le placeholder de desinscription.
- `src/Infrastructure/Mailing/MailingDeliveryRecipientStore.php`
  Gere la queue d'envoi et les transitions de statuts.
- `src/Infrastructure/Mailer/SymfonyNewsletterMailSender.php`
  Envoi effectif via Symfony Mailer.

### Messenger et livraison

- `src/Application/Mailing/MessageHandler/SendMailingCampaignRecipientMessageHandler.php`
  Envoi reel recipient par recipient.
- `src/Application/Mailing/MessageHandler/SendMailingCampaignTestMessageHandler.php`
  Envoi test.
- `src/Application/Command/DispatchPendingMailingCampaignsCommand.php`
  Ouvre les vagues d'envoi selon la capacite disponible.

Variables de cadence importantes :

- `MAILING_WINDOW_LIMIT`
- `MAILING_WINDOW_MINUTES`
- `MAILING_DISPATCH_BATCH_SIZE`
- `MAILING_HOME_LATITUDE`
- `MAILING_HOME_LONGITUDE`

### Tables et donnees

#### `mailing_campaign`

Contient notamment :

- le contenu de la campagne ;
- le statut global ;
- `audience_filter`, stocke sous forme de structure serialisee Doctrine.

#### `mailing_recommendation`

Porte les recommandations integrees a la newsletter.

#### `mailing_delivery_recipient`

File d'attente technique de l'envoi reel.

Champs importants :

- `campaign_uuid` ;
- `email_address` ;
- `unsubscribe_token` ;
- `status` ;
- `queued_at` ;
- `dispatched_at` ;
- `sent_at` ;
- `failed_at` ;
- `last_error`.

Cette table est la source de verite pour suivre l'execution d'une campagne reelle.

## Statuts

### Statut de campagne

Le module manipule un statut global de campagne pour verrouiller les actions incompatibles avec un envoi deja engage.

### Statut de recipient

Les statuts principaux de `mailing_delivery_recipient` sont :

- `pending` ;
- `processing` ;
- `sent` ;
- `failed` ;
- `cancelled`.

## Commandes et verifications utiles

Exemples :

```bash
php bin/console app:mailing:dispatch-pending-campaigns
php bin/console messenger:consume async
```

Avant un changement de flux mailing :

- verifier le rendu des templates Twig ;
- verifier l'audience resolue sur une campagne de test ;
- verifier le cycle test -> queue -> dispatch -> envoi unitaire ;
- verifier la route publique de desinscription.

Lecture pratique :

- `pending` : pas encore remis a Messenger ;
- `processing` : pris par une vague ;
- `sent` : succes final ;
- `failed` : echec final pour ce recipient ;
- `cancelled` : recipient annule avant envoi.

## Ciblage geographique

Le ciblage geographique supporte deux familles de filtres :

- des filtres administratifs : region, departement, commune ;
- un filtre de rayon.

Le rayon repose sur des coordonnees.

Sources possibles :

- `MAILING_HOME_LATITUDE` / `MAILING_HOME_LONGITUDE` pour le point favori ;
- les coordonnees de la commune choisie ;
- les coordonnees saisies dans le formulaire.

Sans coordonnees valides, le rayon ne peut pas etre calcule.

## Type d'etablissement `test`

Le type `test` existe pour isoler des destinataires techniques sans polluer les audiences reelles.

Usage recommande :

- cibler `Types d'etablissement = Test mailing` ;
- ajouter si besoin une contrainte geographique ou un tag ;
- ne jamais le melanger a des campagnes de production reelles.

La migration `Version20260630213000` cree 4 organisations de test a Cornimont avec des e-mails invalides :

- `test-cornimont-01@jardinsonore-mailing-test.invalid`
- `test-cornimont-02@jardinsonore-mailing-test.invalid`
- `test-cornimont-03@jardinsonore-mailing-test.invalid`
- `test-cornimont-04@jardinsonore-mailing-test.invalid`

Objectif :

- en local, Mailpit recupere les envois pour verifier le rendu ;
- en production, le SMTP doit produire des retours d'erreur sans toucher de vraies boites.

## Throttling

Le debit d'envoi reel repose sur une fenetre glissante.

Variables :

- `MAILING_WINDOW_LIMIT`
  Nombre maximal d'e-mails que la commande a le droit de dispatcher dans la fenetre.
- `MAILING_WINDOW_MINUTES`
  Duree de la fenetre glissante en minutes.
- `MAILING_DISPATCH_BATCH_SIZE`
  Taille maximale d'une vague publiee dans Messenger a chaque execution.

Exemple prudent pour tests :

```dotenv
MAILING_WINDOW_LIMIT=1
MAILING_WINDOW_MINUTES=60
MAILING_DISPATCH_BATCH_SIZE=1
```

Exemple plus rapide :

```dotenv
MAILING_WINDOW_LIMIT=1
MAILING_WINDOW_MINUTES=1
MAILING_DISPATCH_BATCH_SIZE=1
```

Conseils :

- le cron peut tourner toutes les minutes ;
- si la fenetre est pleine, la commande ne dispatchera rien ;
- pour un debit strictement maitrise, garder `MAILING_DISPATCH_BATCH_SIZE <= MAILING_WINDOW_LIMIT`.

## Variables d'environnement

## Minimum vital

```dotenv
APP_ENV=prod
APP_SECRET=...
DATABASE_URL=...
MAILER_DSN=...
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
DEFAULT_URI=https://admin.jardinsonore.fr
DEFAULT_CONTACT=contact@jardinsonore.fr
MAILING_FROM_NAME=Jardin Sonore
MAILING_WINDOW_LIMIT=1
MAILING_WINDOW_MINUTES=60
MAILING_DISPATCH_BATCH_SIZE=1
MAILING_HOME_LATITUDE=...
MAILING_HOME_LONGITUDE=...
```

## Role de chaque variable

- `DATABASE_URL`
  Base de donnees application et transport Messenger Doctrine.
- `MAILER_DSN`
  Transport SMTP reel.
- `MESSENGER_TRANSPORT_DSN`
  Transport de queue Messenger.
- `DEFAULT_URI`
  Base absolue utilisee pour les liens comme la desinscription.
- `DEFAULT_CONTACT`
  Contact public utilise par les templates.
- `MAILING_FROM_NAME`
  Nom expediteur visible.
- `MAILING_WINDOW_LIMIT`
  Quota d'e-mails dispatchables sur la fenetre.
- `MAILING_WINDOW_MINUTES`
  Duree de la fenetre glissante.
- `MAILING_DISPATCH_BATCH_SIZE`
  Taille max d'une vague par passage du cron.
- `MAILING_HOME_LATITUDE`
- `MAILING_HOME_LONGITUDE`
  Point par defaut du ciblage geographique.

## Points d'attention

- `DEFAULT_URI` doit etre public et correct ;
- les coordonnees home sont obligatoires pour la carte et le rayon depuis le point favori ;
- `MAILER_DSN` doit correspondre au fournisseur reel ;
- `MESSENGER_TRANSPORT_DSN` doit etre partage entre l'application et le worker.

## Cron et worker

Le mailing reel a besoin de deux mecanismes separes.

### 1. Worker Messenger

Il doit tourner en continu pour consommer les messages d'envoi.

Sans lui :

- le cron peut publier des messages ;
- aucun e-mail ne partira effectivement.

### 2. Cron de dispatch

Commande :

```bash
php bin/console app:mailing:dispatch-pending-campaigns
```

Frequence recommandee :

- toutes les minutes.

Avec `1 mail / 60 minutes`, le cron peut quand meme tourner chaque minute. La commande n'enverra rien tant que la fenetre reste pleine.

## Logs et supervision

Le channel Monolog dedie est `mailing_delivery`.

Fichiers utiles :

- `var/log/*/mailing_delivery.log`
- logs Messenger du worker ;
- logs cron de dispatch.

Evenements a surveiller :

- campagne mise en file ;
- lot reclame ;
- recipient marque `sent` ;
- recipient marque `failed` ;
- campagne terminee ;
- saturation durable de la fenetre ;
- backlog `pending` anormalement long.

## Compatibilite base de donnees

Le module manipule des UUID binaires dans plusieurs tables.

Point important :

- ne pas supposer la disponibilite de fonctions SQL specifiques a MySQL 8 comme `BIN_TO_UUID()` ;
- preferer une lecture binaire brute suivie d'une conversion cote PHP quand le code doit rester compatible MariaDB.

Le correctif de juin 2026 sur `DoctrineNewsletterAudienceOptionsProvider` vient de cette contrainte : la page de ciblage prod cassait parce que MariaDB n'exposait pas `BIN_TO_UUID()`.

## Procedure d'exploitation

### Avant premiere utilisation en prod

1. Verifier les migrations.
2. Verifier les variables d'environnement mailing.
3. Verifier `DEFAULT_URI`.
4. Verifier le worker Messenger.
5. Verifier le cron de dispatch.
6. Verifier le SMTP reel.
7. Ouvrir une campagne de test ciblee sur le type `test`.

### Avant une campagne reelle

1. Previsualiser HTML et texte.
2. Envoyer un test manuel.
3. Verifier l'audience estimee.
4. Verifier le throttling.
5. Verifier que la fenetre de debit est adaptee.
6. Lancer seulement ensuite l'envoi reel.

### Pendant l'envoi

1. Suivre les logs de dispatch.
2. Suivre les logs Messenger.
3. Observer l'evolution des statuts `pending`, `processing`, `sent`, `failed`.

### Apres l'envoi

1. Verifier qu'il ne reste pas de `pending` ou `processing` inattendus.
2. Quantifier les `failed`.
3. Verifier les desinscriptions eventuelles.

## Depannage

### La page audience renvoie 500 en prod mais pas en local

Verifier en priorite :

- compatibilite SQL entre local et prod ;
- variables `MAILING_HOME_LATITUDE` / `MAILING_HOME_LONGITUDE` ;
- disponibilite des coordonnees de communes ;
- logs PHP / Symfony ;
- logs Apache.

Cas reel rencontre le 30 juin 2026 :

- prod en MariaDB ;
- code utilisant `BIN_TO_UUID(uuid)` dans le provider des tags ;
- resultat : 500 sur `/mailing/{uuid}/audience`.

### Le cron tourne mais rien ne part

Verifier :

- worker Messenger actif ;
- `MESSENGER_TRANSPORT_DSN` partage ;
- fenetre de throttling non saturee ;
- recipients toujours en `pending`.

### Les tests partent mais pas les campagnes reelles

Verifier :

- remplissage de `mailing_delivery_recipient` ;
- execution du cron de dispatch ;
- consommation du transport Messenger ;
- statut des recipients en base.

### Le lien de desinscription est faux

Verifier :

- `DEFAULT_URI` ;
- la route publique `/newsletter/unsubscribe/{token}` ;
- l'environnement utilise par le renderer.

## Fichiers clefs

- `src/Application/Controller/MailingController.php`
- `src/Application/Controller/NewsletterController.php`
- `src/Application/Twig/Component/MailingAudience.php`
- `src/Application/Form/MailingAudienceType.php`
- `src/Application/Form/Model/MailingAudienceFormModel.php`
- `src/Application/Command/DispatchPendingMailingCampaignsCommand.php`
- `src/Application/Mailing/SendMailingCampaign.php`
- `src/Application/Mailing/SendMailingCampaignTest.php`
- `src/Application/Mailing/MessageHandler/SendMailingCampaignRecipientMessageHandler.php`
- `src/Application/Mailing/MessageHandler/SendMailingCampaignTestMessageHandler.php`
- `src/Infrastructure/Mailing/DoctrineNewsletterAudienceResolver.php`
- `src/Infrastructure/Mailing/DoctrineNewsletterAudienceOptionsProvider.php`
- `src/Infrastructure/Mailing/MailingDeliveryRecipientStore.php`
- `src/Infrastructure/Mailing/TwigNewsletterRenderer.php`
- `src/Infrastructure/Mailer/SymfonyNewsletterMailSender.php`
