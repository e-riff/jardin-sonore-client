# Roadmap Jardin Sonore

Mise à jour le 5 octobre 2026. Ce document est la **seule source de l'état courant, des priorités et du point de reprise**. `AGENTS.md` et `.codex/jardin-sonore-guidelines.md` décrivent la méthode de travail ; les plans, conceptions et bilans datés gardent le détail technique et l'état constaté à leur date. Ne pas reprendre leurs anciennes listes de tâches sans vérifier cette page. L'ordre général est : **visibilité et nouvelles demandes**, puis **gain de temps dans le backoffice**. Les priorités indiquent un ordre de travail, pas un engagement de livraison simultanée.

## Livré — hors travaux futurs

- Backoffice métier pour l'annuaire, les médias, les prolongements, le mailing et les séances ; EasyAdmin reste disponible pour le dépannage technique.
- Masques d'audience réutilisables, ciblage géographique et extension d'une campagne déjà envoyée.
- Compositeur de séances, publication aux structures, lecture HTML et téléchargement du PDF canonique ; la génération et le téléchargement fonctionnent. L'esthétique du PDF reste à reprendre.
- Portail structures accessible avec comptes, séances, comptines, profil et impersonation ; le bandeau du portail et la stabilité du menu sont livrés.
- Règle de mot de passe du portail centralisée. Le traitement d'une réussite partielle lors de l'édition du profil reste distinct et ouvert.
- Notifications de première disponibilité par structure, préférences distinctes du profil, audiences annuaire/portail/abonnés libres, contrôle du consentement avant envoi, gestion des abonnés dans E-mails et inscription publique confirmée depuis le footer.
- Page « Mentions légales et confidentialité » publiée et reliée au footer, au sitemap et aux métadonnées. Son contenu reste à compléter sur les points indiqués en P0.

## Point de reprise — 5 octobre 2026

**Les actions administrateur d'invitation/réinitialisation, les lots notifications/newsletter et la page légale sont livrés. Aucun n'est à recommencer.** Le rendu du courriel de disponibilité est considéré bon dans Gmail et Outlook selon le souvenir de l'utilisateur ; la date et le compte utilisés pour cette vérification ne sont pas consignés. La branche active est `main`, propre et synchronisée avec `origin/main` lors du contrôle du 5 octobre. Le prochain point sécurité est l'audit de la provenance de l'IP utilisée par le rate limiting du portail ; ne modifier le code que si le proxy permet de falsifier `x-forwarded-for`.

### Livraisons récentes

- 30 septembre : première disponibilité et préférences newsletter, tag `deploy-availability-newsletter-20260930-01` ; migrations `Version20260930130613` et `Version20260930140509` appliquées en production, schéma et trois crons contrôlés lors de cette livraison.
- 1er octobre : audiences et contrôle du consentement avant SMTP, tag `deploy-newsletter-audiences-consent-20261001-01` ; puis gestion des abonnés libres et inscription footer avec confirmation, tag `deploy-newsletter-footer-20261001-01`. Deux corrections de présentation de la confirmation ont suivi sous `deploy-newsletter-confirmation-layout-20261001-01` et `deploy-newsletter-confirmation-layout-20261001-02`. La migration `Version20261001101645` avait été validée en développement/test ; son état en production n'a pas été revérifié lors du contrôle du 5 octobre.
- 2 octobre : page légale et contrôles SEO/accessibilité du front, commit `2b69a8b`, tag `deploy-legal-privacy-20261002-01`.
- 5 octobre, contrôle de reprise : page légale et sitemap publics HTTP 200, route de confirmation HTTP 200 ; 311 tests backend / 1 706 assertions, 14 tests Node, lint et build front, style PHP, PHPStan, validations Symfony et schéma local réussis. Deux dépréciations et une notice PHPUnit déjà consignées. Selon le souvenir de l'utilisateur, le rendu du courriel de disponibilité est bon dans Gmail et Outlook ; les notes antérieures n'en précisent ni la date ni le compte utilisé. L'état de la migration newsletter en production reste à revérifier.

### Vérifications de livraison à garder en vue

- Les informations restant à compléter sur la page légale figurent en P0 ci-dessous.
- Contrôler les journaux d'accès de l'hébergeur pour la confirmation newsletter si une vérification de production est reprise. Le jeton est placé dans le fragment du lien, puis envoyé dans le corps d'un POST à chemin constant afin de ne pas figurer dans l'URL de requête.

La [conception des disponibilités](docs/superpowers/specs/2026-09-30-session-availability-notifications-design.md), le [plan de recette notifications](docs/superpowers/plans/2026-09-30-session-availability-notifications.md) et le [plan de recette newsletter](docs/superpowers/plans/2026-10-01-newsletter-footer-and-backoffice.md) gardent les détails d'exécution. Ils ne redéfinissent pas le prochain chantier.

## P0 — Fiabilité, visibilité et demandes entrantes

| Chantier | Bénéfice attendu | Dépendances / critère de départ |
| --- | --- | --- |
| Vérifier la provenance de l'IP utilisée pour limiter les tentatives sur le portail. | Rendre la limitation fiable face aux en-têtes fournis par le visiteur. | Examiner la chaîne proxy → Next.js BFF → Symfony en production et tester `x-forwarded-for` ; corriger seulement si l'usurpation est possible. Suivre le [plan dédié](docs/superpowers/plans/2026-09-29-portal-admin-actions-security.md). |
| Compléter les informations de la page légale déjà publiée. | Donner des informations fiables aux visiteurs et aux structures. | L’utilisateur ne souhaite pas afficher d’adresse, d’e-mail ni de téléphone ; documenter cet écart, fixer les durées de conservation et mettre en place la purge correspondante. Hébergeur o2switch identifié. |
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
