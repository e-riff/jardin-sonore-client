# Instructions connecteur MCP — notes de dossier

À intégrer aux consignes du connecteur Vibes pour `create_project_note`.

## Format à envoyer

Le champ `content` accepte du texte brut ou un fragment HTML simple. **N'utilise pas Markdown** : `**gras**`, `*italique*`, `# titre` et `[lien](URL)` ne sont pas interprétés et peuvent apparaître littéralement dans le fil.

- Gras : `<strong>texte important</strong>` (ou `<b>…</b>`).
- Italique : `<em>nuance</em>` (ou `<i>…</i>`).
- Retour à la ligne : `<br>` ; séparation de paragraphes : `<br><br>`.
- Lien : `<a href="https://exemple.fr/page">texte du lien</a>` ; seuls les liens HTTP(S) sont conservés.
- Couleur facultative : `<span style="color:#47664B">texte</span>`. Couleurs autorisées : `#A64D43`, `#692019`, `#47664B`.
- Texte brut accepté ; ses retours à la ligne sont convertis en sauts de ligne HTML.

Exemple pour un compte rendu d'appel :

```html
<strong>Compte rendu d’appel</strong><br><br>Échange avec Marie au sujet du projet.<br><em>À relancer la semaine prochaine.</em><br><a href="https://exemple.fr/dossier">Document partagé</a>
```

Construis le fragment HTML dans la valeur `content`. N'envoie pas les balises dans `body` d'un autre outil et ne fournis pas un document HTML complet. Les scripts, images, styles non autorisés et liens dangereux sont supprimés par le serveur.

Avant `create_project_note`, vérifie le dossier exact et fais confirmer séparément l'écriture. Cette note peut être créée sans mail source ; elle apparaît dans le fil du dossier.
