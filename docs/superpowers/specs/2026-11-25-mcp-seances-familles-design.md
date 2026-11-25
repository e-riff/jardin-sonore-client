# MCP — séances, familles et éléments

## Objectif

Permettre à l'assistant de rechercher, consulter, créer et modifier les familles et séances, et de composer leurs éléments en réutilisant ou en créant les contenus nécessaires. Chaque écriture doit être présentée à l'utilisateur pour acceptation explicite dans son client MCP.

## Périmètre envisagé

- Rechercher et lire les familles, séances, éléments et leurs rattachements, avec des identifiants exacts et des liens vers le backoffice.
- Créer et modifier une famille, ses catégories et les rattachements des séances.
- Créer et modifier une séance et son déroulé, ordonner ses éléments, réutiliser une version existante ou ajouter un élément libre ou une fiche du répertoire.
- Rattacher des instruments, prolongements et médias existants ou nouvellement créés, y compris une URL YouTube ou un fichier fourni par l'utilisateur.
- Créer les séances uniquement en brouillon. Le MCP ne peut pas publier de séance ; la publication reste dans le backoffice.
- Ne pas rattacher ni détacher d'établissements : une séance créée par MCP n'en a aucun et les rattachements existants sont préservés.

## Principes techniques à préserver

- Utiliser les services métier existants et conserver les effets de bord du backoffice, notamment la génération du document et les règles de publication.
- Prévenir les écrasements simultanés avec une révision à la lecture, et les doublons en cas de nouvelle tentative de création.
- Préserver les variantes indépendantes et leurs règles de copie, les données existantes et les champs non modifiés.
- Ne pas dépublier silencieusement une séance existante : les écritures MCP sur les séances déjà publiées sont refusées ; leur consultation reste possible.
- Distinguer contenu propre à une séance et fiche partagée du catalogue ; signaler la portée d'une modification de fiche partagée.
- Valider les références, les formats et les limites des fichiers ; vérifier que le client MCP transmet effectivement leurs octets.
- Protéger les écritures par un réglage serveur dédié et des outils identifiés comme modifiants ; vérifier dans le client que la confirmation « Demander » est effective. Une annotation MCP seule ne garantit pas une confirmation interactive.
- Déployer les nouvelles écritures désactivées jusqu'à vérification du réglage du client et de la configuration de production.

## Décisions à préciser

Définir les opérations exactes de gestion et de suppression ainsi que les formats et limites d'envoi de fichiers selon les capacités réelles du client. La conception détaillée et les tests doivent couvrir les erreurs partielles, les nouvelles tentatives et le refus des écritures sur les séances déjà publiées.
