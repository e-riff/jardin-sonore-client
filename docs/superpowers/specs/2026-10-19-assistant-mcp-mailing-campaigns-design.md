# MCP : lecture et édition des campagnes de mailing

**État :** déployé le 19 octobre 2026 sous le tag `deploy-mcp-mailing-drafts-20261019-01`. `MCP_MAILING_WRITE_ENABLED=1` est actif en production ; la valeur par défaut reste `0`.

## Besoin

Mistral doit pouvoir consulter les campagnes existantes, y compris celles envoyées, afin de reprendre leur ton et certains contenus. Il peut créer et modifier une campagne uniquement tant que l'envoi aux destinataires n'a pas commencé. La confirmation des écritures reste demandée dans le client MCP.

## Outils

- `search_mailing_campaigns` fournit une liste bornée de campagnes, leur statut et leur caractère éditable.
- `get_mailing_campaign` retourne le contenu complet, les blocs d'introduction, les recommandations, le masque appliqué et une révision à transmettre lors d'une modification.
- `list_mailing_audience_masks` liste les masques par UUID et nom, sans exposer les adresses des destinataires.
- `create_mailing_draft` crée une campagne en brouillon. Un identifiant de requête stable protège contre la création en double après une reprise.
- `update_mailing_draft` accepte les champs à changer et exige la révision courante.

## Garde-fous

Le domaine `MailingCampaign::isEditable()` interdit les modifications dès que l'envoi est planifié, commencé, arrêté, terminé ou en échec. La garde est refaite au moment de l'écriture. Aucun outil MCP ne programme, teste ou envoie un mailing.

Le formateur HTML du mailing nettoie le contenu des blocs texte. Les images déjà présentes dans l'introduction sont conservées ; le MCP ne peut pas les téléverser ou les modifier.

Les recommandations appartiennent au mailing et ne sont pas partagées depuis un catalogue. Le MCP peut les lire, les créer sans image, modifier leur texte, leur lien, leur état actif et leur ordre, ou les retirer du mailing. L'image est nullable en base. Quand une recommandation sans image est créée, la réponse du MCP porte un rappel explicite à transmettre à Emeric afin qu'il ajoute l'image dans le backoffice. Retirer une recommandation du mailing ne supprime pas son éventuel fichier image.

Les masques existants sont rattachables, sans création ni modification de masque par le MCP.

## Vérification et livraison

Les tests ciblés MCP, PHPStan, PHP CS Fixer et la validation du schéma Doctrine passent. Aucun changement de schéma n'était nécessaire. La vérification après déploiement confirme les cinq outils mailing dans la liste MCP et le rejet d'une demande de création incomplète avant toute sauvegarde. L'opération ne peut ni envoyer, ni planifier, ni tester l'envoi d'une campagne.
