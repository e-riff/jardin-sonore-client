# Changements du 21 octobre 2026 à transmettre au connecteur MCP

## Dossiers, calendrier et tâches

- Les séances en option portent `[OPT]` dans Google Calendar. Quand une séance passe de « option » à « validée », le marqueur est retiré ; le passage inverse le rétablit. Depuis un dossier, l'utilisateur peut confirmer ou rejeter en une seule action toutes les séances en option.
- Le fil du dossier masque les notes automatiques de confirmation et d'annulation des séances pour laisser visibles les échanges utiles. Les retours à la ligne du texte des notes sont affichés normalement.
- Les tâches peuvent avoir une heure indicative, sans réservation dans Google Calendar. Les rendez-vous de dossier sont distincts des séances : ils sont créés, déplacés ou annulés dans le calendrier personnel sans inviter automatiquement un contact. Leur résumé reste dans le dossier.
- `get_project_context` fournit les identifiants et révisions des tâches ; `get_project_appointment` lit le détail et la révision d'un rendez-vous précis. Les commandes MCP `create_project_task`, `update_project_task`, `complete_project_task`, `create_project_appointment`, `update_project_appointment`, `cancel_project_appointment` et `set_project_appointment_summary` existent, mais **les écritures de tâches et rendez-vous sont désactivées côté serveur** jusqu'à la validation de leur réglage « Demander » dans Mistral. Ne pas annoncer une création ou modification comme effectuée si l'outil retourne `Project schedule writes are disabled.`
- Pour un nouveau rendez-vous, `create_project_appointment` exige un `projectId` exact, un `requestId` hexadécimal stable de 32 caractères, un titre et `startsAt`/`endsAt`. Les modifications et annulations exigent l'identifiant exact et la révision fournie par `get_project_appointment`.

## Brouillons de mail

- `create_mail_draft`, `save_mail_draft` et `update_mail_draft` acceptent désormais `htmlBody` facultatif, en plus du `body` texte obligatoire. Le brouillon contient alors les versions texte et HTML. Le HTML accepte une mise en forme simple, des listes et des liens HTTP(S) ; le serveur retire les scripts, styles, images et attributs actifs. Aucun de ces outils n'envoie le mail.
- Un brouillon libre créé par `create_mail_draft` peut être relu avec `read_standalone_mail_draft` en passant dans `draftMessageId` le `messageId` retourné lors de la création. La lecture fournit son texte, son HTML éventuel, son destinataire, son objet et sa `revision`.
- Pour le corriger, utiliser `update_standalone_mail_draft` avec le `draftMessageId` et la `revision` courants, le nouveau `body` complet et éventuellement le nouveau `htmlBody` complet. L'outil retourne un **nouvel identifiant à conserver**. Le destinataire et l'objet sont conservés ; en cas de conflit, relire le brouillon avant de proposer une autre modification.
- L'édition libre ne concerne que les brouillons créés par le MCP dont l'identifiant est connu. Elle ne cherche pas tous les brouillons du compte et ne modifie pas un brouillon créé manuellement dans Thunderbird ou Roundcube. Les pièces jointes restent à ajouter dans le client mail.
- Les outils d'écriture de brouillon doivent rester sur « Demander » dans Mistral, notamment le nouvel `update_standalone_mail_draft`. Les trois verrous serveur de brouillon sont actifs ; le nouvel outil et `read_standalone_mail_draft` ont été vérifiés dans les 26 outils exposés en production. Une création et une édition réelles dans le client IMAP restent à vérifier.

La [roadmap](../ROADMAP.md) porte l'état de livraison et la [documentation des brouillons](superpowers/specs/2026-10-19-assistant-mcp-brouillons-imap-design.md) détaille leurs contrats.
