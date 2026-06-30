# Mailing

## Vue macro

Le module de mailing sert a composer une campagne newsletter, definir son audience a partir de l'annuaire, previsualiser le rendu, envoyer un test, puis lancer un envoi reel en vagues.

Le flux general est le suivant :

1. Une campagne est creee dans le backoffice.
2. Son contenu est edite : sujet, hero, texte, CTA, recommandations.
3. Son audience est calculee a partir des filtres annuaire et geographiques.
4. Un envoi test peut etre declenche vers une adresse libre.
5. Un envoi reel met tous les destinataires eligibles en file d'attente.
6. Un cron declenche regulierement la commande de dispatch.
7. La commande publie une vague limitee de messages dans Messenger.
8. Le worker Messenger envoie les e-mails un par un et met a jour les statuts.
9. Chaque e-mail contient un lien de desinscription personnalise.

## Composants

### Backoffice

- `src/Application/Controller/MailingController.php`
  Gere la creation, l'edition, la previsualisation, l'envoi test et l'envoi reel.
- `src/Application/Form/*Mailing*.php`
  Porte les formulaires de contenu, audience et test.

### Rendu

- `src/Infrastructure/Mailing/TwigNewsletterRenderer.php`
  Construit le HTML et le texte brut a partir des templates Twig.
- `templates/mailing/email/default.html.twig`
- `templates/mailing/email/default.txt.twig`

Le renderer insere une URL absolue de desinscription via `DEFAULT_URI`. En production, cette valeur doit pointer vers l'URL publique du backoffice ou du site qui expose la route `/newsletter/unsubscribe/{token}`.

### Audience

- `src/Infrastructure/Mailing/DoctrineNewsletterAudienceResolver.php`
  Resolve les destinataires depuis l'annuaire.

Les filtres disponibles sont :

- types d'etablissement ;
- secteurs ;
- statuts client ;
- tags ;
- regions, departements, communes ;
- rayon geographique autour d'un point.

La resolution retient uniquement les e-mails :

- actifs ;
- opt-in newsletter ;
- non desinscrits ;
- avec token de desinscription ;
- rattaches a une entree active.

Une personne peut etre selectionnee via l'organisation a laquelle elle est rattachee. C'est important pour les filtres de type d'etablissement, de secteur et de geographie.

### File d'attente et envoi reel

- `src/Application/Mailing/SendMailingCampaign.php`
  Calcule l'audience et peuple `mailing_delivery_recipient`.
- `src/Infrastructure/Mailing/MailingDeliveryRecipientStore.php`
  Gere la file, les reclamations de lots et les statuts.
- `src/Application/Command/DispatchPendingMailingCampaignsCommand.php`
  Applique le throttling et pousse une vague dans Messenger.
- `src/Application/Mailing/MessageHandler/SendMailingCampaignRecipientMessageHandler.php`
  Envoie un destinataire reel et marque `sent` ou `failed`.

Statuts de queue principaux :

- `pending` : en attente de dispatch ;
- `processing` : lot deja reclame et remis a Messenger ;
- `sent` : envoi OK ;
- `failed` : envoi en erreur ;
- `cancelled` : annulation avant dispatch.

### Envoi test

- `src/Application/Mailing/SendMailingCampaignTest.php`
- `src/Application/Mailing/MessageHandler/SendMailingCampaignTestMessageHandler.php`

L'envoi test passe aussi par Messenger mais ne peuple pas `mailing_delivery_recipient`. Il sert a verifier le rendu, le transport SMTP et le lien de desinscription.

### Mailer

- `src/Infrastructure/Mailer/SymfonyNewsletterMailSender.php`

Le mailer Symfony envoie :

- un test via `sendTest()` ;
- un recipient reel via `sendToRecipient()`.

Sur un envoi reel, le token de desinscription est injecte dans le HTML et le texte au moment de l'envoi. Sur un test, si l'adresse de test existe dans `email_contact`, le token correspondant est utilise ; sinon le placeholder reste non resolu.

### Desinscription

- `src/Application/Controller/NewsletterController.php`
- `src/Application/Mailing/UnsubscribeNewsletterRecipient.php`

Le lien de desinscription pointe vers `/newsletter/unsubscribe/{token}`. La route marque `unsubscribed_at`, ce qui exclut ensuite l'adresse de toute audience future.

## Throttling

Le dispatch reel est limite par une fenetre glissante :

- `MAILING_WINDOW_LIMIT`
  Nombre maximal d'e-mails dispatches dans la fenetre.
- `MAILING_WINDOW_MINUTES`
  Duree de la fenetre glissante en minutes.
- `MAILING_DISPATCH_BATCH_SIZE`
  Taille maximale d'une vague poussee a Messenger a chaque execution de la commande.

Exemple de configuration prudente pour tests :

```dotenv
MAILING_WINDOW_LIMIT=1
MAILING_WINDOW_MINUTES=60
MAILING_DISPATCH_BATCH_SIZE=1
```

Exemple pour tester plus vite :

```dotenv
MAILING_WINDOW_LIMIT=1
MAILING_WINDOW_MINUTES=1
MAILING_DISPATCH_BATCH_SIZE=1
```

Important :

- le cron peut tourner chaque minute ;
- la commande dispatchera 0 ou 1 destinataire selon la capacite restante ;
- `MAILING_DISPATCH_BATCH_SIZE` ne doit pas depasser `MAILING_WINDOW_LIMIT` si on veut un debit parfaitement maitrise.

## Type d'etablissement `test`

Le type `test` existe pour isoler facilement des destinataires techniques.

Usage recommande :

- cibler uniquement `Types d'etablissement = Test mailing` ;
- eventuellement ajouter en plus une contrainte geographique ou un tag ;
- ne jamais melanger ce type avec des destinataires reels lors d'un test prod.

La migration `Version20260630213000` cree 4 organisations de test a Cornimont avec des e-mails invalides :

- `test-cornimont-01@jardinsonore-mailing-test.invalid`
- `test-cornimont-02@jardinsonore-mailing-test.invalid`
- `test-cornimont-03@jardinsonore-mailing-test.invalid`
- `test-cornimont-04@jardinsonore-mailing-test.invalid`

En local, Mailpit les acceptera et permettra de verifier le rendu.
En production, le transport SMTP doit remonter un echec de distribution, utile pour valider le comportement d'erreur sans toucher a de vraies boites.

## Variables d'environnement

Minimum pour la prod :

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

Notes :

- `MAILER_DSN` depend du fournisseur SMTP reel ;
- `DEFAULT_URI` doit etre publique et correcte, sinon les liens absolus seront faux ;
- `MAILING_HOME_LATITUDE` et `MAILING_HOME_LONGITUDE` sont necessaires pour le ciblage par rayon depuis le point favori ;
- `MESSENGER_TRANSPORT_DSN` doit viser le meme stockage que le worker et l'application.

## Cron et worker

Le mailing reel suppose deux briques d'exploitation :

1. Un worker Messenger actif en continu.
2. Un cron qui lance periodiquement la commande de dispatch.

Commande de cron :

```bash
php bin/console app:mailing:dispatch-pending-campaigns
```

Frequence recommandee :

- toutes les minutes.

Avec un throttling `1 mail / 60 minutes`, le cron peut tourner chaque minute sans risque. La commande verifiera simplement si la fenetre glissante permet de publier une nouvelle vague.

## Logs et supervision

Le channel Monolog dedie est `mailing_delivery`.

Fichier principal :

- `var/log/*/mailing_delivery.log`

A surveiller :

- mise en file de campagne ;
- vagues dispatches ;
- recipients envoyes ;
- recipients en echec ;
- campagne terminee avec succes ou echecs.

## Checklist prod

Avant d'envoyer une campagne reelle :

1. Verifier que les migrations sont a jour.
2. Verifier `DEFAULT_URI`.
3. Verifier `MAILER_DSN`.
4. Verifier que le worker Messenger tourne.
5. Verifier que le cron `app:mailing:dispatch-pending-campaigns` existe.
6. Verifier `MAILING_WINDOW_LIMIT`, `MAILING_WINDOW_MINUTES` et `MAILING_DISPATCH_BATCH_SIZE`.
7. Envoyer un test manuel depuis le backoffice.
8. Lancer une campagne ciblee sur le type `test` avant toute audience reelle.

## Fichiers clefs

- `src/Application/Controller/MailingController.php`
- `src/Application/Controller/NewsletterController.php`
- `src/Application/Command/DispatchPendingMailingCampaignsCommand.php`
- `src/Application/Mailing/SendMailingCampaign.php`
- `src/Application/Mailing/SendMailingCampaignTest.php`
- `src/Application/Mailing/MessageHandler/SendMailingCampaignRecipientMessageHandler.php`
- `src/Application/Mailing/MessageHandler/SendMailingCampaignTestMessageHandler.php`
- `src/Infrastructure/Mailing/DoctrineNewsletterAudienceResolver.php`
- `src/Infrastructure/Mailing/MailingDeliveryRecipientStore.php`
- `src/Infrastructure/Mailing/TwigNewsletterRenderer.php`
- `src/Infrastructure/Mailer/SymfonyNewsletterMailSender.php`
