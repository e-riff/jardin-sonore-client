# Transfert de la gestion des comptes portail

## Intention

Déplacer les écrans de gestion des comptes portail d’EasyAdmin vers l’onglet Technique du backoffice métier afin de regrouper l’administration dans une interface cohérente.

## Objectif et limites

- Reproduire les parcours et fonctions visibles aujourd’hui dans EasyAdmin : liste/recherche, création d’un accès organisation, consultation, modification, gestion des accès, invitation, réinitialisation du mot de passe et ouverture du portail.
- Conserver les entités, tables, identifiants, services applicatifs, règles d’autorisation, états, validations, effets annuaire, envois d’e-mails et protections CSRF existants.
- Ne pas migrer les données ni changer le comportement métier.
- Ne pas inclure les comptes des administrateurs internes, le carnet d’adresses lui-même, ni une refonte des règles d’accès.
- Signaler séparément les incohérences éventuellement découvertes ; ne pas les corriger dans ce transfert.

## Approches

- Garder EasyAdmin et simplement ajouter un lien depuis Technique : trop peu cohérent avec le déplacement demandé.
- Réécrire les parcours métier : risque de divergence et hors périmètre.
- Recréer les écrans dans le backoffice en réutilisant services, entités et règles actuels : approche retenue, car elle déplace l’interface sans déplacer le métier.

## Décisions et contraintes

- Conserver les parcours EasyAdmin actuels, y compris la sélection ou création d’un contact au moment de la création d’un accès.
- Garder la même distinction d’état pour invitation, réinitialisation et impersonation.
- Garder l’ouverture du portail via formulaire POST CSRF et la page de lancement existante.
- Conserver les comportements de modification du compte et de sa collection d’accès, sans introduire de nouveau statut ni de nouvelle action.
- Aucune migration Doctrine attendue.
- Ajouter en fin de lot un protocole de recette locale en Markdown avec cases à cocher, couvrant les comportements et les e-mails.

## À la réalisation

Créer les routes, formulaires et vues internes nécessaires, intégrer un sous-onglet « Comptes portail » sous Technique, retirer le raccourci EasyAdmin vers ce CRUD, puis documenter les scénarios de recette manuelle locale.
