# Notes de dossier via MCP — conception

## But

Permettre à l'assistant d'ajouter au fil d'un dossier une note libre avec mise en forme simple, après confirmation explicite de l'utilisateur. Continuer à renseigner les comptes rendus de rendez-vous téléphoniques avec l'outil existant `set_project_appointment_summary`.

## Deux emplacements existants

- `set_project_appointment_summary` met à jour le champ résumé d'un rendez-vous précis, avec ID de dossier, ID de rendez-vous et révision. C'est le parcours qui a déjà servi pour des comptes rendus téléphoniques ; il reste disponible avec les mêmes paramètres et la même demande de confirmation.
- Le fil du dossier contient des événements `note`. Le backoffice y accepte déjà une mise en forme HTML limitée et affiche ces notes via `commercial_timeline_rich_text`. `record_mail_summary` y crée une note provenant d'un mail vérifié ; il n'est pas adapté à une note libre sans mail.

## Nouvel outil

`create_project_note` reçoit un `projectId` exact, un contenu de 1 à 10 000 caractères, une date facultative et un `requestId` stable pour éviter les doublons lors d'un nouvel appel. Il crée un événement `note` dans le dossier, sans mail source ni tâche implicite. Il renvoie l'ID de la note et l'URL du dossier. Le projet doit avoir été choisi via `search_projects` puis relu avec `get_project_context` ; l'assistant ne déduit pas l'ID depuis un nom seul.

Le contenu accepte du texte simple et les mêmes balises sûres que le fil actuel : gras, italique, saut de ligne, lien HTTP(S) et les trois couleurs autorisées. Le backend normalise ce contenu avec le formateur existant avant enregistrement ; scripts, images, styles arbitraires et protocoles de lien dangereux sont retirés. Une note vide après normalisation est refusée. Le texte reste modifiable dans le backoffice.

Les résumés de rendez-vous restent en texte simple dans ce lot, car leur carte et leur formulaire actuel les affichent comme tels. Pour obtenir une note **mise en forme** après un appel, l'assistant crée une note dans le fil et peut continuer à renseigner le résumé du rendez-vous si l'utilisateur le demande. Il décrit clairement où chaque texte sera visible.

## Autorisation et confirmation

L'écriture réutilise le verrou serveur `MCP_WRITE_ENABLED`. La description de `create_project_note` demande une confirmation explicite de l'utilisateur avant chaque appel et l'outil est réglé sur « Demander » dans le connecteur. Le jeton Bearer seul ne constitue pas une preuve de confirmation ; l'approbation par appel appartient au client MCP.

## Comportements à vérifier

- Créer une note sur le dossier exact sans mail source ; la retrouver dans le fil et dans le contexte MCP récent.
- Rejouer le même `requestId` sans créer de doublon ; refuser sa réutilisation pour un autre dossier ou un autre contenu.
- Afficher gras, italique et liens sûrs ; retirer les balises et URL dangereuses.
- Refuser l'écriture quand `MCP_WRITE_ENABLED` est désactivé et vérifier la confirmation côté connecteur.
- Conserver le fonctionnement actuel de `set_project_appointment_summary`.
