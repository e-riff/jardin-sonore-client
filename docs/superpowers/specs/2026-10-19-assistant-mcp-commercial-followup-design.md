# MCP commercial : contexte, résumés et rappels

**État :** conception validée. Elle prolonge la [conception MCP IMAP du 17 octobre](2026-10-17-assistant-mcp-imap-suivi-dossiers-design.md). L'utilisateur a validé le 19 octobre les trois capacités et deux confirmations séparées ; il a choisi de confirmer les écritures dans Mistral.

## Objectif et périmètre

Depuis une conversation Mistral, l'utilisateur veut consulter un mail professionnel, retrouver le bon dossier commercial, lire son contexte et recevoir une proposition de résumé et de rappel. Il valide séparément l'ajout du résumé et celui du rappel avant que le backend ne les enregistre. Le backoffice affiche ensuite les éléments créés, qui restent modifiables par ses contrôles habituels.

Le lot ajoute la lecture du contexte d'un dossier et deux écritures ciblées. La recherche IMAP actuelle reste limitée à INBOX. La lecture des messages envoyés, les brouillons, l'envoi de mail et les séances sont hors de ce lot.

## Parcours utilisateur

1. Mistral utilise `search_mail`, `read_mail_thread` et `search_projects` pour réunir le mail et les dossiers candidats. En cas d'ambiguïté, il demande à l'utilisateur de choisir un dossier ; il ne rattache jamais silencieusement un mail à un candidat.
2. Mistral appelle `get_project_context` pour afficher le titre, l'organisation, le contact principal, l'état, les dernières notes du fil et les rappels ouverts du dossier choisi. Les collections sont bornées ; aucune pièce jointe ni aucun document du dossier n'est exposé.
3. Mistral propose un résumé avec sa source et, si utile, un rappel avec titre et date. L'utilisateur peut corriger le dossier, le texte ou la date dans la conversation avant l'appel d'écriture.
4. `record_mail_summary` et `create_project_reminder` sont deux outils distincts. Chacun exige une confirmation dans Mistral avant son exécution. Un refus ne modifie rien ; l'acceptation renvoie l'identifiant et l'URL du dossier ou un résultat indiquant qu'une écriture identique existe déjà.

## Choix d'intégration

Le backend Symfony MCP reste l'unique point d'accès aux entités commerciales ; les outils réutilisent les modèles existants `CommercialProjectEntity`, `CommercialEventEntity` et `CommercialActionEntity`. Aucun nouvel accès direct à la base n'est donné à Mistral. Le bearer MCP reste distinct de l'identifiant IMAP.

La confirmation a lieu dans Mistral, conformément au choix de l'utilisateur. Les deux outils d'écriture sont déclarés comme modifiant des données et leur permission dans le connecteur est réglée sur la demande de confirmation, tandis que les outils de lecture restent autorisés. Un réglage serveur laisse les écritures désactivées jusqu'à ce que cette permission soit vérifiée dans Mistral ; un appel prématuré échoue sans effet. Le backend ne peut pas déduire à partir du seul bearer qu'une personne a cliqué sur « Approuver » ; il applique donc aussi ses propres validations, limites et protections contre les doublons. Une file de propositions à approuver dans le backoffice a été écartée pour ce lot, car elle ajoute une seconde interface de validation.

## Contrats MCP

### `get_project_context`

Entrée : `projectId` entier positif provenant d'un candidat trouvé par `search_projects`. Sortie bornée : identifiant, titre, état, organisation, contact principal, au plus 10 événements de suivi récents utiles au contexte et 20 actions ouvertes avec leur échéance. Les événements techniques et les contenus intégraux des devis, factures, documents et mails n'y figurent pas. L'outil est en lecture seule.

### `record_mail_summary`

Entrée : `projectId`, `mailReference` renvoyée par `search_mail`, `summary` non vide (10 000 caractères au plus). Le backend vérifie l'existence du dossier et relit la référence IMAP pour récupérer la date, l'expéditeur, l'objet et une identité de source stable. Le texte proposé est conservé comme note dans le fil du dossier ; le contenu intégral du mail n'est pas copié dans le backoffice. La note présente sa provenance sans promettre un lien universel vers un client mail.

Une contrainte unique sur le couple dossier et identité de source empêche deux résumés du même mail dans le même dossier, y compris lors d'appels concurrents. Une répétition renvoie la note existante. L'identité utilise le compte, le `Message-ID` exploitable, puis un repli sur le dossier IMAP, `UIDVALIDITY` et l'UID. L'édition et la suppression manuelles de la note restent possibles dans le backoffice.

### `create_project_reminder`

Entrée : `projectId`, `mailReference`, `title` non vide (255 caractères au plus) et `dueOn` au format `YYYY-MM-DD`. L'outil crée une action ouverte liée au dossier, avec une date civile sans conversion de fuseau horaire. Il refuse un dossier fermé et une date invalide. Une contrainte unique par dossier et source de mail évite qu'une relance réseau crée deux rappels ; une répétition renvoie le rappel existant. Ce premier lot crée au plus un rappel par mail et par dossier. Le rappel reste modifiable, reportable, terminable ou annulable depuis le backoffice.

Les deux écritures sont indépendantes : l'utilisateur peut approuver l'une et refuser l'autre. Un échec du rappel après un résumé accepté ne retire pas ce résumé ; une nouvelle tentative sûre est possible.

## Affichage et données

Le résumé utilise un événement de type `note` avec des métadonnées de provenance et une clé de source dédiée, afin de conserver l'édition existante. Le fil affiche un libellé de source avec date, objet et expéditeur, traduit dans le domaine commercial. Le rappel utilise l'action commerciale existante ; la création d'action conserve le fonctionnement actuel du tableau de bord et du mail quotidien. Une migration Doctrine ajoute les clés de source et leurs contraintes uniques sans modifier les notes ni rappels historiques. Elle sera relue et exécutée en local après accord avant tout déploiement.

Les réponses MCP retournent les données nécessaires à la décision et des URL de dossier, sans contenu de message supplémentaire. Les erreurs de validation sont compréhensibles pour l'utilisateur ; les erreurs IMAP et base de données ne révèlent ni secrets ni contenu de mail.

## Vérification et livraison

- Tester la lecture bornée d'un dossier connu avec notes et rappels, puis un dossier absent.
- Tester une note issue d'un mail réel et sa provenance visible dans le fil ; vérifier qu'une seconde exécution ne crée pas de doublon.
- Tester séparément la création d'un rappel, sa date et son apparition dans le backoffice et le suivi quotidien ; vérifier les cas de doublon, dossier fermé et date invalide.
- Vérifier que les écritures sont désactivées par défaut, puis régler la confirmation de chaque outil dans Mistral avant de les activer. Tester qu'un refus ne crée rien et qu'une approbation crée exactement l'élément annoncé. Vérifier aussi qu'un mail consulté reste non lu dans le client IMAP.
- Contrôler le SQL, le `down()`, le diff de schéma et l'exécution locale de la migration avant livraison. Déployer selon les règles du dépôt après tests, commit, tag et poussée de la branche et du tag.

La suite indépendante concerne la lecture des messages envoyés et, plus tard, la préparation de brouillons IMAP ; elle demande de confirmer le dossier « Envoyés » et le dossier « Brouillons » du client mail utilisé.
