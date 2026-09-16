# Cycle de vie des comptes portail

## Objectif

Rendre un compte portail administrable et utilisable depuis Symfony, sans modifier le client Next.js : correction de son e-mail, gestion de ses accès aux structures, invitation initiale et réinitialisation de mot de passe.

## Périmètre

Le back-office EasyAdmin gère les comptes. Une page Symfony publique, volontairement provisoire, permet de définir un mot de passe depuis un lien reçu par e-mail. Le portail front reste hors périmètre et pourra consommer les mêmes liens ou les remplacer plus tard.

## Compte et accès aux structures

Le formulaire d'édition d'un compte portail présente :

- l'e-mail propre au compte, modifiable et unique ; cette valeur n'est pas synchronisée vers l'annuaire ;
- son état (`En attente`, `Actif`, `Inactif`) et la liste de ses accès ;
- l'ajout d'une structure par le mécanisme EasyAdmin existant ;
- la suppression d'un accès, sauf lorsqu'il s'agit du dernier accès du compte.

Un accès associe toujours le compte à une structure et, si applicable, à la personne déjà créée lors de l'attribution de l'accès. Une même structure ne peut être liée qu'une fois au même compte.

## Invitations et réinitialisations

Deux types de liens utilisent le même mécanisme : `invitation` et `password_reset`.

- L'invitation est disponible pour un compte en attente ; elle peut être renvoyée.
- La réinitialisation est disponible pour un compte actif.
- Chaque génération invalide les liens encore valides du même type pour ce compte.
- Un lien expire après une durée configurée et n'est utilisable qu'une fois.
- Les jetons sont générés aléatoirement, transmis seulement dans l'URL et stockés uniquement sous forme d'empreinte cryptographique.

EasyAdmin propose les actions contextuelles appropriées et confirme à l'administrateur que l'e-mail a été remis au transport. Un échec du transport ne marque pas le jeton comme consommé.

## Page Symfony provisoire

Une route publique reçoit le jeton et affiche un formulaire avec mot de passe et confirmation.

- Un jeton inconnu, expiré ou déjà utilisé affiche un message neutre sans révéler de compte.
- Un mot de passe valide est haché avec le hasher Symfony configuré.
- Après validation, le jeton est consommé ; une invitation fait passer le compte à `Actif`, tandis qu'une réinitialisation conserve son état actif.
- La page affiche un succès puis renvoie vers une destination provisoire définie côté Symfony. Elle ne crée pas encore de session portail ni de firewall portail.

## Données et architecture

Une entité Doctrine dédiée `UserPasswordTokenEntity` porte le compte, le type, l'empreinte, la date d'expiration, la date de consommation et les dates techniques. Un service applicatif unique gère la création, l'invalidation, la validation et la consommation des jetons. Un expéditeur de mail dédié construit l'e-mail et son URL à partir d'un paramètre de base configurable.

Le mécanisme n'est pas couplé au carnet d'adresses : l'e-mail du compte reste une identité de connexion autonome. La future application portail réutilisera la même persistence et les mêmes services, mais pourra fournir ses propres pages et authentification.

## Erreurs et sécurité

- L'e-mail de compte reste protégé par l'unicité existante.
- La dernière structure ne peut pas être retirée, côté serveur comme dans l'interface.
- Les jetons ne sont jamais journalisés ni persistés en clair.
- Le formulaire public est protégé contre le CSRF et ne divulgue pas l'existence d'un compte.
- Les comptes inactifs ne reçoivent ni invitation ni lien de réinitialisation.

## Vérification

Tests unitaires du cycle de jetons (création, invalidation, expiration, consommation) et de la règle du dernier accès. Tests fonctionnels des actions EasyAdmin et de la page publique : lien valide, lien invalide, mots de passe discordants, activation par invitation et changement de mot de passe.

## Hors périmètre

- Écran et authentification du portail Next.js.
- Synchronisation automatique entre e-mail portail et e-mail d'annuaire.
- Authentification automatique après le choix de mot de passe.
