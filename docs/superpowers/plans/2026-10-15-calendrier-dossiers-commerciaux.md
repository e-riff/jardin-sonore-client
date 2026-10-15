# Calendrier des dossiers commerciaux — plan d'implémentation

> **Pour l'exécution :** suivre les étapes dans l'ordre, avec une vérification ciblée à chaque lot. Ce plan est préparatoire ; aucune connexion Google n'est encore configurée.

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
- Confirmation et annulation sont des états métier ; retirer `???` d'un titre Google ne confirme pas une séance. Une suppression Google confirmée annule la séance liée et laisse une trace dans le dossier. Une erreur réseau n'est jamais une annulation.
- Une modification de date depuis le dossier doit réussir côté Google avant d'être présentée comme enregistrée. Une version Google concurrente bloque l'écriture et invite à relire les nouvelles données.
- Afficher les interventions et les tâches près du haut du dossier ; rendre le fil repliable quand des interventions sont liées. Le mail contient une ligne courte par intervention à venir, groupée par semaine, avec l'état « option » visible.
- La facturation ne se déclenche qu'après la dernière séance **confirmée et passée** du mois pour le dossier. Une seule action de préparation de facture par dossier et par mois, annulable par l'utilisateur ; ne pas en recréer une après annulation volontaire.
- Garder les libellés dans `translations/commercial.fr.yaml` et `translations/commercial_digest.fr.yaml`. Ne pas introduire de vue agenda globale.

## Points à vérifier avant le code

1. Confirmer l'identifiant réel de « Professionnel Musique », le compte Google propriétaire, l'URL de retour OAuth sur le cPanel et la présence de l'extension Sodium en production. Aucun secret ne va dans Git.
2. Examiner quelques événements réels anonymisés : séances simples ou récurrentes, horaires, fuseaux, lieux, titres d'options et dates alternatives. Cela fixe le traitement des occurrences et des événements sans heure.
3. Vérifier le statut de publication OAuth : Google indique qu'un jeton de renouvellement pour une application externe en mode « Testing » peut expirer après sept jours ([OAuth 2.0](https://developers.google.com/identity/protocols/oauth2/)). Choisir le périmètre Calendar minimal nécessaire ([scopes](https://developers.google.com/workspace/calendar/api/auth)).

## Lot 1 — Connexion Google et modèle de séances

**Fichiers principaux :** `jardin-sonore-backend/config/services.php`, `.env`/configuration de référence, nouveaux contrôleur et service OAuth dans `src/Application/Controller/` et `src/Infrastructure/Google/`, nouvelles entités et mappings Doctrine dans `src/Infrastructure/Doctrine/`, migration générée et relue.

- [ ] Ajouter une connexion OAuth administrateur avec accès hors ligne, déconnexion et état visible de la connexion ; conserver le jeton de renouvellement chiffré avec une clé locale non versionnée. Définir `CommercialCalendarGateway` dans `src/Application/Commercial/` et son adaptateur dans `src/Infrastructure/Google/`.
- [ ] Créer `CommercialInterventionEntity` : dossier, identifiants calendrier/événement uniques, début/fin et fuseau connus, lieu, état option/confirmée/annulée, ETag, dernière synchronisation et date d'annulation. Stocker l'identifiant d'une occurrence si l'événement est récurrent.
- [ ] Prévoir la migration Doctrine après vérification du diff de schéma ; la jouer en production seulement dans la livraison du lot.
- [ ] Vérifier l'autorisation, le renouvellement du jeton, l'unicité du lien Google et le refus des accès non administrateur. Une connexion en échec doit rester clairement signalée.

## Lot 2 — Rattachement et synchronisation fiable

**Fichiers principaux :** service de synchronisation dans `src/Application/Commercial/`, adaptateur Calendar dans `src/Infrastructure/Google/`, commande Symfony dans `src/Application/Command/`, contrôleur de dossier et entité d'événement commercial.

- [ ] Permettre de chercher les événements de « Professionnel Musique » sur une période choisie, puis d'en rattacher un ou plusieurs au dossier sans inférer le lien depuis leur titre. Permettre aussi de créer une séance depuis le dossier.
- [ ] Rafraîchir périodiquement les interventions liées à venir et celles du mois récent ; vérifier les dates plus anciennes à l'ouverture du dossier concerné. Une disparition confirmée chez Google passe la séance à « annulée » avec trace ; les erreurs d'autorisation, de quota ou de réseau laissent l'ancien état et indiquent que les données ne sont plus fraîches.
- [ ] Permettre de modifier début/fin et lieu depuis le dossier avec l'ETag Google ; refuser proprement un conflit et afficher la nouvelle version. La confirmation explicite depuis le dossier met à jour l'état métier et le titre Google selon une convention décidée à partir des événements réels.
- [ ] Vérifier notamment déplacement depuis Google, double rattachement, événement récurrent, passage heure d'été/hiver, suppression Google, panne réseau et conflit d'édition. La référence Google expose l'ETag et les propriétés privées des événements ([ressource Event](https://developers.google.com/workspace/calendar/api/v3/reference/events), [mise à jour](https://developers.google.com/workspace/calendar/api/v3/reference/events/update)).

## Lot 3 — Fiche dossier centrée sur les dates

**Fichiers principaux :** `templates/commercial/project/show.html.twig`, nouveau partiel `templates/commercial/project/_interventions.html.twig`, `templates/commercial/project/_timeline.html.twig`, `assets/styles/app.css`, traductions commerciales.

- [ ] Placer les interventions à venir, options et rappels après les contacts, avec les dates passées consultables sur la même fiche ; garder les commandes de modification et de rattachement utilisables sur téléphone.
- [ ] Replier le fil par défaut lorsqu'au moins une intervention est liée, en conservant l'ouverture manuelle. Montrer la dernière synchronisation et une erreur éventuelle près des dates.
- [ ] Contrôler la lisibilité d'un dossier sans séance, de plusieurs lieux et de dates alternatives pour une seule prestation.

## Lot 4 — Mail sur deux semaines et rappel de facturation

**Fichiers principaux :** `src/Application/Commercial/BuildCommercialDigest.php`, `CommercialDigest.php`, `DispatchCommercialDigest.php`, les deux `templates/commercial/digest_email.*.twig`, traductions du digest ; service de rappel mensuel et commande dédiée.

- [ ] Chaque matin ouvré, prendre les prochaines interventions liées depuis le jour courant jusqu'au dimanche de la semaine suivante, en heure de Paris ; les grouper « cette semaine / semaine prochaine » et afficher date, heure, dossier et état en une ligne. Une intervention en option est signalée. Une synchronisation incertaine est signalée, et le mail part aussi si les séances sont son seul contenu.
- [ ] Après la dernière séance confirmée et passée du mois pour un dossier, créer ou mettre à jour une seule action « Préparer la facture » liée au mois. Recalculer si une séance est déplacée ou ajoutée ; préserver une annulation volontaire et terminer l'action lorsqu'une facture correspondante est enregistrée.
- [ ] Vérifier les semaines qui traversent deux mois, le changement d'heure, un mois avec plusieurs lieux, une option après la dernière séance confirmée, une facture déjà saisie et une exécution répétée du cron.

## Livraison

- [ ] Vérifier les contrôles backend adaptés, la compilation des assets et le diff Doctrine ; faire une recette authentifiée sur téléphone et ordinateur avec un calendrier de test.
- [ ] Configurer l'autorisation Google et le cron cPanel, puis livrer par commit, tag et push selon `AGENTS.md`. Vérifier un aller-retour de modification et un mail de prévisualisation avant le premier envoi automatique.

**Sources techniques Google :** [OAuth web](https://developers.google.com/identity/protocols/oauth2/web-server), [événements Calendar](https://developers.google.com/workspace/calendar/api/v3/reference/events), [liste des événements](https://developers.google.com/workspace/calendar/api/v3/reference/events/list). Une synchronisation incrémentale complète de l'agenda n'est pas nécessaire au premier lot puisque seuls les événements liés sont suivis ; Google documente les jetons de synchronisation et leur expiration si ce choix change plus tard ([guide](https://developers.google.com/workspace/calendar/api/guides/sync)).
