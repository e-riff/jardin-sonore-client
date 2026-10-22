# Notes de dossier via MCP — conception

## But

Permettre à l'assistant d'ajouter au fil d'un dossier une note libre avec mise en forme simple, après confirmation explicite de l'utilisateur. Continuer à renseigner les comptes rendus de rendez-vous téléphoniques avec l'outil existant `set_project_appointment_summary`.

## Deux emplacements existants

- `set_project_appointment_summary` met à jour le champ résumé d'un rendez-vous précis, avec ID de dossier, ID de rendez-vous et révision. C'est le parcours qui a déjà servi pour des comptes rendus téléphoniques ; il reste disponible avec les mêmes paramètres et la même demande de confirmation.
- Le fil du dossier contient des événements `note`. Le backoffice y accepte déjà une mise en forme HTML limitée et affiche ces notes via `commercial_timeline_rich_text`. `record_mail_summary` y crée une note provenant d'un mail vérifié ; il n'est pas adapté à une note libre sans mail.

## Nouvel outil

`create_project_note` reçoit un `projectId` exact, un contenu de 1 à 10 000 caractères, une date facultative et un `requestId` stable pour éviter les doublons lors d'un nouvel appel. Il crée un événement `note` dans le dossier, sans mail source ni tâche implicite. Il renvoie l'ID de la note et l'URL du dossier. Le projet doit avoir été choisi via `search_projects` puis relu avec `get_project_context` ; l'assistant ne déduit pas l'ID depuis un nom seul.

Le champ `content` accepte du texte simple ou un fragment HTML limité ; **il ne parse pas le Markdown**. Envoyer `**important**` affiche les astérisques. Pour la mise en forme, utiliser `<strong>important</strong>` pour le gras, `<em>nuance</em>` pour l'italique, `<br>` pour un retour à la ligne et `<br><br>` pour séparer deux paragraphes. Les liens doivent être des ancres HTML avec une URL absolue `http://` ou `https://`, par exemple `<a href="https://example.org">consulter la ressource</a>`. Les couleurs sont facultatives et limitées à `<span style="color:#A64D43">…</span>`, `#692019` ou `#47664B`. Les balises `<b>` et `<i>` sont également normalisées en gras et italique.

Exemple de valeur à envoyer dans `content` :

```html
<strong>Compte rendu d’appel</strong><br><br>Échange avec Marie au sujet du projet.<br><em>À relancer la semaine prochaine.</em><br><a href="https://example.org/dossier">Document partagé</a>
```

Ne pas envoyer de syntaxe Markdown (`**gras**`, `*italique*`, titres `#`, listes Markdown ou liens `[texte](URL)`) en espérant qu'elle soit convertie. Le formateur ne convertit pas ces conventions et peut les afficher telles quelles. Les retours à la ligne dans du texte brut sont convertis en `<br>`. Le backend normalise le contenu avant enregistrement ; scripts, images, styles arbitraires et protocoles de lien dangereux sont retirés. Une note vide après normalisation est refusée. Le texte reste modifiable dans le backoffice.

Les consignes prêtes à copier dans les instructions du connecteur Vibes sont maintenues dans le [guide de formatage MCP](../../mcp-connecteur-formatage-notes.md). La description de l'outil `create_project_note` reprend aussi les balises et l'avertissement contre le Markdown pour être visible dès la découverte des outils.

Les résumés de rendez-vous restent en texte simple dans ce lot, car leur carte et leur formulaire actuel les affichent comme tels. Pour obtenir une note **mise en forme** après un appel, l'assistant crée une note dans le fil et peut continuer à renseigner le résumé du rendez-vous si l'utilisateur le demande. Il décrit clairement où chaque texte sera visible.

## Autorisation et confirmation

L'écriture réutilise le verrou serveur `MCP_WRITE_ENABLED`. La description de `create_project_note` demande une confirmation explicite de l'utilisateur avant chaque appel et l'outil est réglé sur « Demander » dans le connecteur. Le jeton Bearer seul ne constitue pas une preuve de confirmation ; l'approbation par appel appartient au client MCP.

## Comportements à vérifier

- Créer une note sur le dossier exact sans mail source ; la retrouver dans le fil et dans le contexte MCP récent.
- Rejouer le même `requestId` sans créer de doublon ; refuser sa réutilisation pour un autre dossier ou un autre contenu.
- Afficher gras, italique et liens sûrs ; retirer les balises et URL dangereuses.
- Refuser l'écriture quand `MCP_WRITE_ENABLED` est désactivé et vérifier la confirmation côté connecteur.
- Conserver le fonctionnement actuel de `set_project_appointment_summary`.
