# Brouillons de réponse IMAP depuis le connecteur MCP

**État :** fonctionnalités de brouillon déployées et activées en production le 19 octobre 2026. Cette étape prolonge la [conception MCP IMAP](2026-10-17-assistant-mcp-imap-suivi-dossiers-design.md) et les [écritures commerciales](2026-10-19-assistant-mcp-commercial-followup-design.md).

## Intention et périmètre

Depuis Mistral, l'utilisateur prépare des réponses et des messages libres. Ils sont enregistrés comme brouillons dans le compte IMAP professionnel cPanel et peuvent être relus ou corrigés dans Thunderbird ou Roundcube. Le MCP ne possède aucun outil d'envoi.

Le lot inclut la recherche bornée de réponses envoyées, la création idempotente d'une réponse (`save_mail_draft`), la lecture d'un brouillon lié à sa source (`read_mail_draft`), son remplacement après confirmation (`update_mail_draft`) et la composition libre à destinataire et objet explicites (`create_mail_draft`). Un mail de formulaire envoyé depuis `contact@jardin-sonore.fr` peut recevoir une réponse si son `Reply-To` unique est externe et valide.

Le backoffice reste la référence des dossiers commerciaux et des rappels. Les messages reçus sont recherchés dans `INBOX`. Les pièces jointes ne sont pas gérées par le MCP ; leur ajout se fait manuellement dans le client mail en attendant un cadrage séparé.

## Parcours

1. Pour répondre, Mistral cherche et lit le mail, identifie un dossier commercial si utile, puis propose le texte. Le serveur recalcule le destinataire et l'objet depuis le mail source.
2. L'utilisateur confirme séparément la création ou le remplacement dans Mistral, réglé sur « Demander ». Une réponse déjà envoyée, une source ambiguë ou un destinataire invalide empêche la création.
3. Pour un message sans mail source, Mistral propose le destinataire, l'objet et le texte ; `create_mail_draft` les enregistre tels quels après confirmation.
4. L'utilisateur ouvre le brouillon dans Thunderbird ou Roundcube, le relit, peut l'éditer et l'envoie lui-même.

Une demande répétée pour la même source retrouve le brouillon initial sans écraser les modifications faites dans le client mail. Les entrées identiques de la composition libre retrouvent le brouillon par identifiant déterministe. `update_mail_draft` est réservé aux brouillons MCP rattachés à une source : il exige l'identifiant et la révision courante, vérifie le remplacement avant de retirer l'ancien et ne transmet jamais le message.

## Dossiers IMAP et clients

Les chemins configurés sont `INBOX.Drafts` et `INBOX.Sent`, vérifiés sur le serveur cPanel. Le serveur ne crée pas de dossier manquant. Un brouillon manuel enregistré depuis Thunderbird mobile est apparu dans Roundcube, confirmant le partage du dossier IMAP ; les clients doivent viser ces dossiers du compte, pas un dossier local.

`search_mail` reste limité à `INBOX`. `find_sent_replies` recherche au plus trois réponses liées au mail source par `Message-ID`, `In-Reply-To` et `References`. L'absence de correspondance ne prouve pas qu'aucune réponse n'existe.

## Contrats d'écriture

### `save_mail_draft`

Entrée : `mailReference` d'un message et corps texte non vide, limité à 10 000 caractères. Le serveur choisit une seule adresse depuis un `Reply-To` valide, ou `From` si le champ est absent. Un `Reply-To` malformé ou multiple est refusé. Les messages provenant de la boîte elle-même ne sont acceptés que si un `Reply-To` unique et externe permet une réponse, comme pour le formulaire du site. Le serveur fixe `From`, `To` et `Re:` ; le modèle ne fournit ni destinataire ni en-têtes.

Le message MIME est en `text/plain` UTF-8 avec identifiant propre et liens de réponse valides. La clé unique en base et le `Message-ID` déterministe réconcilient les appels répétés ou les retours IMAP incertains. Aucun brouillon édité dans Thunderbird n'est remplacé silencieusement.

### `read_mail_draft` et `update_mail_draft`

La lecture retourne le brouillon MCP connu et sa révision, sans parcourir arbitrairement tout le dossier Brouillons. La mise à jour reçoit la référence source, l'identifiant du brouillon, sa révision et le texte complet proposé. Elle refuse toute révision périmée. Le nouvel append est vérifié avant la suppression ciblée de l'ancien via `UID EXPUNGE` ; l'état persistant permet de réconcilier un remplacement incertain. Les brouillons libres non liés à une source ne sont pas éditables par ces outils pour l'instant.

### `create_mail_draft`

Entrée : adresse `recipient`, `subject` et `body`, fournis explicitement et chacun validé/limité côté serveur. L'outil crée un MIME texte indépendant de tout mail source ; il ne prend ni fichier, ni chemin local, ni URL. Une empreinte déterministe évite les doublons sur appels identiques. La réponse incertaine est signalée, sans envoi.

## Permissions et production

Les trois outils d'écriture sont des outils « Interactif » dans Mistral et doivent rester réglés sur « Demander ». Le texte reçu par mail est une donnée non fiable : il ne peut pas autoriser une écriture ni changer un destinataire.

Les verrous serveur `MCP_DRAFT_WRITE_ENABLED`, `MCP_DRAFT_EDIT_ENABLED` et `MCP_DRAFT_COMPOSE_ENABLED` sont distincts et désactivés par défaut. Les trois sont actifs en production depuis le 19 octobre après confirmation des permissions Mistral. `create_mail_draft` a été activé à la demande de l'utilisateur. Le contrôle de production a confirmé les 11 outils et rejeté un corps vide avant toute écriture ; aucun brouillon de contrôle n'a été créé.

Les livraisons et migrations sont listées dans la [roadmap](../../../ROADMAP.md). Le bearer MCP et les identifiants IMAP restent des secrets serveur.

## Pièces jointes — piste à cadrer

Pour les besoins actuels, le parcours recommandé est que le MCP prépare seulement le texte, puis que l'utilisateur ajoute le fichier dans Thunderbird ou Roundcube avant d'envoyer. Cela couvre aussi bien un document local que des pièces jointes de mail, sans ouvrir les fichiers ou le stockage à l'IA.

Si l'ajout automatique devient utile, commencer par copier au brouillon une ou plusieurs pièces jointes déjà présentes sur le mail source choisi. Le MCP pourrait d'abord retourner leurs noms, types et tailles ; l'utilisateur sélectionnerait précisément celles à copier. Le serveur lirait les octets depuis IMAP et n'accepterait ni chemin arbitraire, ni URL, ni fichier choisi implicitement par le modèle. Il faudrait définir et tester les limites cumulées de nombre et de taille, les types MIME, les noms de fichier, les doublons et la reprise idempotente. L'upload d'un fichier local au MCP est une autre étape, avec un canal et des règles de conservation propres.

## Vérifications restantes

- Dans Mistral, vérifier « Demander » pour `Save Mail Draft`, `Read Mail Draft`, `Update Mail Draft` et `Create Mail Draft`.
- Créer un brouillon de réponse sur un mail entrant, puis vérifier son édition dans Thunderbird/Roundcube sans l'envoyer.
- Tester une réponse à un mail du formulaire avec `Reply-To`, puis créer un brouillon libre ; confirmer leur présence dans le dossier partagé et l'absence d'envoi.
- Garder l'ajout de pièce jointe manuel jusqu'à validation d'un design et de limites dédiées.
