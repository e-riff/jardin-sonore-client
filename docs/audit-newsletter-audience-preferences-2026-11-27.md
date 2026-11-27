# Audit technique : audience newsletter et préférences

Audit du code au 27 novembre 2026, après livraison `deploy-outreach-journal-20261127-01`. Lecture statique des parcours ; aucune donnée de production consultée ou modifiée, aucun envoi. Les recommandations ci-dessous demandent un cadrage avant implémentation. Ce document ne constitue pas une validation juridique.

## Socle existant

`email_contact` est l'identité technique de l'adresse : UUID, adresse unique, état actif, accord newsletter, date de désabonnement et jeton unique. `contact_details_email_link` rattache cette même adresse aux coordonnées de plusieurs personnes ou établissements. `SharedContactSubscriber` réutilise le contact existant et conserve ses préférences après retrait des rattachements.

L'abonnement indépendant existe déjà sur cette identité : `free_newsletter_subscription`, date de confirmation et origine. Il peut donc coexister avec un rattachement professionnel sans dupliquer l'adresse ni créer un établissement artificiel.

Le résolveur `DoctrineNewsletterAudienceResolver` assemble : annuaire filtré, établissements ajoutés explicitement, utilisateurs actifs du portail liés aux établissements, et abonnés indépendants confirmés si `includeFreeSubscribers` est activé. Il fusionne par adresse normalisée et cumule les établissements ciblés. La branche abonnés indépendants ne reçoit aucun filtre géographique ou de type.

Les ajouts explicites d'établissements contournent les filtres de profil et de géographie, mais conservent les contrôles actif/accord/désabonnement. Un filtre géographique vide ne signifie pas audience vide : il ne limite pas l'annuaire. Départements, communes, régions et rayon sont réunis par OU.

## Parcours constatés et écarts

| Parcours | Comportement actuel | Conséquence pour la suite |
| --- | --- | --- |
| Formulaire public | Réutilise/crée l'adresse, demande une confirmation, jeton haché valable 48 h, temporisation 60 s ; confirmation transactionnelle | La mécanique principale existe déjà. |
| Adresse déjà professionnelle qui s'inscrit publiquement | Le seul accord historique ne suffit pas à court-circuiter la demande ; confirmation ajoute le statut indépendant | Une crèche hors zone pourra recevoir sans détection d'adresse postale, si la campagne inclut les abonnés. |
| Inscription backoffice | Attestation exigée ; pour une adresse liée à l'annuaire sans abonnement indépendant, active seulement l'accord | L'action intitulée abonnement peut laisser une adresse hors masque sans réception. Harmoniser sa signification. |
| Préférence portail activée | Modifie accord/date globale ; ne confirme pas l'abonnement indépendant | Activer ne garantit pas la réception hors masque. Même incohérence que le backoffice. |
| Désactivation portail/backoffice | Coupe l'accord, date le refus, consomme les demandes en attente | Aujourd'hui cela bloque aussi le démarchage. |
| Lien de désabonnement public | GET avec jeton déclenche directement le refus global | Conserver la compatibilité des liens historiques ; examiner la mutation via GET et les robots de lecture avant changement du parcours. |
| Audience démarchage | Même résolveur et même accord newsletter que les autres campagnes | Pas de refus spécifique au démarchage ; la case abonnés indépendants peut aussi les ajouter à une prospection. |
| Contrôle juste avant SMTP | `DoctrineNewsletterRecipientEligibility` relit actif + accord newsletter + absence de désabonnement, sans type de campagne | Séparer uniquement les écrans ou la préparation serait insuffisant : adapter aussi le worker. |

## Sens réel des données historiques

`opt_in_newsletter` vaut vrai par défaut dans l'entité et le mapping. Ce champ représente donc une autorisation technique historique ; il ne prouve pas, à lui seul, une inscription volontaire. Ne pas convertir toutes ces lignes en abonnements explicites.

Le marqueur indépendant confirmé fournit une distinction plus précise. Les demandes non consommées sont des demandes en attente, jamais des abonnés confirmés. Leur présence ne suffit pas à conclure à un dysfonctionnement : expiration, abandon ou réémission peuvent l'expliquer.

Un refus porte sur l'adresse partagée. Il affecte toutes les structures utilisant cette adresse ; un refus par établissement constituerait une autre fonctionnalité. L'historique figé des destinataires reste une trace d'envoi, pas le registre vivant des abonnements.

## Architecture recommandée à valider

Conserver une identité par adresse et les liens existants. Aucune seconde liste physique d'adresses ni duplication des établissements n'est nécessaire. Distinguer trois informations : admission à l'audience newsletter, abonnement volontaire hors masque, et refus des propositions. Garder l'état actif comme blocage technique général.

Audience newsletter cible : union du masque réservé et des abonnements volontaires confirmés, dédoublonnée, puis filtrée par les préférences et blocages. Audience démarchage : ciblage propre à chaque campagne, filtré par le refus démarchage et l'exclusion glissante. L'abonnement volontaire n'ajoute jamais automatiquement quelqu'un au démarchage.

Le masque réservé doit avoir une identité technique stable et une protection de suppression côté serveur, pas seulement un nom spécial. Définir quand son contenu est copié dans une campagne : proposition recommandée, copie à la création avec audience visible avant envoi ; une modification ultérieure du masque ne doit pas modifier silencieusement une campagne déjà préparée. Vérifier les règles d'extension et de campagne planifiée dans le cadrage.

Tous les points d'entrée doivent appliquer les mêmes règles : formulaire public, portail, administration, import/annuaire, outils MCP, sélection, extension et worker. La réinscription newsletter ne doit pas effacer implicitement un refus démarchage. Un ancien lien global doit continuer à tout arrêter.

## Migration et vérifications préalables

1. Mesurer en lecture seule les volumes agrégés : adresses actives, refus, abonnements confirmés, demandes en attente/expirées, liens multiples et abonnés aussi présents dans l'annuaire. Examiner les écritures d'import et le modèle de domaine/mapper avant choix final des colonnes.
2. Définir la correspondance des anciens états : refus historique appliqué aux deux usages par prudence ; accord par défaut conservé comme état historique, sans fabrication de preuve ni nouvelle confirmation.
3. Ajouter les états nécessaires et leur reprise déterministe, puis vérifier les mêmes comptes avant/après sur une copie locale. Préserver UUID, liens, jetons et historique d'envoi existants.
4. Adapter les parcours ensemble avant activation des préférences distinctes. Les campagnes déjà en file demandent une règle explicite ; le contrôle à l'envoi effectif doit toujours honorer le refus courant.
5. Couvrir les cas métier : inscription d'une crèche hors masque, adresse partagée, double inscription, refus entre préparation et SMTP, réinscription newsletter avec refus démarchage maintenu, ancien lien global, abonnés exclus de la prospection, modification du masque et extension.

## Ampleur et ordre proposé

Le prochain lot est une évolution transversale des préférences et de la sélection, avec une migration limitée à l'identité email et éventuellement au rôle du masque. Il ne nécessite pas de refaire les snapshots, l'annuaire ou le journal. Son risque principal est la cohérence entre les différentes écritures et le worker, plutôt que le volume de données à déplacer.

Commencer par le relevé agrégé et le cadrage des états/liens de désabonnement, puis rédiger un plan couvrant backend, portail, formulaire public, backoffice et MCP. Les articles publics restent un chantier éditorial et de publication distinct.

## Complément : relevé de production et écritures, 27 novembre

Connecteur PhpStorm `[PROD]` déclaré en lecture seule ; deux SELECT agrégés, aucune adresse nominative extraite ni écriture.

| Indicateur | Nombre |
| --- | ---: |
| Identités email | 2 638 |
| Actives | 2 586 |
| Accord faux ou date de désabonnement présente | 76 |
| Abonnements indépendants confirmés | 0 |
| Avec au moins un lien actif à l'annuaire | 2 584 |
| Avec plusieurs liens actifs | 211 |
| Demandes de confirmation | 1 |
| Dont consommées / encore valides / expirées non consommées | 0 / 0 / 1 |

Ces catégories se recouvrent : les 76 ne sont pas nécessairement 76 clics de désabonnement, car le champ est aussi utilisé par des traitements techniques et des formulaires. Aucun abonné indépendant confirmé n'est donc à convertir actuellement ; ne pas en déduire qu'aucun professionnel n'a demandé la newsletter par un autre canal. Les 211 adresses liées plusieurs fois confirment l'intérêt de conserver les préférences à l'adresse.

`DirectoryEstablishmentUpserter` active l'accord pour les nouvelles adresses importées, mais réutilise sans réactivation les adresses déjà connues. Conserver cette protection. Les formulaires de lien créent pour leur part certaines adresses avec accord faux : l'origine doit rester explicite lors de la migration.

`DoctrineInvalidRecipientBatchProcessor` utilise également l'accord faux pour les adresses invalides et peut désactiver leurs liens ; sa branche désabonnement écrit la date mais ne consomme pas elle-même les demandes en attente. Ce parcours doit rejoindre la même opération de refus que portail/backoffice/lien public pour empêcher une confirmation antérieure de rouvrir l'envoi. Le repository de domaine consomme déjà les demandes lors du refus, sous verrou.

Deux approches restent possibles : étendre les champs de `email_contact` avec des refus distincts et réutiliser l'abonnement confirmé existant ; ou créer un registre de préférences par finalité et son historique. La première minimise les changements sur les données et les parcours actuels. La seconde facilite de futurs canaux et une chronologie détaillée, au prix d'une reprise et de jointures supplémentaires. Pour les deux usages actuellement demandés, l'extension de l'identité email est recommandée ; le choix et les règles de réinscription doivent être validés avant conception finale.

## Sources principales inspectées

- `src/Infrastructure/Doctrine/Entity/EmailContactEntity.php` et son mapping.
- `src/Infrastructure/Doctrine/EventSubscriber/SharedContactSubscriber.php`.
- `src/Infrastructure/Mailing/DoctrineNewsletterAudienceResolver.php`.
- `src/Infrastructure/Mailing/FreeNewsletterSubscriptionManager.php`.
- `src/Infrastructure/Portal/PortalNewsletterSubscriptionManager.php`.
- `src/Infrastructure/Mailing/DoctrineNewsletterRecipientEligibility.php`.
- `src/Application/Mailing/UnsubscribeNewsletterRecipient.php`.
- `src/Application/Controller/NewsletterController.php`.
- `src/Application/Mailing/MessageHandler/SendMailingCampaignRecipientMessageHandler.php`.

Chemins relatifs au backend `jardin-sonore-backend/`.
