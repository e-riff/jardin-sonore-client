# Roadmap Jardin Sonore

Mise à jour le 5 octobre 2026. Ce document est la **seule source de l'état courant, des priorités et du point de reprise**. `AGENTS.md` et `.codex/jardin-sonore-guidelines.md` décrivent la méthode de travail ; les plans, conceptions et bilans datés gardent le détail technique et l'état constaté à leur date. Ne pas reprendre leurs anciennes listes de tâches sans vérifier cette page. L'ordre général est : **visibilité et nouvelles demandes**, puis **gain de temps dans le backoffice**. Les priorités indiquent un ordre de travail, pas un engagement de livraison simultanée.

## Livré — hors travaux futurs

- Backoffice métier pour l'annuaire, les médias, les prolongements, le mailing et les séances ; EasyAdmin reste disponible pour le dépannage technique.
- Masques d'audience réutilisables, ciblage géographique et extension d'une campagne déjà envoyée.
- Compositeur de séances, publication aux structures, lecture HTML et téléchargement du PDF canonique ; la génération et le téléchargement fonctionnent. L'esthétique du PDF reste à reprendre.
- Portail structures accessible avec comptes, séances, comptines, profil et impersonation ; le bandeau du portail et la stabilité du menu sont livrés.
- Règle de mot de passe du portail centralisée. Le traitement d'une réussite partielle lors de l'édition du profil reste distinct et ouvert.
- Notifications de première disponibilité par structure, préférences distinctes du profil, audiences annuaire/portail/abonnés libres, contrôle du consentement avant envoi, gestion des abonnés dans E-mails et inscription publique confirmée depuis le footer.
- Récapitulatif de fin de mailing et corrections d'affichage des médias du répertoire dans le portail livrés en production le 5 octobre 2026 (`deploy-mailing-summary-media-20261005-01`).
- Page « Mentions légales et confidentialité » publiée et reliée au footer, au sitemap et aux métadonnées. Les compléments sont reportés faute d'informations disponibles ; ne pas les relancer avant le 5 novembre 2026 et limiter ensuite le rappel à une fois par mois maximum.

## Point de reprise — 5 octobre 2026

**Prochaine séance : réfléchir au contenu d'une page destinée aux responsables de crèches et structures petite enfance qui recherchent un musicien intervenant.** Avant de la construire, préciser les publics et âges, le déroulement et les formats des ateliers, la zone de déplacement, les preuves publiables et l'appel à l'action. Le positionnement doit parler d'une intervention professionnelle en structure, pas de cours de musique pour les familles.

L'analyse initiale Search Console est faite à partir des exports du 5 octobre. Sur les trois derniers mois, le site a eu 30 clics et 395 impressions ; seule la page d'accueil apparaît dans l'export des pages. Les requêtes visibles pour « intervenant musical en crèche » (2 impressions, position moyenne 5,5) et « éveil musical crèche » (1 impression, position 20) ont un faible volume, mais correspondent à la cible prioritaire. Le tableau des requêtes n'inclut pas toutes les recherches, notamment les requêtes anonymisées.

Le rapport d'indexation montrait une page non indexée : la capture Search Console identifie `http://jardin-sonore.fr/`, redirigée vers la version HTTPS canonique. Cette redirection est attendue ; le sitemap contient les URL HTTPS de l'accueil et des mentions légales. Aucun autre problème d'indexation n'est signalé dans l'export fourni.

La fiche Google Business Profile reste à évaluer après la réflexion sur la page. Une boutique n'est pas nécessaire si l'activité va chez les clients : vérifier l'éligibilité comme activité de zone desservie, la zone réelle et le masquage de l'adresse. Les projets, témoignages et liens de partenaires restent des leviers complémentaires, après la page d'offre.

L'alerte npm `GHSA-vfj7-8cjw-p6xm` est à surveiller : elle concerne `braces` dans la chaîne de dépendances d'`eslint-config-next` ; aucun correctif n'était publié au 5 octobre. Ne pas appliquer le downgrade majeur proposé par `npm audit fix --force`.

Les actions administrateur d'invitation/réinitialisation, les lots notifications/newsletter et la page légale sont livrés. Le rendu du courriel de disponibilité est considéré bon dans Gmail et Outlook selon le souvenir de l'utilisateur ; la date et le compte utilisés n'ont pas été consignés. Le contrôle de provenance de l'IP du portail reste accepté en l'état par l'utilisateur, sans preuve que `x-forwarded-for` ne puisse pas être falsifié ; voir la réserve ci-dessous.

### Livraisons récentes

- 30 septembre : première disponibilité et préférences newsletter, tag `deploy-availability-newsletter-20260930-01` ; migrations `Version20260930130613` et `Version20260930140509` appliquées en production, schéma et trois crons contrôlés lors de cette livraison.
- 1er octobre : audiences et contrôle du consentement avant SMTP, tag `deploy-newsletter-audiences-consent-20261001-01` ; puis gestion des abonnés libres et inscription footer avec confirmation, tag `deploy-newsletter-footer-20261001-01`. Deux corrections de présentation de la confirmation ont suivi sous `deploy-newsletter-confirmation-layout-20261001-01` et `deploy-newsletter-confirmation-layout-20261001-02`. La migration `Version20261001101645` et les migrations antérieures sont confirmées appliquées en production au 5 octobre, avant la migration `Version20261005170000`.
- 2 octobre : page légale et contrôles SEO/accessibilité du front, commit `2b69a8b`, tag `deploy-legal-privacy-20261002-01`.
- 5 octobre : récapitulatif de fin de mailing, libellés distinctifs pour les médias homonymes et affichage des PDF/images liés dans le portail ; commit `a68c479`, tag `deploy-mailing-summary-media-20261005-01`. Les 311 tests backend et 1 706 assertions passent (une notice PHPUnit) ; lint PHP/PHPStan et lint/build client réussis. Migration appliquée en production et base à jour ; site public vérifié HTTP 200.

### Vérifications de livraison à garder en vue

- Les compléments de la page légale sont différés faute d'informations ; ne pas les relancer avant le 5 novembre 2026, puis au plus une fois par mois.
- Sécurité du portail : les actions administrateur sont protégées et testées. L'audit de la provenance de l'IP utilisée pour limiter les tentatives a été tenté le 5 octobre, mais Tiger Protect a renvoyé HTTP 429 ; le compte cPanel ne donne pas accès à la configuration Apache globale ni aux journaux d'accès. **Accepté en l'état pour le moment, sans preuve que `x-forwarded-for` ne puisse pas être falsifié.** Reprendre uniquement si l'hébergeur fournit la configuration du proxy ou si une recette de production non bloquée devient possible ; corriger si l'usurpation est alors constatée. Détails dans le [plan dédié](docs/superpowers/plans/2026-09-29-portal-admin-actions-security.md).
- Contrôler les journaux d'accès de l'hébergeur pour la confirmation newsletter si une vérification de production est reprise. Le jeton est placé dans le fragment du lien, puis envoyé dans le corps d'un POST à chemin constant afin de ne pas figurer dans l'URL de requête.

La [conception des disponibilités](docs/superpowers/specs/2026-09-30-session-availability-notifications-design.md), le [plan de recette notifications](docs/superpowers/plans/2026-09-30-session-availability-notifications.md) et le [plan de recette newsletter](docs/superpowers/plans/2026-10-01-newsletter-footer-and-backoffice.md) gardent les détails d'exécution. Ils ne redéfinissent pas le prochain chantier.

## P0 — Fiabilité, visibilité et demandes entrantes

| Chantier | Bénéfice attendu | Dépendances / critère de départ |
| --- | --- | --- |
| Concevoir puis créer une page d'offre « musicien intervenant en crèche et petite enfance ». | Attirer les responsables de structures qui cherchent un intervenant et faciliter les demandes de devis. | Réfléchir d'abord au contenu : types de structures et âges, objectifs pédagogiques, déroulement, formats et modalités, zone de déplacement, preuves autorisées et contact/devis. Une page principale avant d'envisager des pages géographiques. |
| Évaluer et, si éligible, créer ou compléter la fiche Google Business Profile en activité de zone desservie. | Être trouvé localement par les structures et rendre visible la zone réellement couverte. | Confirmer les interventions en personne chez les clients, la zone habituelle et les informations publiques ; masquer l'adresse si les clients ne sont pas reçus sur place. |

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
| Page collaborations et projets, avec témoignages et liens entrants de partenaires. | Rassurer les prospects et obtenir des visites de recommandation depuis les sites partenaires. | Après la page d'offre ; obtenir les accords pour noms, citations, logos et photos, et demander aux partenaires un lien vers la page pertinente. |
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
