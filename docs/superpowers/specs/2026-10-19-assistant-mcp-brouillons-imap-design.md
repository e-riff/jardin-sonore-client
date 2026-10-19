# Brouillons de réponse IMAP depuis le connecteur MCP

**État :** conception présentée dans la conversation et approuvée par l'utilisateur le 19 octobre 2026 ; document à relire avant le plan d'implémentation. Cette étape prolonge la [conception MCP IMAP](2026-10-17-assistant-mcp-imap-suivi-dossiers-design.md) et les [écritures commerciales](2026-10-19-assistant-mcp-commercial-followup-design.md).

## Intention et périmètre

L'utilisateur consulte la boîte professionnelle cPanel dans Thunderbird sur son téléphone et envisage Thunderbird sur son PC. Roundcube reste un accès de secours au même compte IMAP. Depuis Mistral, il veut préparer une réponse à un mail reçu, contrôler son destinataire, son objet et son texte, puis enregistrer un brouillon visible dans ces clients. L'envoi reste exclusivement manuel dans le client mail.

Le lot ajoute une lecture limitée des messages envoyés pour contextualiser les réponses, puis un seul outil d'écriture `save_mail_draft`. Il ne déplace, ne supprime et n'envoie aucun message. Il n'ajoute aucune gestion des dossiers IMAP par l'IA. Les pièces jointes, les destinataires multiples, les transferts et la modification de brouillons existants sont hors périmètre initial.

Le backoffice reste la référence des dossiers commerciaux et des rappels. La boîte de réception reste le point de recherche des mails reçus tant que `search_mail` ne sait lire qu'`INBOX`. L'utilisateur peut se servir des dossiers système IMAP après vérification de leurs noms réels (`Drafts`, `Sent`, `Archive`, `Junk`, `Trash` sont les noms habituels de Roundcube) et envisager « Administratif » ; aucun déplacement automatique des échanges clients n'est prévu dans ce lot.

## Parcours

1. Mistral cherche le mail reçu, lit son contenu et, si nécessaire, vérifie les messages envoyés correspondants. Il propose une réponse dans la conversation en montrant le destinataire calculé, l'objet et le texte. Il signale explicitement une réponse déjà envoyée ou une conversation ambiguë.
2. L'utilisateur corrige le texte puis approuve séparément l'appel à `save_mail_draft` dans Mistral. Un refus ne crée aucun brouillon.
3. Le serveur relit le mail source, recalcule le destinataire et l'objet, puis ajoute le message au dossier IMAP Brouillons. Il retourne le dossier utilisé et l'identifiant du brouillon, sans envoyer le mail.
4. L'utilisateur ouvre le brouillon dans Thunderbird ou Roundcube, le relit, l'édite si besoin et l'envoie lui-même. Une demande répétée pour le même mail source retrouve le brouillon créé ; elle ne remplace pas ses modifications.

## Dossiers IMAP et clients

Le dossier Brouillons est une configuration serveur explicite (`MCP_IMAP_DRAFTS_FOLDER`) fondée sur le nom IMAP réellement utilisé par Roundcube et Thunderbird. Le dossier Envoyés est configuré séparément (`MCP_IMAP_SENT_FOLDER`). Le serveur vérifie leur existence et échoue sans création si l'un des chemins requis est absent ; il ne crée pas de dossier nommé approximativement. Un essai manuel préalable consiste à enregistrer un brouillon Thunderbird sur le téléphone et à vérifier qu'il apparaît dans Roundcube, puis dans Thunderbird sur PC. Les copies de messages envoyés et les brouillons des deux Thunderbird doivent viser ces dossiers du compte IMAP, et non les dossiers locaux.

La lecture des messages envoyés est bornée et en lecture seule. Elle permet de confirmer si une réponse à la source a déjà été envoyée, en utilisant `Message-ID`, `In-Reply-To` et `References` lorsqu'ils sont valides. Une absence de correspondance n'est pas présentée comme une preuve qu'aucune réponse n'existe. Les messages reçus restent recherchés dans `INBOX` pour cette version.

L'outil de lecture `find_sent_replies` accepte une `mailReference` de `search_mail`. Il recherche dans le dossier Envoyés configuré au plus trois réponses liées au `Message-ID` de la source, et renvoie pour chacune date, destinataire, objet et texte borné. Si la source n'a pas de `Message-ID` exploitable, il retourne un état « correspondance impossible » au lieu d'affirmer qu'aucune réponse n'a été envoyée. Il n'effectue aucune recherche libre sur tout le dossier Envoyés.

## Contrat de `save_mail_draft`

L'outil accepte la `mailReference` d'un message reçu et un `body` texte non vide de 10 000 caractères au plus. Il ne prend ni adresse destinataire libre, ni en-têtes bruts, ni pièce jointe. Le serveur choisit une seule adresse de réponse à partir du `Reply-To` valide, sinon du `From` valide, et refuse un mail envoyé par la boîte elle-même ou une adresse de réponse ambiguë. Le serveur construit `From` avec l'adresse professionnelle configurée, `To` avec l'adresse vérifiée et `Subject` avec `Re:` sans le dupliquer. Mistral présente ces valeurs avant la confirmation ; le serveur les recalcule lors de l'écriture.

Le message stocké est un MIME `text/plain` UTF-8 avec en-têtes encodés et dates conformes, `Message-ID` propre, ainsi que `In-Reply-To` et `References` lorsque l'identifiant source est exploitable. Les retours de ligne et le texte sont échappés pour empêcher l'injection d'en-têtes. L'ajout IMAP utilise le drapeau `\Draft` et vérifie que le brouillon est retrouvable dans le dossier cible. Aucun outil SMTP ou d'envoi n'est exposé au MCP.

Une clé stable par boîte et mail source rend l'opération idempotente. Le brouillon porte aussi une marque technique permettant de le retrouver après une réponse IMAP incertaine ; une contrainte unique en base coordonne les appels concurrents. Si l'enregistrement IMAP réussit mais que la réponse réseau échoue, la relance recherche d'abord le brouillon marqué avant tout nouvel ajout. L'outil ne modifie jamais un brouillon déjà édité dans Thunderbird. Si ce brouillon a été supprimé ou envoyé, une relance renvoie l'état connu et demande une nouvelle décision utilisateur plutôt que de le recréer silencieusement.

## Autorisations et livraison progressive

`save_mail_draft` est déclaré comme outil d'écriture et doit rester sur « Demander » dans Mistral. Le texte d'un mail reçu reste une donnée non fiable : il ne peut pas autoriser une création ou changer le destinataire. Un drapeau dédié `MCP_DRAFT_WRITE_ENABLED`, désactivé par défaut, permet de livrer le code sans activer l'écriture avant la recette des dossiers et de la confirmation. Le bearer MCP et les identifiants IMAP restent des secrets serveur non affichés dans les réponses ou les journaux.

Le serveur vérifie la taille du message et du brouillon, l'existence du dossier, l'adresse calculée et les métadonnées de réponse. Les erreurs retournées distinguent validation, dossier absent et échec IMAP sans exposer le contenu du mail ni les secrets. Les écritures commerciales déjà actives gardent leur réglage indépendant.

## Vérification

- Confirmer les chemins réels des dossiers Envoyés et Brouillons de la boîte cPanel et la synchronisation d'un brouillon entre téléphone, PC et Roundcube.
- Tester les adresses `Reply-To` et `From`, les mails auto-adressés par le formulaire du site, les sujets déjà préfixés `Re:`, les caractères accentués et les en-têtes malformés.
- Tester un refus dans Mistral, puis un brouillon approuvé ; vérifier son destinataire, son texte, son rattachement au fil et l'absence d'envoi.
- Répéter l'appel après succès et après une réponse IMAP incertaine pour vérifier l'absence de doublons ; vérifier qu'un brouillon édité manuellement n'est pas écrasé.
- Vérifier les refus par défaut, les limites de taille, les erreurs de dossier, les dates et les messages envoyés existants. Relire toute migration, son `down()`, le SQL généré et le diff de schéma avant déploiement.

La publication suit les règles du dépôt : tests, commit, tag et poussée avant déploiement. L'activation du drapeau dédié et la recette réelle dans Mistral viennent après la vérification des dossiers et de la permission « Demander ».
