# Notifications lors de la première disponibilité d'une séance

Décision utilisateur du 30 septembre 2026 : les comptes doivent être informés lorsqu'une séance devient nouvelle dans l'espace de leur structure. Cette décision remplace le déclenchement limité à la première publication globale ; elle ne modifie pas le chantier newsletter.

## Comportement retenu

- Une séance non publiée ne notifie personne, même si elle est rattachée à une structure.
- Une séance publiée sans structure ne notifie personne ; son rattachement ultérieur déclenche les notifications des structures nouvellement concernées.
- Publier une séance rattachée ou rattacher une séance déjà publiée enregistre sa première disponibilité pour chaque structure et programme les comptes éligibles dans la même transaction.
- L'historique séance/structure survit au retrait du rattachement. Modifier, dépublier/republier, ou retirer/rétablir le même rattachement ne notifie pas de nouveau.
- Un même compte ne reçoit qu'une notification pour une séance, même s'il appartient à plusieurs structures ; le suivi séance/compte existant est conservé. Activer sa préférence plus tard ou créer un accès portail ne provoque pas de rattrapage dans ce lot.
- Avant envoi, contrôler la publication, les droits et les préférences actuels, et conserver au moins un accès à une structure ayant déclenché cette livraison. Ne pas envoyer pour un rattachement retiré en se rabattant sur une autre structure déjà connue.
- Initialiser l'historique des rattachements existants pour les séances ayant déjà été publiées, sans programmer de mail. Les brouillons jamais publiés restent à notifier lors de leur mise en ligne.

## Mise en œuvre

Un historique technique `session_organization_availability` conserve séance, structure et date de première disponibilité, avec unicité séance/structure. Il est indépendant des rattachements actifs et supprimé uniquement lorsque la séance ou la structure disparaît. Chaque sauvegarde publiée, sous le verrou de séance existant, enregistre les disponibilités nouvelles et sélectionne leurs comptes ; les livraisons déjà présentes ne sont pas recréées.

Les livraisons mémorisent les identifiants des structures déclencheuses dans un champ JSON nullable. Les anciennes livraisons conservent leur contrôle d'accès existant ; les nouvelles vérifient les seules structures déclencheuses encore autorisées. Les états, la distribution Messenger, les mails et la génération PDF restent identiques.

Docker reçoit un distributeur de notifications exécuté toutes les 60 secondes, utilisant le worker et Mailpit existants. Le cron de production reste inchangé.

## Vérifications

Cas : brouillon rattaché ; publication sans rattachement puis ajout ; ancienne séance rattachée à une autre structure ; ajout en mode non publié puis publication ; édition/republication/retrait-rétablissement sans doublon ; compte multi-structures déjà notifié ; préférence désactivée ; transaction annulée ; rattachement déclencheur retiré avant envoi malgré un autre accès valide ; schéma limité à l'historique et aux structures déclencheuses, initialisation sans rétroactivité.

La migration de développement sera présentée pour accord avant exécution. La production conserve le fonctionnement déployé jusqu'à un déploiement explicitement demandé de cette évolution.
