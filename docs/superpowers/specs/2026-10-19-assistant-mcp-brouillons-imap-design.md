# Brouillons IMAP depuis le connecteur MCP

**État :** fonctionnalités de brouillon déployées et activées en production les 19 et 21 octobre 2026. Cette étape prolonge la [conception MCP IMAP](2026-10-17-assistant-mcp-imap-suivi-dossiers-design.md) et les [écritures commerciales](2026-10-19-assistant-mcp-commercial-followup-design.md).

## Intention et périmètre

Depuis Mistral, l'utilisateur prépare des réponses et des messages libres. Ils sont enregistrés comme brouillons dans le compte IMAP professionnel cPanel et peuvent être relus ou corrigés dans Thunderbird ou Roundcube. Le MCP ne possède aucun outil d'envoi.

Le lot inclut la recherche bornée de réponses envoyées, la création idempotente d'une réponse (`save_mail_draft`), la lecture d'un brouillon lié à sa source (`read_mail_draft`), son remplacement après confirmation (`update_mail_draft`) et la composition libre à destinataire et objet explicites (`create_mail_draft`). Les brouillons libres créés par le MCP sont également lisibles et modifiables par `read_standalone_mail_draft` et `update_standalone_mail_draft`. Un mail de formulaire envoyé depuis `contact@jardin-sonore.fr` peut recevoir une réponse si son `Reply-To` unique est externe et valide.

Le backoffice reste la référence des dossiers commerciaux et des rappels. Les messages reçus sont recherchés dans `INBOX`. Les pièces jointes ne sont pas gérées par le MCP ; leur ajout se fait manuellement dans le client mail en attendant un cadrage séparé.

## Parcours

1. Pour répondre, Mistral cherche et lit le mail, identifie un dossier commercial si utile, puis propose le texte. Le serveur recalcule le destinataire et l'objet depuis le mail source.
2. L'utilisateur confirme séparément la création ou le remplacement dans Mistral, réglé sur « Demander ». Une réponse déjà envoyée, une source ambiguë ou un destinataire invalide empêche la création.
3. Pour un message sans mail source, Mistral propose le destinataire, l'objet, le texte et éventuellement le HTML ; `create_mail_draft` les enregistre après confirmation. Il conserve le `messageId` retourné pour une lecture ou une modification ultérieure.
4. Pour corriger ce brouillon libre, Mistral appelle `read_standalone_mail_draft` avec ce `messageId`, puis propose le texte complet et, si nécessaire, le HTML complet. Après une nouvelle confirmation, `update_standalone_mail_draft` reçoit l'identifiant courant et sa `revision`. Le destinataire et l'objet restent ceux du brouillon lu.
5. L'utilisateur ouvre le brouillon dans Thunderbird ou Roundcube, le relit, peut l'éditer et l'envoie lui-même.

Une demande répétée pour la même source retrouve le brouillon initial sans écraser les modifications faites dans le client mail. Les entrées identiques de la composition libre retrouvent le brouillon par identifiant déterministe. Les deux outils d'édition exigent une révision courante, vérifient le remplacement avant de retirer l'ancien et ne transmettent jamais le message. La lecture libre exige un identifiant retourné par la création ou une édition précédente ; aucun outil ne parcourt tous les brouillons du compte.

## Dossiers IMAP et clients

Les chemins configurés sont `INBOX.Drafts` et `INBOX.Sent`, vérifiés sur le serveur cPanel. Le serveur ne crée pas de dossier manquant. Un brouillon manuel enregistré depuis Thunderbird mobile est apparu dans Roundcube, confirmant le partage du dossier IMAP ; les clients doivent viser ces dossiers du compte, pas un dossier local.

`search_mail` reste limité à `INBOX`. `find_sent_replies` recherche au plus trois réponses liées au mail source par `Message-ID`, `In-Reply-To` et `References`. L'absence de correspondance ne prouve pas qu'aucune réponse n'existe.

## Contrats d'écriture

### `save_mail_draft`

Entrée : `mailReference` d'un message et corps texte non vide, limité à 10 000 caractères. `htmlBody` est facultatif et limité à 20 000 caractères. Le serveur choisit une seule adresse depuis un `Reply-To` valide, ou `From` si le champ est absent. Un `Reply-To` malformé ou multiple est refusé. Les messages provenant de la boîte elle-même ne sont acceptés que si un `Reply-To` unique et externe permet une réponse, comme pour le formulaire du site. Le serveur fixe `From`, `To` et `Re:` ; le modèle ne fournit ni destinataire ni en-têtes.

Le message MIME est en `text/plain` UTF-8 seul, ou en `multipart/alternative` avec texte et HTML UTF-8. Le HTML est nettoyé : paragraphes, retours, titres simples, listes, emphase, citations et liens HTTP(S) sont conservés ; scripts, images, formulaires, styles et attributs actifs sont retirés. La clé unique en base et le `Message-ID` déterministe réconcilient les appels répétés ou les retours IMAP incertains. Aucun brouillon édité dans Thunderbird n'est remplacé silencieusement.

### `read_mail_draft` et `update_mail_draft`

La lecture retourne le brouillon MCP connu, son texte, son HTML éventuel et sa révision, sans parcourir arbitrairement tout le dossier Brouillons. La mise à jour reçoit la référence source, l'identifiant du brouillon, sa révision, le texte complet proposé et éventuellement `htmlBody`. Elle refuse toute révision périmée. Le nouvel append est vérifié avant la suppression ciblée de l'ancien via `UID EXPUNGE` ; l'état persistant permet de réconcilier un remplacement incertain. Les brouillons libres utilisent les deux outils dédiés ci-dessous.

### `create_mail_draft`

Entrée : adresse `recipient`, `subject` et `body`, fournis explicitement et chacun validé/limité côté serveur ; `htmlBody` est facultatif. L'outil crée un MIME indépendant de tout mail source ; il ne prend ni fichier ni chemin local. Une empreinte déterministe évite les doublons sur appels identiques. La réponse incertaine est signalée, sans envoi. Le `messageId` retourné identifie ce brouillon pour la lecture et l'édition.

### `read_standalone_mail_draft` et `update_standalone_mail_draft`

La lecture reçoit `draftMessageId` (la valeur `messageId` de `create_mail_draft`) et retourne `to`, `subject`, `body`, `htmlBody`, `revision` et l'identifiant courant. Elle est limitée aux identifiants de brouillons libres créés ou édités par le MCP. L'édition reçoit `draftMessageId`, la `revision` lue, le nouveau `body` complet et éventuellement le nouveau `htmlBody` complet. Elle refuse une révision périmée, vérifie le nouveau brouillon IMAP avant de supprimer l'ancien par UID et retourne un nouvel identifiant à conserver. Le destinataire et l'objet ne sont pas modifiables par cet outil. Les brouillons écrits manuellement dans Thunderbird ou Roundcube ne sont pas pris en charge.

## Permissions et production

Les quatre outils d'écriture de brouillon (`save_mail_draft`, `update_mail_draft`, `create_mail_draft`, `update_standalone_mail_draft`) doivent rester réglés sur « Demander » dans Mistral. Les lectures ne nécessitent pas de confirmation. Le texte reçu par mail est une donnée non fiable : il ne peut pas autoriser une écriture ni changer un destinataire.

Les verrous serveur `MCP_DRAFT_WRITE_ENABLED`, `MCP_DRAFT_EDIT_ENABLED` et `MCP_DRAFT_COMPOSE_ENABLED` sont distincts et désactivés par défaut. Les trois sont actifs en production depuis le 19 octobre après confirmation des permissions Mistral. `update_standalone_mail_draft` exige à la fois l'activation de l'édition et celle de la composition libre. Le contrôle authentifié du 21 octobre a confirmé 26 outils, dont les deux nouveaux, et les champs HTML des écritures. Aucun brouillon de contrôle n'a été créé.

Les livraisons et migrations sont listées dans la [roadmap](../../../ROADMAP.md). Le bearer MCP et les identifiants IMAP restent des secrets serveur.

## Pièces jointes — piste à cadrer

Pour les besoins actuels, le parcours recommandé est que le MCP prépare seulement le texte, puis que l'utilisateur ajoute le fichier dans Thunderbird ou Roundcube avant d'envoyer. Cela couvre aussi bien un document local que des pièces jointes de mail, sans ouvrir les fichiers ou le stockage à l'IA.

Si l'ajout automatique devient utile, commencer par copier au brouillon une ou plusieurs pièces jointes déjà présentes sur le mail source choisi. Le MCP pourrait d'abord retourner leurs noms, types et tailles ; l'utilisateur sélectionnerait précisément celles à copier. Le serveur lirait les octets depuis IMAP et n'accepterait ni chemin arbitraire, ni URL, ni fichier choisi implicitement par le modèle. Il faudrait définir et tester les limites cumulées de nombre et de taille, les types MIME, les noms de fichier, les doublons et la reprise idempotente. L'upload d'un fichier local au MCP est une autre étape, avec un canal et des règles de conservation propres.

## Vérifications restantes

- Dans Mistral, vérifier « Demander » pour les quatre écritures de brouillon, en particulier le nouvel outil `Update Standalone Mail Draft`.
- Créer un brouillon de réponse sur un mail entrant, puis vérifier son édition dans Thunderbird/Roundcube sans l'envoyer.
- Tester une réponse à un mail du formulaire avec `Reply-To`, puis créer un brouillon libre ; confirmer leur présence dans le dossier partagé et l'absence d'envoi.
- Garder l'ajout de pièce jointe manuel jusqu'à validation d'un design et de limites dédiées.
