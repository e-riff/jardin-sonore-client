# Brouillons MCP et notifications personnelles

## Intention et décisions confirmées

Permettre au connecteur de retrouver et modifier les brouillons de la boîte Jardin
Sonore, y compris ceux créés ou modifiés dans un logiciel mail. Ajouter un outil
MCP distinct pour les notifications des tâches planifiées de Mistral, envoyées par
le backend depuis l'adresse Jardin Sonore vers l’adresse personnelle confirmée par défaut, ou une adresse explicitement
fournie dans `recipient` (amendement utilisateur après la première livraison).
Aucun accès à Gmail n'est nécessaire.

L'utilisateur a donné son accord pour l'élargissement des droits sur les
brouillons et pour le principe des notifications personnelles. Le stockage de
leur résultat en base a été validé par l’utilisateur.

## Constats dans le code

- `EditStandaloneMailDraft::assertOwnedMessageId()` exclut les identifiants des
  brouillons manuels. La lecture dépend d'un Message-ID déjà connu.
- `UpdateMailDraft` dépend du Message-ID mémorisé dans un reçu de création MCP.
  Un changement d'identifiant par un logiciel mail peut donc rompre le lien ;
  ce comportement du logiciel n'est pas encore reproduit sur un cas réel.
- La révision actuelle couvre seulement les corps texte et HTML.
- `MailDraft` et les générateurs MIME ne gèrent pas les pièces jointes.
- Le rapport commercial réutilise `DEFAULT_CONTACT` pour l'expéditeur et le
  destinataire. Le fichier de référence ne définit pas de destinataire personnel
  MCP. Ne pas réutiliser un paramètre de destinataires de test ou de newsletter.
- `DraftHtmlBody` filtre déjà le HTML selon une liste d'éléments et de liens sûrs.

## Approches pour les brouillons

1. Élargir seulement les Message-ID acceptés : changement limité, mais sans
   recherche ni conservation des pièces jointes, insuffisant pour le besoin.
2. Donner accès au dossier Brouillons par recherche et édition générale :
   approche recommandée et conforme au « go » de l'utilisateur. Conserver les
   anciens outils comme points d'entrée compatibles.

### Comportement proposé

- Ajouter `search_mail_drafts`, avec recherche littérale facultative et limite
  de 1 à 25 résultats, dans le seul dossier Brouillons configuré.
- Retourner les identifiants courants, l'objet, les destinataires et la date.
  Un identifiant perdu ou périmé impose une nouvelle recherche ; ne jamais
  choisir automatiquement un brouillon d'après un objet ressemblant.
- Étendre la lecture et l'édition aux brouillons manuels et aux réponses.
- Autoriser la modification de l'objet et des destinataires, ainsi que des
  corps texte et HTML. Les champs omis conservent leur valeur, y compris le HTML.
- Conserver les destinataires To/Cc/Bcc, le contexte de réponse et les pièces
  jointes existantes lors d'une modification du texte.
- Ajouter des pièces jointes par nom de fichier, type MIME et contenu base64,
  sans URL distante ni chemin de fichier serveur. Proposition de limites :
  10 pièces jointes, 5 Mio par fichier et 10 Mio cumulés après décodage ; appliquer
  les limites aussi avant décodage. Retourner leurs métadonnées sans le base64.
- Prévoir le retrait explicite d'une pièce jointe par son identifiant issu de la
  lecture. Une liste d'ajouts vide ne retire pas les pièces jointes existantes.
- La révision inclut tous les éléments conservés/modifiés, dont les pièces
  jointes et destinataires ; un changement externe entraîne un conflit et une
  invitation à relire. Ne pas supprimer ce contrôle sous prétexte de droits élargis.
- En IMAP, vérifier le remplacement avant de retirer l'ancien UID. Ne jamais
  expurger globalement le dossier. Conserver les gardes UIDPLUS existantes.
- Le connecteur n'obtient aucun droit d'envoi de ces brouillons. Les autorisations
  serveur de création/édition existantes restent applicables.

## Approches pour les notifications

1. Simple log applicatif : pratique pour diagnostiquer, mais ne réserve pas de
   façon atomique une demande et ne suffit pas à éviter les doublons concurrents.
2. Petite entité persistée : recommandée pour tracer et dédoublonner sans stocker
   les corps du mail. Réutiliser les conventions Doctrine des reçus MCP.

### Contrat proposé : `send_personal_notification`

- Entrées obligatoires : `requestId`, `subject`, `body`.
- Entrées facultatives : `htmlBody`, `recipient`.
- `requestId` identifie une occurrence de tâche (par exemple une veille à une
  date donnée) et reste identique lors des relances de cette occurrence.
- Destinataire par défaut fixé côté serveur par `MCP_PERSONAL_NOTIFICATION_RECIPIENT` ;
  valeur de production prévue : adresse personnelle confirmée, conservée hors Git. Le champ `recipient` peut sélectionner une adresse email valide de 254 caractères
  maximum. Aucun argument `to`, `cc`, `bcc`, expéditeur ou pièce jointe n'est accepté.
- Expéditeur issu de `DEFAULT_CONTACT`, transport SMTP du backend existant.
- Activation indépendante : `MCP_PERSONAL_NOTIFICATION_ENABLED`, désactivée par
  défaut. Une tâche autorisée peut appeler l'outil sans confirmation interactive
  à chaque occurrence ; les réglages Mistral devront permettre cet usage.
- Objet limité à 255 caractères sans contrôles, texte à 10 000 caractères,
  HTML à 20 000 caractères ; filtrage HTML existant réutilisé.
- Proposition de plafond : 10 demandes réservées par heure, globalement pour
  ce canal, avec contrôle atomique. Une relance déjà connue ne consomme pas une
  nouvelle place.

### Trace et absence de doublons

Une entité conserve : clé de demande unique, empreinte des paramètres effectifs
(destinataire et expéditeur inclus), état, dates de création et de fin. Aucun
corps HTML/texte ni secret SMTP n'est stocké dans cette trace.

Réserver la demande avant l'appel SMTP. Même clé et même empreinte : retourner
l'état connu sans renvoyer. Même clé et contenu différent : conflit explicite.
Une réservation concurrente perdante n'envoie rien.

Les états distinguent la réservation/envoi en cours, l'acceptation SMTP et
l'issue incertaine. Une coupure après acceptation SMTP peut laisser une issue
incertaine : ne pas relancer automatiquement et ne pas promettre un envoi
« exactement une fois ». L'acceptation SMTP ne prouve pas la livraison dans Gmail.
Les erreurs détaillées restent dans les logs serveur, sans corps ni secrets.

## Vérification et livraison

Deux lots : brouillons d'abord, notifications ensuite. Tests de comportement
avant implémentation, tests MIME/IMAP, tests de contrats MCP et de concurrence
pour les réservations. Vérifier notamment les brouillons manuels, les changements
externes, la conservation Cc/Bcc/pièces jointes/contexte de réponse, les limites
base64, les relances et les résultats SMTP incertains.

Exécuter PHPUnit backend avec `memory_limit=1G`, le style, PHPStan et le lint
du conteneur Symfony. Une recette IMAP et Mistral réelle reste nécessaire après
déploiement ; les tests locaux ne la remplacent pas.

L'entité de notification nécessite une migration : la générer, relire son SQL et
son `down()`, vérifier le diff de schéma, puis demander confirmation avant son
exécution conformément aux consignes du projet. Aucun commit, déploiement ou
mail réel n'est inclus sans autorisation correspondante.


## Amendement confirmé après livraison initiale

L’utilisateur a explicitement demandé la possibilité d’un destinataire arbitraire.
Le paramètre `recipient` devient facultatif ; l’adresse personnelle reste le défaut.
La destination effective est validée avant réservation et incluse dans l’empreinte,
le To MIME et l’enveloppe SMTP. Les tâches planifiées peuvent utiliser ce champ.
Aucune nouvelle migration ou dépendance ; les anciens appels restent compatibles.
