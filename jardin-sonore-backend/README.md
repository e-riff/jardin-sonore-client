# Jardin Sonore Backend

Backend Symfony du projet Jardin Sonore. Il porte aujourd'hui le backoffice interne, l'annuaire, les imports de referentiel et le module de mailing newsletter.

## Vue macro

Le backend remplit quatre roles principaux :

- administrer les donnees de l'annuaire via une interface interne ;
- importer et rapprocher des etablissements depuis des exports externes ;
- preparer, cibler et envoyer des campagnes de mailing ;
- exposer les routes techniques du backoffice et les routes publiques associees, comme la desinscription newsletter.

La logique metier est volontairement separee de la persistence :

- `src/Domain/` porte les modeles metier purs ;
- `src/Application/` porte les cas d'usage, commandes et controllers Symfony ;
- `src/Infrastructure/` porte Doctrine, Mailer, Twig, stockage de fichiers et integration framework.

## Vue technique

Structure principale :

- `src/Application/Command/` : commandes CLI, import annuaire, dispatch mailing, sync geographie.
- `src/Application/Controller/` : controllers metier du backoffice et routes publiques.
- `src/Application/Mailing/` : cas d'usage du module newsletter.
- `src/Application/Directory/` : import et rapprochement d'etablissements.
- `src/Domain/Model/` : modeles metier purs.
- `src/Infrastructure/Doctrine/` : entites, mappings, mappers et repositories Doctrine.
- `src/Infrastructure/Mailing/` : resolution d'audience, rendu Twig, file de livraison.
- `templates/` : ecrans Twig du backoffice et templates d'e-mail.
- `docs/` : documentation backend maintenue avec le code.

## Documentation

- [Vue d'ensemble de la documentation](docs/README.md)
- [Import annuaire](docs/directory-import.md)
- [Mailing](docs/mailing.md)

## Commandes utiles

Depuis la racine du repo :

```bash
make backend-cs-check
make backend-stan
make backend-lint
make deploy-backend
```

Depuis `jardin-sonore-backend/` :

```bash
php bin/console
composer run cs-check
composer run stan
```

## Notifications de première publication et cron cPanel

La publication enregistre ses destinataires dans `session_notification_delivery` dans la même transaction que la séance. La commande suivante met au maximum 100 livraisons en file sur le transport `async` existant :

```bash
php bin/console app:sessions:dispatch-notifications --env=prod --no-debug --recover-after=30
```

**État local :** le sender et les templates HTML/texte sont branchés ; les exemples fictifs ont été envoyés au Mailpit local pour revue et corrigés après retour utilisateur. Le lien de notification conserve désormais la destination de la fiche à travers la connexion au portail. La recette locale est passée : programmation et reprises, préférences/adresse/droits relus avant envoi, rendu des mails et retour à la fiche après connexion. Activation en production et contrôle du rendu réel dans Gmail/Outlook restent à faire. La simulation est disponible :

```bash
php bin/console app:sessions:dispatch-notifications --dry-run --recover-after=30
```

Les deux crons cPanel communiqués par l'utilisateur s'exécutent chaque minute :

- `messenger:consume async --env=prod --time-limit=240 --memory-limit=256M`, protégé par `/tmp/jardin-sonore-messenger.lock`, traite les messages. Le verrou empêche le chevauchement des lancements pendant les quatre minutes de consommation.
- `app:mailing:dispatch-pending-campaigns --env=prod --no-debug`, protégé par `/tmp/jardin-sonore-mailing-dispatch.lock`, programme les campagnes.

Pour les notifications, ajouter un troisième cron de distribution, distinct, avec le même répertoire et binaire PHP :

```cron
* * * * * flock -n /tmp/jardin-sonore-session-notifications-dispatch.lock sh -lc 'cd /home/riem3079/repositories/jardin-sonore-backend && /opt/alt/php85/usr/bin/php bin/console app:sessions:dispatch-notifications --env=prod --no-debug --recover-after=30' >> /home/riem3079/logs/jardin-sonore-session-notifications-dispatch.log 2>&1
```

C'est une proposition d'exploitation, **aucun cron de production n'a été installé ou modifié**. Les trois crons utilisent un seul worker `async` : les deux distributeurs mettent les messages en file, le worker les traite avec les PDF et les autres messages existants. Aucun Supervisor n'est nécessaire. Une file ou un worker distinct pourra être envisagé si le volume des campagnes retarde les notifications ; cette charge n'a pas été mesurée.

`--recover-after` vaut 15 minutes par défaut. Choisir une valeur supérieure à l'intervalle entre deux démarrages du worker et à son éventuel retard de traitement. Exemple : pour un worker lancé toutes les 5 minutes, 30 minutes laissent une marge. Les livraisons `pending` sont immédiatement distribuables ; les `queued` anciennes sont redistribuées pour récupérer un arrêt ou un message perdu. Si le lot comporte plus de 100 destinataires, les passages suivants continuent la distribution.

Un verrou de ligne protège chaque distribution et chaque traitement, y compris pendant l'appel SMTP. Les messages dupliqués pour une livraison `sent` ou `skipped` ne renvoient pas le mail. Juste avant l'envoi, le traitement relit l'adresse du compte, son statut, sa préférence et ses accès actifs aux structures partagées ; le mail ne contient que les noms de structures auxquelles ce compte a accès. Une séance dépubliée ou un compte devenu inéligible conduit à `skipped`.

En cas d'échec, la livraison passe en `failed` avec son compteur de tentatives et sa dernière erreur. L'exception est propagée : Messenger utilise sa stratégie de reprise existante, puis le transport `failed` si les reprises sont épuisées. Le cron de distribution ne recycle pas les échecs SMTP : consulter `messenger:failed:show`, corriger la cause puis utiliser `messenger:failed:retry` pour les messages concernés. Les notifications ne modifient pas le fonctionnement des newsletters ou de la génération PDF qui utilisent aussi `async`.

SMTP ne permet pas de garantir une livraison exactement une fois : si le serveur accepte le mail et que le processus s'arrête avant l'enregistrement de `sent`, une reprise peut exceptionnellement le renvoyer. Les verrous et états terminaux évitent les doublons dus aux exécutions concurrentes ordinaires.

## Verifications

Pour un changement backend significatif :

- lancer au minimum `make backend-cs-check` ou un check cible equivalent ;
- lancer `make backend-stan` si l'environnement de cache/container le permet ;
- lancer la commande metier touchee en dry-run quand c'est pertinent, par exemple pour l'import annuaire.
