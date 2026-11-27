# Audience newsletter et préférences par adresse

Statut : conception approuvée par l'utilisateur le 27 novembre 2026 ; implémentation en local sur `feat/newsletter-preferences`. Approche par extension de l'identité email choisie par l'utilisateur. Référence : [audit et relevé de production](../../audit-newsletter-audience-preferences-2026-11-27.md).

## Intention et périmètre

Recevoir la newsletter par le cœur de cible sélectionné ou par inscription volontaire, partout ; pouvoir arrêter séparément newsletter et propositions commerciales. Une adresse reste unique, même liée à plusieurs établissements. Préserver les refus historiques et les blocages techniques sans inventer de consentement.

Ce lot couvre sélection, préférences, inscriptions publiques/portail/backoffice, imports, MCP, extensions et contrôle avant SMTP. Les templates et l'exclusion glissante sont déjà livrés. Articles publics, calendrier éditorial et élargissement géographique effectif sont hors de ce lot.

## Approches

Approche retenue : étendre `email_contact` et réutiliser son abonnement indépendant confirmé. Elle garde les identités, liens et jetons et évite une reprise des rattachements. L'alternative, un registre séparé par finalité avec chronologie complète, facilite de futurs canaux mais augmente la reprise et les accès ; elle n'est pas retenue pour ces deux usages. Aucun nouveau CRM ni historique de réponses.

## États proposés

Les noms définitifs suivent les conventions du code ; les significations suivantes constituent le contrat métier.

| Information | Signification |
| --- | --- |
| `active` existant | Adresse techniquement utilisable ; faux bloque tous les mailings. |
| Admission annuaire | Adresse autorisée à participer au ciblage professionnel ; ce n'est pas une preuve d'abonnement. |
| Abonnement newsletter confirmé existant | Demande volontaire, indépendante de la géographie, avec date et origine. |
| Refus newsletter daté | Prioritaire sur masque, abonnement et ajout explicite. |
| Refus démarchage daté | Prioritaire sur tout ciblage commercial. |

Proposition technique : ajouter un indicateur d'admission annuaire et deux dates de refus. Les champs historiques `opt_in_newsletter` / `unsubscribed_at` restent disponibles pour lire la portée des anciennes campagnes, mais cessent d'être une seconde source modifiable des préférences modernes. Toutes les opérations passent par un service commun et mettent à jour la projection historique nécessaire. Le plan précisera les noms et cette projection avant migration.

Une confirmation newsletter crée ou conserve l'abonnement, lève seulement le refus newsletter et ne change pas l'admission annuaire ni le refus démarchage. Un refus conserve la date/origine de l'inscription comme historique mais empêche sa réception. Une adresse inactive ne peut être réactivée par inscription.

## Règles d'audience

Newsletter : union du ciblage annuaire admis et de tous les abonnés confirmés, puis exclusion des inactifs et refus newsletter, déduplication par adresse normalisée. Les abonnés hors masque ne nécessitent aucune localisation. Les établissements figés dans l'historique restent uniquement ceux réellement sélectionnés par le ciblage.

Démarchage : ciblage annuaire admis moins inactifs et refus démarchage, puis exclusion glissante existante. Aucune inclusion par abonnement newsletter seul. Une adresse abonnée peut recevoir une proposition si elle est indépendamment retenue par son contexte professionnel et n'a pas refusé ce type.

Le résolveur doit recevoir explicitement le type de campagne. Aperçu, préparation, extensions, MCP et worker utilisent la même politique. Un masque vide ne doit jamais déclencher un envoi à tout l'annuaire. Les anciennes campagnes non classées gardent le refus historique global et respectent aussi tout nouveau refus de l'un des deux types, afin de ne pas contourner un choix par une campagne sans type.

## Masque réservé

Un rôle technique unique identifie le masque newsletter, protégé contre suppression et changement de rôle côté serveur, y compris MCP. Ses critères restent modifiables. Création de newsletter : copie des critères du masque ; inclusion des abonnés confirmés obligatoire pour les newsletters typées. Les critères restent inspectables avant diffusion et les ajouts explicites restent possibles.

Une modification du masque ne réécrit pas les campagnes préparées. Une action explicite « Réappliquer le masque newsletter » actualise un brouillon ; afficher son effet avant sauvegarde. Les préférences et l'état actif sont toujours relus à l'envoi réel. Une campagne planifiée résout ses critères enregistrés au moment de mise en file, puis contrôle les refus au SMTP ; un nouvel abonné après constitution de la file peut rejoindre une extension, sans envoi automatique rétroactif.

Initialisation : aucun nouveau territoire ni profil ajouté automatiquement. Créer le masque réservé désactivé/non configuré ; demander une configuration explicite ou la copie contrôlée d'un masque existant avant création de nouvelles newsletters. Le masque « Forez et Gier » inclut des profils plus larges que le cœur éditorial et ne doit pas être consacré implicitement. Les campagnes existantes conservent leurs critères et leur file.

## Parcours de préférences

Proposition de wording à valider : newsletter, lien principal « Ne plus recevoir la newsletter » ; démarchage, « Ne plus recevoir de propositions ». Chaque mail offre également « Gérer mes préférences », où « Tout arrêter » reste disponible. Les anciens liens globaux continuent à tout arrêter.

La page de préférences ne demande pas de compte et utilise un jeton opaque. Consultation sans mutation ; modification par POST protégé, boutons accessibles et confirmation lisible. Le GET des anciens liens conserve son effet global pour compatibilité dans ce lot ; les nouveaux liens ouvrent une page permettant l'action ciblée par un bouton explicite. Les éventuels en-têtes automatisés de désabonnement doivent être inventoriés et alignés sur la portée annoncée avant livraison.

Le portail affiche deux choix distincts et explique la portée sur l'adresse partagée. Activer la newsletter depuis un compte authentifié crée l'abonnement indépendant avec origine portail. Lever un refus démarchage n'inscrit pas à la newsletter et nécessite une action explicitement nommée ; cela n'accorde pas l'admission annuaire. Aucun réglage ne bloque les notifications de service.

Le formulaire public garde confirmation, expiration et limitation existantes. Backoffice : inscription avec attestation crée systématiquement l'abonnement indépendant, y compris pour une adresse déjà dans l'annuaire. Une correction d'annuaire ou un import ne lève aucun refus. Le traitement groupé de refus consomme aussi les confirmations en attente. Les modifications sensibles exposées via MCP suivent les mêmes validations et révisions ; aucune nouvelle capacité d'envoi autonome.

## Migration proposée

Relevé du 27 novembre : 2 638 adresses, 2 586 actives, 76 avec accord faux ou date globale présente, aucun abonnement indépendant confirmé, 211 avec plusieurs liens actifs, une demande expirée non consommée. Ces comptes ne représentent pas 76 oppositions volontaires prouvées.

Pour chaque ligne historique : admission annuaire = ancien accord vrai et absence de date globale ; tout ancien accord faux ou date globale donne refus des deux usages. Reprendre la date connue ; sinon utiliser une date de transition documentée, sans la présenter comme date d'opposition réelle. Conserver `active`, les liens, les jetons, les preuves existantes et les demandes. Aucun accord historique transformé en abonnement confirmé. Aucune réactivation ou modification des envois historiques.

Migration additive générée avec Doctrine, revue SQL/up/down, test sur une copie locale et comparaison des comptes d'éligibilité à critères identiques : aucune adresse jusque-là bloquée ne devient sélectionnable. Application séparément autorisée selon AGENTS.md. Déploiement coordonné des lecteurs/écrivains et redémarrage des workers ; ne pas laisser du code ancien écrire les seuls champs historiques après bascule.

## Vérification et risques

Scénarios essentiels : abonnement d'une crèche hors zone ; inscription publique/backoffice/portail cohérente ; adresse partagée ; refus ciblé puis réinscription sans levée de l'autre refus ; lien ancien global ; refus après mise en file ; traitement groupé avec confirmation en attente ; import sans réactivation ; abonné seul absent du démarchage ; masque non configuré ; copie explicite du masque ; anciens destinataires en file ; notifications de service indépendantes.

Recette des formulaires réels et du mobile, HTML/texte et liens des deux types, aperçu/extension/MCP, tests pertinents puis contrôles statiques et assets. Aucun envoi réel de recette à des tiers. Comparer les volumes agrégés avant/après migration et vérifier le schéma sans diff parasite.

## Arbitrages proposés par Codex à approuver

Précisions après relecture, prioritaires sur les formulations précédentes : chaque refus comporte un indicateur et une date nullable ; un refus historique sans date n'obtient aucune date inventée. La projection historique donne accord vrai seulement si admission ou abonnement confirmé et aucun refus ; sa date globale reprend la première date connue des refus actifs, sinon null. Un refus sans date coupe néanmoins l'accord. Les lecteurs modernes utilisent les nouveaux états.

La reprise conserve volontairement une admission fausse pour les anciennes adresses bloquées ; lever un refus seul ne suffit donc pas à les réadmettre au ciblage professionnel. Prévoir une action backoffice distincte « Autoriser le ciblage professionnel », avec contexte et confirmation explicites, sans lever les refus ni créer d'abonnement. La réinscription newsletter reste indépendante de cette admission.

Le worker contrôle préférences et blocages sur l'audience figée ; il ne réévalue pas la géographie courante. L'interdiction du masque vide signifie « non configuré » ; une audience sans restriction géographique reste possible par configuration explicite. La présentation administrative distingue absence de date historique et date d'un choix réellement exprimé.

- Lien principal de chaque nouveau mail limité au type de ce mail ; anciens liens globaux conservés.
- Deux dates de refus et admission annuaire explicite, avec projection historique pour les campagnes anciennes.
- Copie du masque à la création, actualisation explicite des brouillons ; pas d'élargissement automatique.
- Masque réservé à configurer sans sélection géographique automatique.
- Portail authentifié et backoffice attesté créent un abonnement indépendant réel.

Après validation : plan détaillé et découpage de l'implémentation. Aucun code produit ou schéma modifié par ce document.
