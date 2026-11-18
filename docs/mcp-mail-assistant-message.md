# Message à transmettre à l’IA connectée au MCP

Le connecteur MCP Jardin Sonore prend en charge les pièces jointes et les
brouillons « répondre à tous ». Utilise le schéma actuel des outils ; s’il manque
ces paramètres, demande un rafraîchissement des fonctions du connecteur.

- **Créer un mail en brouillon** : `create_mail_draft`, avec `attachments` facultatif.
- **Créer une réponse en brouillon** : `save_mail_draft`, avec la `mailReference`
  exacte, `body`, éventuellement `htmlBody`, `attachments` et **`replyAll: true`**
  pour répondre à tous. Les To/Cc du mail source sont repris, sans notre boîte ni
  doublons. Cet outil ne modifie pas un brouillon déjà créé pour ce mail source.
- **Ajouter ou retirer des fichiers dans n’importe quel brouillon** : le retrouver
  avec `search_mail_drafts`, le lire avec `read_standalone_mail_draft`, puis appeler
  `update_standalone_mail_draft` avec le `draftMessageId` et la `revision` courants.
  `attachments` ajoute des fichiers ; `removeAttachmentIds` retire uniquement les
  identifiants exacts retournés à la lecture. Les champs omis sont conservés et
  `attachments: []` n’efface rien. `update_mail_draft` accepte aussi ces paramètres
  sur une réponse liée à un mail source, avec `mailReference` et `body` obligatoires.
- **Envoyer directement une notification autorisée** : `send_personal_notification`
  accepte aussi `attachments`. Garde le même `requestId` et le même contenu lors
  d’une relance. Un changement de fichier avec la même clé produit un conflit ;
  après `sending` ou `uncertain`, ne change pas la clé pour forcer un nouvel envoi.

Chaque fichier suit ce format :

```json
{"filename": "devis.pdf", "mimeType": "application/pdf", "base64": "<contenu réel du fichier encodé en base64>"}
```

Limites : **10 fichiers, 5 Mio par fichier, 10 Mio au total**. Aucun chemin serveur
ni URL de fichier n’est accepté. N’invente pas le contenu base64 ou les identifiants.
Après une édition réussie, utilise les nouveaux identifiants et la nouvelle révision
retournés. Après conflit, relis le brouillon ; après interruption, reprends exactement
la même demande. Les outils de brouillon n’envoient aucun mail et conservent leurs
règles de confirmation. Le retrait d’un fichier concerne le brouillon.
