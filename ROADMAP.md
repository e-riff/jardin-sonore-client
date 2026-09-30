# Roadmap Jardin Sonore

Mise à jour le 30 septembre 2026. Ce document est le **seul pilotage actif** des chantiers produit et techniques. Les autres plans et bilans cités en fin de page apportent du contexte ou conservent l'historique ; leurs listes de tâches ne définissent plus les priorités. L'ordre général est : **visibilité et nouvelles demandes**, puis **gain de temps dans le backoffice**. Les priorités indiquent un ordre de travail, pas un engagement de livraison simultanée.

## Livré — hors travaux futurs

- Backoffice métier pour l'annuaire, les médias, les prolongements, le mailing et les séances ; EasyAdmin reste disponible pour le dépannage technique.
- Masques d'audience réutilisables, ciblage géographique et extension d'une campagne déjà envoyée.
- Compositeur de séances, publication aux structures, lecture HTML et téléchargement du PDF canonique ; la génération et le téléchargement fonctionnent. L'esthétique du PDF reste à reprendre.
- Portail structures accessible avec comptes, séances, comptines, profil et impersonation ; le bandeau du portail et la stabilité du menu sont livrés.
- Règle de mot de passe du portail centralisée. Le traitement d'une réussite partielle lors de l'édition du profil reste distinct et ouvert.

## Point de reprise — 1er octobre 2026

**Reprendre directement la partie 3 du [plan newsletter](docs/superpowers/plans/2026-09-30-newsletter-subscriptions-and-audiences.md) : résolution des audiences.** Les parties 1 (stockage) et 2 (préférence du profil) sont terminées ; ne pas les recommencer.

- [x] Notifications à la première disponibilité par structure : rattachement d’une séance active ou activation d’une séance rattachée ; historique durable, un mail au plus par séance/compte, droits des structures déclencheuses relus avant SMTP.
- [x] Deux préférences indépendantes dans le profil : nouvelles séances et newsletter ; consentement porté par l’adresse, lien public de désabonnement et conservation de l’historique des contacts sans lien d’annuaire.
- [x] Les trois crons cPanel sont présents ; distribution locale ajoutée à Docker toutes les 60 secondes.
- [x] Recette locale : 238 tests backend / 1 371 assertions, style et PHPStan, lint/build front et recette navigateur. Les migrations `Version20260930130613` et `Version20260930140509` sont appliquées en développement/test ; schéma synchronisé.
- [x] Livraison de clôture du 30 septembre : commit `afbcece`, tag `deploy-availability-newsletter-20260930-01`, branche/tag poussés puis backend/front déployés depuis ce tag. Le script backend a appliqué les deux migrations, vidé le cache et envoyé le signal d’arrêt aux workers. Schéma production synchronisé, migrations à jour, files `async`/`failed` à 0, trois crons présents et historique des séances publiées complet, sans mail de rattrapage. Vitrine, connexion et désabonnement public HTTP 200 ; profil sans authentification HTTP 401 ; destination après connexion conservée.
- [ ] **Partie 3 :** réunir annuaire, comptes du portail selon leurs structures et abonnés libres confirmés ; option libre désactivée par défaut, ciblage existant conservé, déduplication par e-mail.
- [ ] **Partie 4 :** vérifier le consentement courant juste avant SMTP et terminer proprement les livraisons annulées.
- [ ] **Partie 5 :** ajouter l’option d’audience au backoffice et préserver son état dans les masques, duplications et extensions ; recette du lot complet.
- [ ] Ensuite seulement : inscription publique avec confirmation depuis le footer. Aucun formulaire public ou mécanisme de confirmation livré à ce stade.
- [ ] Contrôler le rendu réel Gmail/Outlook. Le « 1 mail envoyé » rapporté n’a pas été reproduit : distributeur à 0 lors du contrôle, 574 livraisons envoyées réparties en 205/174/195 ; aucun compteur modifié.
- [ ] Page légale et finitions PDF reportées.

Branche conservée : `feat/session-first-publication-notifications`. Arrêt à la frontière de la partie 2 newsletter. Les choix de consentement historiques restent inchangés ; l’abonnement depuis le profil ne donne pas automatiquement l’appartenance au groupe libre. Les nouveaux comptes ne sont pas encore ajoutés aux audiences par la partie 3.

La [conception des disponibilités](docs/superpowers/specs/2026-09-30-session-availability-notifications-design.md) remplace le déclenchement uniquement à la première publication globale. Le [plan des disponibilités](docs/superpowers/plans/2026-09-30-session-availability-notifications.md) et le [plan initial](docs/superpowers/plans/2026-09-30-session-first-publication-notifications.md) conservent la recette et l’historique ; le [cadrage newsletter](docs/superpowers/specs/2026-09-30-session-notifications-newsletter-design.md) sépare audiences et inscription publique.

## P0 — Fiabilité, visibilité et demandes entrantes

| Chantier | Bénéfice attendu | Dépendances / critère de départ |
| --- | --- | --- |
| Sécuriser les actions administrateur d'invitation et de réinitialisation : POST, CSRF et redirection interne ; vérifier la provenance de l'IP utilisée pour limiter les tentatives sur le portail. | Éviter les actions déclenchées par une requête non autorisée et rendre la limitation fiable. | Tests ciblés ; examiner la chaîne de proxies en production avant de modifier le traitement de l'IP. |
| Contrôler le rendu réel des notifications dans Gmail/Outlook. | Prévenir les comptes éligibles une seule fois par séance, avec un mail soigné. | Crons en place ; clôture de livraison suivie ci-dessus, recette réelle sur une nouvelle disponibilité choisie par l’utilisateur. |
| Abonnement newsletter des comptes du portail et des personnes sans structure, puis inscription publique depuis le footer. | Permettre un consentement distinct et inclure les abonnés libres dans les campagnes choisies. | Reprendre partie 3 du plan approuvé ; puis contrôle avant SMTP, backoffice et inscription publique. |
| Préparer la page légale et l'information sur les données personnelles, accessibles depuis le pied de page — reporté à la demande de l'utilisateur. | Donner des informations fiables aux visiteurs et aux structures. | Reprendre après le chantier notifications/newsletter ; valider les mentions propres à l'activité et les traitements réels avant publication. |
| Analyser Search Console et la fiche Google Business Profile, puis corriger les écarts constatés. | Comprendre les recherches et améliorer la découverte locale. | Accès aux deux outils ; relever requêtes, pages, indexation et cohérence des coordonnées/zone. |
| Créer une page d'offre « ateliers crèches et EAJE » et expliquer concrètement le portail dans « En séance ». | Répondre aux questions des prospects et montrer les ressources offertes après intervention. | Décrire la zone habituelle, les déplacements possibles pour les séances spéciales et les modalités pratiques ; utiliser uniquement des exemples ou visuels autorisés du portail. |

## P1 — Suivi commercial dans le backoffice

| Chantier | Bénéfice attendu | Dépendances / critère de départ |
| --- | --- | --- |
| Enregistrer les demandes issues du site et permettre la saisie des demandes reçues par d'autres canaux. | Ne perdre aucune demande et retrouver son origine. | Définir les données utiles et relier le formulaire de contact sans déplacer les échanges hors de Gmail. |
| Suivre un dossier par projet, rattaché à la structure concernée. | Voir l'historique et la prochaine étape d'une relation commerciale. | S'appuyer sur les structures existantes ; préciser les cas de plusieurs projets pour une même structure. |
| Gérer une file d'actions terminables, reportables ou suspendables, avec échéances suggérées puis modifiables. | Prioriser les relances sans imposer un calendrier rigide. | Définir les événements qui créent une action, son responsable et ses états. |
| Envoyer un résumé des actions à Gmail les jours ouvrés et prévoir une pause globale datée. | Garder les échéances visibles pendant l'activité et éviter les rappels durant une absence. | Fixer l'heure, le fuseau horaire, les jours ouvrés et le comportement de reprise après la pause. |

Gmail reste le lieu des échanges avec les clients ; le backoffice porte les dossiers, les échéances et les documents.

## P2 — Devis, interventions et facturation suivie

| Chantier | Bénéfice attendu | Dépendances / critère de départ |
| --- | --- | --- |
| Concevoir les devis dans le backoffice à partir du XLS existant ; suivre leur envoi et importer le PDF signé. | Préparer les propositions au même endroit que les dossiers et conserver la preuve d'acceptation. | **Examiner le XLS avant de détailler le générateur**, ses calculs, modèles et variantes ; rattacher chaque devis à un projet. |
| Gérer les visites dans le backoffice et synchroniser leurs dates vers Google Calendar. | Disposer d'un calendrier cohérent pour les interventions prévues. | Définir la source de vérité, la gestion des modifications/annulations et l'autorisation Google. |
| Faire apparaître une visite passée dans « à facturer » sous forme d'action annulable ; rappeler la facture mensuelle après la dernière visite prévue de l'établissement. | Préparer une facture regroupée sans oublier une intervention ni facturer automatiquement. | Suivre les visites réalisées et le regroupement par établissement et par mois ; tenir compte des visites ajoutées ou déplacées. |
| Enregistrer la référence et l'état de la facture émise par la plateforme déjà choisie. | Retrouver le suivi de facturation depuis le dossier. | Définir les statuts utiles et leur mise à jour depuis la plateforme, manuelle d'abord si nécessaire. |

### Facturation électronique — échéance distincte

Les **devis** peuvent être conçus et suivis dans le backoffice. L'**émission et la transmission des factures électroniques** relèvent de la plateforme agréée choisie ; le backoffice n'a pas vocation à la remplacer. Selon le [calendrier officiel du ministère de l'Économie](https://www.economie.gouv.fr/tout-savoir-sur-la-facturation-electronique-pour-les-entreprises), la réception est obligatoire pour toutes les entreprises depuis le 1er septembre 2026, et l'émission devient obligatoire pour les PME et micro-entreprises le 1er septembre 2027. Vérifier les obligations applicables à l'activité avant de spécifier une intégration.

## P2 — Espace structures

| Chantier | Bénéfice attendu | Dépendances / critère de départ |
| --- | --- | --- |
| Créer un accueil de portail avec accès aux rubriques et annonces datées. | Orienter les structures et leur transmettre une information utile à durée limitée. | Définir destinataires, dates de visibilité, droits d'édition et emplacement de gestion dans le backoffice. |
| Distinguer les responsables pour donner accès aux devis signés et aux documents exceptionnels. | Partager les documents sensibles avec les bonnes personnes. | Définir les rôles et vérifier les autorisations par structure et par document. |
| Améliorer la présentation des ressources du portail. | Faciliter la consultation des séances, comptines et documents. | Observer les usages et préserver les accès existants. |
| Présenter le portail sur une page publique dédiée. | Expliquer son intérêt aux prospects. | Après la présentation courte dans « En séance » ; exemples réels autorisés ou données fictives. |

## P3 — À explorer ou à améliorer progressivement

Ces idées restent visibles sans être décidées pour le prochain lot.

| Piste | Bénéfice attendu | Dépendances / critère de départ |
| --- | --- | --- |
| Page collaborations et projets, avec témoignages. | Rassurer les prospects par des exemples concrets. | Obtenir les accords pour noms, citations, logos et photos. |
| Actualités et sélection de newsletters publiables. | Proposer du contenu utile et durable sur la vitrine. | Sélection éditoriale explicite ; ne pas publier automatiquement les campagnes internes. |
| Contenu de la vitrine administrable et messages publics temporaires. | Mettre à jour les informations sans intervention technique. | Cadrer droits d'édition, dates de publication/expiration et séparation des messages publics et réservés aux structures. |
| Recherches réactives du backoffice. | Accélérer la navigation dans les catalogues. | Optimiser et mesurer d'abord les requêtes concernées. |
| Performance et contrats du portail ; cohérence de l'édition du profil. | Réduire les appels inutiles, fiabiliser les données affichées et clarifier les succès partiels. | Mesurer les chargements, typer les séquences exposées par l'API et couvrir l'échec après enregistrement de l'avatar. |
| Découpage du contrôleur de séance et frontières Doctrine. | Rendre les évolutions plus simples et plus sûres. | Extraire par responsabilités, selon les [frontières documentées](jardin-sonore-backend/docs/architecture-boundaries.md), sans refonte générale. |
| Finitions du compositeur : aperçu, médias et esthétique du PDF A4. | Améliorer la lecture et l'impression des séances. | Préserver le flux fonctionnel de génération/téléchargement ; décider des finitions après recette visuelle. |
| Synchronisation éventuelle du PDF canonique vers Google Drive. | Retrouver le document courant dans l'espace de travail partagé. | Clarifier le besoin et les droits d'accès ; conserver le PDF local comme référence disponible. |
| Aperçus de fichiers dans EasyAdmin, fluidité du CRUD répertoire et import initial de matière pédagogique depuis les séances. | Améliorer les corrections techniques et éviter les ressaisies. | Identifier les usages réels, puis définir le dédoublonnage des instruments, comptines et contenus liés. |
| Essai des formulaires Symfony sur DTO. | Réduire le code de formulaire si le gain est réel. | Attendre Symfony 8.2 stable, puis essayer un formulaire simple avant toute décision plus large. |

## Références de contexte

- [Bilan technique du 25 septembre 2026](docs/bilan-technique-2026-09-25.md) et [plan de suites associé](docs/superpowers/plans/2026-09-25-audit-remediation.md) : constats datés, dont certains points sont désormais livrés.
- [Plan d'action SEO](.codex/plan-action-seo.md) : hypothèses et pistes pour la vitrine et la visibilité locale.
- [Historique technique backend](.codex/backend-roadmap.md) : décisions et anciens lots conservés pour mémoire.
