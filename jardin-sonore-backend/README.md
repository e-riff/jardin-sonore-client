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

## Notifications de disponibilité et cron cPanel

La première disponibilité d’une séance publiée pour une structure enregistre ses destinataires dans `session_notification_delivery` dans la même transaction que la séance. La commande suivante met au maximum 100 livraisons en file sur le transport `async` existant :

```bash
php bin/console app:sessions:dispatch-notifications --env=prod --no-debug --recover-after=30
```

**État local :** le sender et les templates HTML/texte sont branchés ; les exemples fictifs ont été envoyés au Mailpit local pour revue et corrigés après retour utilisateur. Le lien de notification conserve désormais la destination de la fiche à travers la connexion au portail. La recette locale est passée : programmation et reprises, préférences/adresse/droits relus avant envoi, rendu des mails et retour à la fiche après connexion. Le code et les deux migrations notifications sont déployés sous `deploy-notifications-20260930-01` (`e3ff7ca`). Les trois crons cPanel ont été installés par l’utilisateur et leur présence vérifiée. Le contrôle du rendu réel dans Gmail/Outlook reste à faire. L’évolution livrée sous `deploy-availability-newsletter-20260930-01` (`afbcece`) fonctionne ainsi : rattacher une séance publiée à une nouvelle structure ou publier un brouillon rattaché programme les nouveaux comptes éligibles. La table `session_organization_availability` conserve les premières disponibilités, même après retrait du rattachement. Une séance/un compte reçoit au plus une livraison ; republications et rattachements rétablis ne déclenchent pas de renvoi. La migration `Version20260930140509`, appliquée en développement/test/production, initialise l’existant sans programmer de mail. La simulation est disponible :

```bash
php bin/console app:sessions:dispatch-notifications --dry-run --recover-after=30
```

Les deux crons cPanel communiqués par l'utilisateur s'exécutent chaque minute :

- `messenger:consume async --env=prod --time-limit=240 --memory-limit=256M`, protégé par `/tmp/jardin-sonore-messenger.lock`, traite les messages. Le verrou empêche le chevauchement des lancements pendant les quatre minutes de consommation.
- `app:mailing:dispatch-pending-campaigns --env=prod --no-debug`, protégé par `/tmp/jardin-sonore-mailing-dispatch.lock`, programme les campagnes.

Le troisième cron de distribution installé pour les notifications utilise avec le même répertoire et binaire PHP :

```cron
* * * * * flock -n /tmp/jardin-sonore-session-notifications-dispatch.lock sh -lc 'cd /home/riem3079/repositories/jardin-sonore-backend && /opt/alt/php85/usr/bin/php bin/console app:sessions:dispatch-notifications --env=prod --no-debug --recover-after=30' >> /home/riem3079/logs/jardin-sonore-session-notifications-dispatch.log 2>&1
```

Les crons ont été ajoutés par l’utilisateur ; Codex a vérifié leur présence sans modifier la crontab. Les trois crons utilisent un seul worker `async` : les deux distributeurs mettent les messages en file, le worker les traite avec les PDF et les autres messages existants. Aucun Supervisor n'est nécessaire. Une file ou un worker distinct pourra être envisagé si le volume des campagnes retarde les notifications ; cette charge n'a pas été mesurée.

`--recover-after` vaut 15 minutes par défaut. Choisir une valeur supérieure à l'intervalle entre deux démarrages du worker et à son éventuel retard de traitement. Exemple : pour un worker lancé toutes les 5 minutes, 30 minutes laissent une marge. Les livraisons `pending` sont immédiatement distribuables ; les `queued` anciennes sont redistribuées pour récupérer un arrêt ou un message perdu. Si le lot comporte plus de 100 destinataires, les passages suivants continuent la distribution.

Un verrou de ligne protège chaque distribution et chaque traitement, y compris pendant l'appel SMTP. Les messages dupliqués pour une livraison `sent` ou `skipped` ne renvoient pas le mail. Juste avant l'envoi, le traitement relit l'adresse du compte, son statut, sa préférence et ses accès actifs aux structures partagées ayant déclenché la livraison ; le mail ne contient que les noms de structures auxquelles ce compte a accès. Une séance dépubliée ou un compte devenu inéligible conduit à `skipped`.

En cas d'échec, la livraison passe en `failed` avec son compteur de tentatives et sa dernière erreur. L'exception est propagée : Messenger utilise sa stratégie de reprise existante, puis le transport `failed` si les reprises sont épuisées. Le cron de distribution ne recycle pas les échecs SMTP : consulter `messenger:failed:show`, corriger la cause puis utiliser `messenger:failed:retry` pour les messages concernés. Les notifications ne modifient pas le fonctionnement des newsletters ou de la génération PDF qui utilisent aussi `async`.

SMTP ne permet pas de garantir une livraison exactement une fois : si le serveur accepte le mail et que le processus s'arrête avant l'enregistrement de `sent`, une reprise peut exceptionnellement le renvoyer. Les verrous et états terminaux évitent les doublons dus aux exécutions concurrentes ordinaires.

## Distribution locale et préférence newsletter

Le service Compose `session-notification-dispatcher` lance `app:sessions:dispatch-notifications --recover-after=30`, `app:commercial:dispatch-contact-requests`, `app:commercial:create-invoice-reminders` et `app:commercial:dispatch-digest` toutes les 60 secondes. Les notifications de séances passent par le worker Messenger existant ; les demandes de contact et le récapitulatif personnel sont envoyés directement vers Mailpit en local. Suivre les passages avec `docker compose logs --tail=30 session-notification-dispatcher`. La boucle continue au passage suivant si une distribution échoue. Aucun port HTTP supplémentaire n’est ouvert.

## Demandes de contact du site

Après validation du formulaire et d’ALTCHA côté Next.js, `POST /api/commercial/contact-requests` reçoit la demande via le secret BFF serveur existant. La transaction crée la demande à qualifier et une livraison `commercial_contact_delivery` avant de répondre. Une reprise avec la même clé et le même contenu retrouve la demande ; une clé réutilisée avec un autre contenu est refusée. Le courriel de contact conserve l’adresse du visiteur en `Reply-To` et utilise `DEFAULT_CONTACT` comme destinataire côté Symfony : vérifier que cette valeur correspond au destinataire actuel du formulaire avant déploiement.

La commande `app:commercial:dispatch-contact-requests` envoie au plus 100 courriels en attente par passage, avec `--dry-run` pour compter sans envoyer. Un échec SMTP reste enregistré et sera retenté au passage suivant. Les envois déjà marqués `sent` ne sont pas répétés. Comme pour les notifications de séances, un arrêt entre l’acceptation du mail par SMTP et l’écriture de l’état `sent` peut exceptionnellement produire un doublon.

Le service Compose local lance cette commande toutes les minutes. En production, le cron cPanel est installé sous verrou `flock` et journalise ses passages dans `/home/riem3079/logs/jardin-sonore-commercial-contact-requests.log`.

## Suivi commercial local

Le backoffice métier propose `/commercial` pour les demandes, dossiers, actions, devis et factures suivis manuellement. Les PDF et les courriels restent dans Drive et Gmail. Les raccourcis Drive se configurent dans `config/parameters.yaml.dist`. Les migrations `Version20261009074032` à `Version20261009084558` sont déployées en production ; 60 migrations sont exécutées, aucune n’est en attente et le schéma est synchronisé.

`app:commercial:create-invoice-reminders` crée une action de relance unique à 30 jours de la date d’émission d’une facture impayée. `app:commercial:dispatch-digest` envoie le courriel personnel les jours ouvrés à l’heure réglée dans le backoffice, seulement si des éléments sont à suivre ; une livraison enregistrée comme envoyée n’est pas répétée le même jour. Les deux crons de production sont installés sous verrou `flock` ; leurs journaux sont `/home/riem3079/logs/jardin-sonore-commercial-invoice-reminders.log` et `/home/riem3079/logs/jardin-sonore-commercial-digest.log`. Les trois crons commerciaux sont lancés chaque minute. Le récapitulatif respecte son horaire configuré et ne s’envoie pas le week-end.

Le profil expose `newsletterSubscribed`, calculé depuis le consentement du contact portant l’adresse actuelle du compte. Une lecture ne crée pas de contact. Un choix explicite crée ou réutilise le contact ; un retrait conserve le jeton et l’historique. Un contact inactif ne peut pas être réactivé depuis le profil. L’appartenance au groupe libre reste distincte, réservée aux inscriptions libres confirmées. Les contacts e-mail survivent désormais au retrait de leurs liens d’annuaire pour conserver ces informations. Le lien `/newsletter/unsubscribe/{token}` est accessible publiquement et ne modifie pas les notifications de séances.

La case du profil et son consentement sont déployés ; l’ajout des comptes et abonnés libres aux audiences reste à réaliser (parties 3–5 du plan newsletter).

Le contrôle rapide du compteur en production n’a pas reproduit « 1 mail envoyé » : les derniers logs du distributeur indiquaient 0 destinataire mis en file et la base comptait 574 livraisons envoyées, réparties en 205, 174 et 195. Aucun compteur n’a été modifié faute d’anomalie établie.

## Verifications

Pour un changement backend significatif :

- lancer au minimum `make backend-cs-check` ou un check cible equivalent ;
- lancer `make backend-stan` si l'environnement de cache/container le permet ;
- lancer la commande metier touchee en dry-run quand c'est pertinent, par exemple pour l'import annuaire.

## Livraison de clôture du 30 septembre 2026

Tag `deploy-availability-newsletter-20260930-01`, commit `afbcece`, branche et tag poussés avant déploiement. Backend et front livrés depuis une archive du tag. Le script `make deploy-backend` a appliqué `Version20260930130613` et `Version20260930140509`, vidé le cache et envoyé le signal d’arrêt aux workers ; les crons relancent leur consommation habituelle. Contrôles : 52 migrations exécutées, aucune en attente, schéma synchronisé, files async/failed vides, trois crons présents, aucune structure de séance publiée absente de l’historique initialisé. Aucun changement manuel cPanel nécessaire. Le rendu Gmail/Outlook reste à contrôler et les audiences newsletter sont la prochaine partie à développer.

Contrôles HTTP après livraison : vitrine et connexion 200, destination après connexion conservée, désabonnement public avec jeton fictif inexistant 200, profil sans authentification 401. Aucun destinataire réel modifié ni mail externe de test envoyé.

## Livraison du suivi commercial — 9 octobre 2026

Le backend et le client ont été déployés après commit et push de la branche `feat/suivi-commercial-v1` et du tag `deploy-commercial-follow-up-20261009-01` (`3e27c4f`). Les migrations commerciales ont été appliquées en production, le cache vidé et les workers signalés pour redémarrage. Contrôles : 60 migrations exécutées, aucune en attente, schéma Doctrine synchronisé, adresse `DEFAULT_CONTACT` vérifiée et trois crons cPanel présents. L'accueil et `/eveil-musical-creche` répondent 200. Une requête de contrôle à `/api/contact` sans preuve ALTCHA valide répond 403 ; aucun courriel de test réel n’a été envoyé.

Le client a ensuite reçu le correctif de compatibilité pour les formulaires ouverts avant l’ajout de `submissionKey`, sous le tag `deploy-client-security-20261009-01` (`d3da04b`). Next.js est en 16.4.0, `sharp` en 0.35.5 et `source-map-js` en 1.2.2 ; le build et le lint passent et l’audit des dépendances de production ne rapporte aucune vulnérabilité. L’audit complet npm conserve des avis de développement dans `braces` ; leur correctif proposé impose un changement majeur d’`eslint-config-next` et n’a pas été forcé.
