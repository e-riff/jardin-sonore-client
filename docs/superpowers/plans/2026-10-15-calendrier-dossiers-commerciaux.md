# Calendrier des dossiers commerciaux — plan d'implémentation

> **Pour l'exécution :** suivre les étapes dans l'ordre, avec une vérification ciblée à chaque lot. Aucune connexion Google n'est encore configurée. Le parcours de rattachement et la synchronisation ont été validés le 16 octobre après examen du calendrier réel.

**Objectif :** voir et modifier les interventions depuis un dossier, recevoir un aperçu bref de cette semaine et de la suivante chaque matin ouvré, et obtenir une action de facturation après la dernière intervention du mois.

**Architecture :** Google Calendar « Professionnel Musique » fait référence pour la date et l'heure. Le backend Symfony conserve le rattachement explicite au dossier, l'état métier (option, confirmée, annulée), la dernière version Google connue et les rappels. Seuls les événements rattachés sont suivis ; une recherche par période permet de rattacher des événements Google existants. Le rafraîchissement des événements liés a lieu périodiquement et à l'ouverture du dossier.

**Pile :** PHP 8.4, Symfony 8.1, Doctrine ORM 3, Twig/Symfony UX, Google Calendar API v3, OAuth 2.0 et les crons cPanel déjà présents.

**Références métier :** [ROADMAP.md](../../../ROADMAP.md) et [notes agenda](../../suivi-commercial-cadrage-en-cours.md#agenda-et-interventions). Le suivi des devis reste manuel pour ce lot.

## Repérage initial du code — 15 octobre 2026

- Aucune intégration Google Calendar/OAuth ni dépendance Google API n'a été trouvée dans le backend ; seules les URL de dossiers Drive sont configurées. L'adaptateur Calendar et son autorisation seront donc nouveaux.
- `CommercialEventEntity` conserve le fil d'événements horodatés du dossier et n'est pas le modèle des séances à venir ; garder une entité d'intervention distincte évite de mélanger ces deux usages.
- Le digest commercial et son cron existent déjà. Le lot mail pourra étendre `BuildCommercialDigest` et ses templates en conservant le mécanisme d'envoi et son horaire ouvré.

## Contraintes communes

- Une séance Google n'entre dans le suivi qu'après rattachement explicite à un dossier. Les événements professionnels non liés restent utilisables pour les conflits, sans devenir facturables.
- Confirmation et annulation sont des états métier ; retirer `???` d'un titre Google ne confirme pas une séance, et confirmer dans le dossier ne change pas le titre Google. Une suppression Google confirmée annule la séance liée et laisse une trace dans le dossier. Une erreur réseau n'est jamais une annulation.
- Une modification de date depuis le dossier doit réussir côté Google avant d'être présentée comme enregistrée. Une version Google concurrente bloque l'écriture et invite à relire les nouvelles données.
- Afficher les interventions et les tâches près du haut du dossier ; rendre le fil repliable quand des interventions sont liées. Le mail contient une ligne courte par intervention à venir, groupée par semaine, avec l'état « option » visible.
- La facturation ne se déclenche qu'après la dernière séance **confirmée et passée** du mois pour le dossier. Une seule action de préparation de facture par dossier et par mois, annulable par l'utilisateur ; ne pas en recréer une après annulation volontaire.
- Garder les libellés dans `translations/commercial.fr.yaml` et `translations/commercial_digest.fr.yaml`. Ne pas introduire de vue agenda globale.

## Points à vérifier avant le code

1. L'identifiant et le compte propriétaire de « Professionnel Musique » ont été vérifiés en lecture. Confirmer l'URL de retour OAuth sur le cPanel et la présence de l'extension Sodium en production. Aucun secret ne va dans Git.
2. L'examen d'événements réels a confirmé des séries dont des occurrences sont déplacées, des options `???` malgré le statut Google `confirmed`, des lieux parfois absents et des journées entières. Garder l'identité de chaque occurrence après déplacement ; exiger un horaire avant son rattachement comme séance.
3. Vérifier le statut de publication OAuth : Google indique qu'un jeton de renouvellement pour une application externe en mode « Testing » peut expirer après sept jours ([OAuth 2.0](https://developers.google.com/identity/protocols/oauth2/)). Choisir le périmètre Calendar minimal nécessaire ([scopes](https://developers.google.com/workspace/calendar/api/auth)).

## Lot 1 — Connexion Google et modèle de séances

**Fichiers principaux :** `jardin-sonore-backend/config/services.php`, `.env`/configuration de référence, nouveaux contrôleur et service OAuth dans `src/Application/Controller/` et `src/Infrastructure/Google/`, nouvelles entités et mappings Doctrine dans `src/Infrastructure/Doctrine/`, migration générée et relue.

- [x] Ajouter une connexion OAuth administrateur avec accès hors ligne, déconnexion et état visible de la connexion ; conserver le jeton de renouvellement chiffré avec une clé locale non versionnée. Définir `CommercialCalendarGateway` dans `src/Application/Commercial/` et son adaptateur dans `src/Infrastructure/Google/`.
- [x] Créer `CommercialInterventionEntity` : dossier, identifiants calendrier/événement uniques, début/fin et fuseau connus, lieu, état option/confirmée/annulée, ETag, dernière synchronisation et date d'annulation. Stocker l'identifiant d'une occurrence si l'événement est récurrent.
- [x] Créer et relire les migrations des deux tables ; elles sont appliquées aux bases locales de développement et de test, jamais en production.
- [ ] Vérifier l'autorisation, le renouvellement du jeton, l'unicité du lien Google et le refus des accès non administrateur. Une connexion en échec doit rester clairement signalée.

## Lot 2 — Rattachement et synchronisation fiable

**Fichiers principaux :** service de synchronisation dans `src/Application/Commercial/`, adaptateur Calendar dans `src/Infrastructure/Google/`, commande Symfony dans `src/Application/Command/`, contrôleur de dossier et entité d'événement commercial.

- [x] Présenter sur un écran les événements non liés de « Professionnel Musique » sur une période choisie. Regrouper visuellement les occurrences d'une même série et suggérer les titres identiques, même après déplacement ; laisser choisir explicitement les séances du groupe et le dossier. Ne jamais déduire le dossier du titre. Permettre de détacher ou réaffecter chaque séance sans toucher à l'événement Google. Une séance créée depuis un dossier y est liée automatiquement.
- [x] Implémenter le rafraîchissement périodique des interventions liées à venir et celles du mois récent, ainsi que la relecture de toutes les dates liées à l'ouverture du dossier. Une disparition confirmée chez Google passe la séance à « annulée » avec trace ; les erreurs conservent l'ancien état et signalent une synchronisation à vérifier. La commande et le point d'exécution cPanel restent à configurer.
- [x] Permettre de modifier début/fin et lieu depuis le dossier avec l'ETag Google ; refuser proprement un conflit et afficher la nouvelle version. La confirmation explicite depuis le dossier met à jour seulement l'état métier, sans modifier le titre Google.
- [ ] Vérifier notamment déplacement depuis Google, double rattachement, événement récurrent, passage heure d'été/hiver, suppression Google, panne réseau et conflit d'édition. La référence Google expose l'ETag et les propriétés privées des événements ([ressource Event](https://developers.google.com/workspace/calendar/api/v3/reference/events), [mise à jour](https://developers.google.com/workspace/calendar/api/v3/reference/events/update)).

## Lot 3 — Fiche dossier centrée sur les dates

**Fichiers principaux :** `templates/commercial/project/show.html.twig`, nouveau partiel `templates/commercial/project/_interventions.html.twig`, `templates/commercial/project/_timeline.html.twig`, `assets/styles/app.css`, traductions commerciales.

- [x] Garder « À faire » visible, puis placer les documents et le volet séances sous les contacts. Le volet séances s'ouvre pendant une discussion et se ferme lorsque le dossier est confirmé ; les commandes restent dans le bloc et les interventions passées restent consultables sur la fiche.
- [x] Replier le fil par défaut et permettre de n'ouvrir qu'un seul volet entre séances et fil. Placer « Ajouter au fil » dans son en-tête et garder le fil ouvert lorsqu'une note échoue à la validation. Montrer les erreurs de synchronisation près des dates.
- [ ] Contrôler la lisibilité d'un dossier sans séance, de plusieurs lieux et de dates alternatives pour une seule prestation.

## Lot 4 — Mail sur deux semaines et rappel de facturation

**Fichiers principaux :** `src/Application/Commercial/BuildCommercialDigest.php`, `CommercialDigest.php`, `DispatchCommercialDigest.php`, les deux `templates/commercial/digest_email.*.twig`, traductions du digest ; service de rappel mensuel et commande dédiée.

- [x] Dans le mail ouvré existant, prendre les prochaines interventions liées depuis le jour courant jusqu'au dimanche de la semaine suivante, en heure de Paris ; les grouper « cette semaine / semaine prochaine » et afficher date, heure, dossier et état. Les options et erreurs de synchronisation sont signalées ; le mail part aussi si les séances sont son seul contenu.
- [x] Après la dernière séance confirmée et passée du mois pour un dossier, créer ou mettre à jour une seule action « Préparer la facture » liée au mois. Recalculer si une séance est déplacée ou ajoutée ; préserver une annulation volontaire et terminer l'action lorsqu'une facture correspondante est enregistrée. Les factures portent désormais un mois de prestations distinct de leur date d'émission.
- [ ] Vérifier les semaines qui traversent deux mois, le changement d'heure, un mois avec plusieurs lieux, une option après la dernière séance confirmée, une facture déjà saisie et une exécution répétée du cron.

## Livraison

- [ ] Vérifier les contrôles backend adaptés, la compilation des assets et le diff Doctrine ; faire une recette authentifiée sur téléphone et ordinateur avec un calendrier de test.
- [ ] Recenser les noms des nouvelles variables Google (identifiants OAuth, calendrier, URL de retour, clé de chiffrement) dans la configuration de référence, sans leurs valeurs. Comparer ensuite cette liste à la configuration de production sur cPanel : le déploiement par rsync exclut .env.local et ne transmet donc pas ces valeurs.
- [ ] Installer les secrets nécessaires directement sur cPanel par un canal sécurisé, dans la configuration locale du backend avec des permissions restreintes, avant de lancer le déploiement du code qui les utilise. Le fichier .env.deploy.local ne transmet pas les secrets de l'application à cPanel. Ne les placer ni dans Git ni dans les journaux de déploiement ; vérifier leur présence et la connexion OAuth sans afficher leurs valeurs.
- [ ] Configurer les secrets OAuth et la clé de chiffrement sur cPanel, installer le cron `app:commercial:refresh-calendar` (il rafraîchit aussi les actions de facture), puis livrer par commit, tag et push selon `AGENTS.md`. Vérifier un aller-retour de modification et un mail de prévisualisation avant le premier envoi automatique.

**Sources techniques Google :** [OAuth web](https://developers.google.com/identity/protocols/oauth2/web-server), [événements Calendar](https://developers.google.com/workspace/calendar/api/v3/reference/events), [liste des événements](https://developers.google.com/workspace/calendar/api/v3/reference/events/list). Une synchronisation incrémentale complète de l'agenda n'est pas nécessaire au premier lot puisque seuls les événements liés sont suivis ; Google documente les jetons de synchronisation et leur expiration si ce choix change plus tard ([guide](https://developers.google.com/workspace/calendar/api/guides/sync)).
