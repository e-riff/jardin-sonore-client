# Actions de comptes et personnes depuis les structures

## Intention et périmètre

Sécuriser les envois d'invitation et de réinitialisation déclenchés depuis le backoffice, puis rendre les personnes d'une structure visibles et modifiables depuis sa fiche. La liste globale des personnes doit aussi retrouver une personne par le nom de sa structure. Le point P0 de la roadmap comprend une vérification de la provenance de l'adresse IP employée pour limiter les tentatives de connexion et de réinitialisation du portail.

L'interface concernée est le backoffice EasyAdmin, réservé aux comptes `ROLE_ADMIN`. Le portail des structures reste en lecture seule pour les données de l'annuaire. Aucun changement de schéma n'est attendu.

## Actions d'invitation et de réinitialisation

- Sur l'index, le détail et l'édition d'un compte portail, afficher les actions sous forme de formulaires `POST`, avec un jeton CSRF distinct par action et par identifiant de compte.
- Définir des routes `POST` explicites, comme l'action d'impersonation existante. Refuser `GET`, un jeton absent ou invalide, ainsi qu'un compte dans un état incompatible, même si une requête est forgée sans passer par l'interface.
- L'invitation reste autorisée uniquement pour un compte `PENDING`, et la réinitialisation uniquement pour un compte `ACTIVE`. Réutiliser `PortalPasswordTokenManager` et le service d'envoi existants ; conserver le comportement actuel si SMTP échoue.
- Après traitement, rediriger vers la fiche du compte concerné par une URL interne générée côté serveur. Ne plus utiliser `Referer`.
- Vérifier par tests fonctionnels que chaque formulaire est présent seulement au bon état, qu'un `GET` ou un mauvais CSRF ne crée aucun jeton et n'envoie aucun message, et qu'un `POST` valide traite la demande une seule fois.

## Personnes liées à une structure

- Sur le détail d'une structure, afficher la liste de ses personnes avec nom, rôle et accès direct à leur fiche. Garder l'action existante de création d'une personne préliée à la structure.
- La fiche personne existante reste le seul écran d'édition de son identité, de son rôle, de sa structure et de ses coordonnées. Un administrateur peut y accéder depuis la fiche structure ; aucun droit d'écriture n'est ajouté au portail.
- Ajouter `organization.name` aux champs recherchables de la liste EasyAdmin des personnes. Une recherche par nom de structure retourne uniquement les personnes de cette structure, sans changer les autres critères de recherche ou les filtres existants.
- Éditer une valeur d'e-mail ou de téléphone liée par référence à plusieurs fiches ne doit pas modifier les autres fiches. Le formulaire doit détacher le lien local avant de changer la valeur, puis laisser la résolution existante rattacher une valeur déjà connue si elle existe. Les propriétés du lien (libellé, type, activation) et son retrait restent locales à la personne. Préserver les préférences et les liens des autres fiches.
- Tester la navigation structure → personne → édition, la recherche par structure, et une modification de coordonnées partagées qui laisse les autres fiches intactes.

## Adresse IP de la limitation du portail

Le BFF Next.js lit aujourd'hui la première valeur de `x-forwarded-for` et la transmet à Symfony avec un secret partagé. Symfony accepte cette valeur si le secret concorde. Vérifier la configuration et le comportement du proxy public en production, puis tester si un visiteur peut imposer une première adresse arbitraire. Si c'est possible, corriger la sélection de l'adresse à la frontière de confiance et ajouter un test de non-usurpation. Si le proxy remplace déjà cet en-tête de façon fiable, garder le mécanisme et documenter la preuve.

Cette vérification ne doit afficher ni enregistrer le secret partagé dans les comptes rendus. Une incertitude sur l'infrastructure doit être signalée explicitement, sans présenter la limitation comme fiabilisée.

## Vérification et livraison

Exécuter les tests ciblés, les contrôles de style et d'analyse statique du backend, puis les vérifications du client seulement si la partie BFF change. Vérifier l'état Git et décrire les fichiers modifiés. Ne créer ni migration, ni commit, ni déploiement sans demande de l'utilisateur.
