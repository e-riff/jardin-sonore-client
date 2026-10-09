# Brief de maquettes — suivi commercial V1

Date : 9 octobre 2026. Support de travail pour Stitch, à relire avant toute implémentation des écrans. Référence fonctionnelle : [conception V1](superpowers/specs/2026-10-08-suivi-commercial-v1-design.md).

## But

Dessiner des écrans de travail pour **le backoffice métier existant** du Jardin Sonore. L'utilisateur gère seul les demandes, appels, relances, devis et factures. L'interface doit l'aider à voir ce qui réclame son attention et à compléter un dossier pendant un appel. Priorité à la lisibilité et à la rapidité des gestes ; pas de refonte esthétique.

Concevoir **d'abord pour un téléphone de 360 px**, puis adapter à 1280 px. Aucun tableau large ni défilement horizontal sur téléphone. Les cibles tactiles, formulaires, états vides, erreurs et confirmations doivent rester compréhensibles. Le bureau peut juxtaposer des panneaux sans changer l'ordre logique des informations.

## Identité visuelle à reprendre

Le backoffice actuel utilise un fond crème `#fdf8f5`, un texte sombre `#1c1b1a`, un accent terre cuite `#be4f41`, un vert secondaire `#47664b`, des surfaces claires, des contours discrets et une navigation interne avec menu mobile. Titres en **Noto Serif**, commandes et métadonnées en **Plus Jakarta Sans**. Reprendre cette ambiance sobre et chaleureuse, avec un contraste net pour les états urgents. Ne pas utiliser les couleurs comme unique indication d'état.

## Écrans à produire

1. **Accueil « Suivi clients »** : demandes « À qualifier » avec date de réception ; actions regroupées « En retard », « Aujourd'hui », « À venir » ; accès aux factures à suivre ; section « Dossiers actifs sans prochaine action ». Les lignes courtes donnent titre, structure, échéance et geste rapide. Un clic ouvre le détail ou le dossier. Sur téléphone, les groupes s'empilent dans cet ordre et les actions courantes restent faciles à atteindre.
2. **Qualification d'une demande** : en haut, message original et coordonnées reçues ; choix explicite d'une structure existante ou création ; choix, création et correction des personnes ; coordonnées modifiables sans perdre la demande ; titre du dossier puis bouton « Ouvrir le dossier », et possibilité « Classer sans suite ». Montrer une suggestion de structure ressemblante qui n'est **pas** sélectionnée automatiquement.
3. **Fiche dossier pendant un appel** : titre et statut « En discussion » ou « Confirmé » ; structure porteuse et personnes liées, interlocuteur principal facultatif ; numéros de téléphone et courriels actionnables ; zone de note libre immédiatement accessible ; ajout facultatif d'une prochaine action ; historique chronologique et listes compactes des actions, devis et factures. Montrer l'édition rapide d'un contact sans effacer une note en cours.
4. **Action ouverte depuis une liste** : ligne compacte avec échéance et contexte ; détail déplié avec précision et interlocuteur ; boutons « Terminer », « Reporter », « Modifier », et « Annuler » dans le détail. Quand la dernière action est terminée, proposer « Créer une autre action » ou « Classer sans suite » si le dossier est en discussion, sans clôture automatique.

Produire pour chaque écran une version téléphone et, si utile, une adaptation bureau du même parcours. Prévoir un exemple d'état vide pour l'accueil et un exemple d'erreur de validation sur la qualification. Les noms, numéros et montants montrés sont fictifs.

## Prompt à transmettre à Stitch

> Crée des maquettes basse fidélité mais soignées pour quatre écrans d'un backoffice métier français de suivi commercial nommé « Jardin Sonore » : accueil de suivi, qualification d'une demande, fiche dossier pendant un appel, action dépliée dans une liste. Commence par un téléphone de 360 px, puis montre l'adaptation bureau à 1280 px. Utilise l'identité existante : fond crème #fdf8f5, texte #1c1b1a, terre cuite #be4f41, vert #47664b, Noto Serif pour les titres, Plus Jakarta Sans pour les contrôles. Priorité à la hiérarchie, aux coordonnées actionnables, à la note d'appel non perdue, à la qualification explicite des structures/personnes et aux actions terminer/reporter. Aucun tableau horizontal sur mobile. Prévois un état vide et une erreur de formulaire. Utilise seulement des données fictives. Garde la navigation du backoffice métier et évite toute refonte décorative. Le détail fonctionnel est dans les quatre descriptions d'écrans ci-dessus.

## Points à valider sur les sorties

- Depuis l'accueil, trouve-t-on « À qualifier », une action en retard et un dossier sans prochaine action en quelques secondes sur téléphone ?
- Pendant un appel, le numéro à composer, la note en cours et l'interlocuteur sont-ils visibles sans perdre le contexte ?
- La sélection d'une structure suggérée demande-t-elle bien un choix explicite, avec une option de création ?
- Terminer et reporter une action sont-ils rapides, sans confondre « Terminée » et « Annulée » ?
- La version bureau garde-t-elle les mêmes priorités que la version téléphone ?

## Sorties Stitch

Exports reçus le 9 octobre 2026 :

- [Prototype mobile 360 px](../.codex/jardin_sonore_suivi_commercial_v1_mobile_360px/code.html) et sa [capture d'accueil](../.codex/jardin_sonore_suivi_commercial_v1_mobile_360px/screen.png).
- [Prototype bureau 1280 px](../.codex/jardin_sonore_suivi_commercial_v1_desktop_1280px/code.html) et sa [capture d'accueil](../.codex/jardin_sonore_suivi_commercial_v1_desktop_1280px/screen.png).

Les deux prototypes ont quatre onglets interactifs. Les écrans de qualification, d'appel et d'action ont été ouverts et relus dans le navigateur aux deux largeurs. Le prototype est une référence de hiérarchie et de style, pas du code à copier dans Symfony.

### Choix retenus pour l'implémentation

- Sur téléphone, conserver l'ordre « À qualifier », actions en retard/aujourd'hui/à venir, factures à suivre, dossiers actifs sans prochaine action. Les cartes courtes et les coordonnées actionnables conviennent au travail quotidien.
- Sur bureau, juxtaposer les blocs sans changer cette priorité. Garder la demande d'origine visible à côté de la qualification et, pendant l'appel, les coordonnées près de la note.
- Sur la fiche, garder la note en cours quand un contact est corrigé ; une fenêtre ou un panneau d'édition local à la page suffit. L'enregistrement automatique permanent et le chronomètre d'appel montrés par Stitch ne sont pas requis en V1.
- Dans l'action dépliée, garder le contexte, l'interlocuteur et les gestes « Terminer », « Reporter », « Modifier », « Annuler ». La prochaine action est facultative et le dossier ne se ferme jamais du seul fait que la dernière action est terminée.
- Éviter tout débordement de contenu sur 360 px. La navigation interne devra rester utilisable sans perdre une destination hors écran ; le prototype mobile serre déjà fortement la barre supérieure.

### Écarts à corriger par rapport à la conception validée

- « Suggestion intelligente » et pourcentage de correspondance : remplacer par une recherche de structures/personnes existantes, jamais sélectionnées automatiquement. L'utilisateur valide explicitement rattachement, création et correction.
- « Contact rattaché — création automatique » : remplacer par un choix ou une création explicite. Ne pas changer l'annuaire à partir du seul texte de la demande.
- « Devis brouillon », bouton « Générer », liste de PDF et pièces jointes : hors V1. Afficher les seules références et montants des devis envoyés, et ceux des factures enregistrées ; les fichiers restent dans Drive.
- Indicateur de séances, dates de séance, agenda et rappels à l'heure : hors V1. Une action a une date d'échéance sans heure ; les séances restent dans Google Calendar.
- « Ignorer / marquer comme spam » : garder « Classer sans suite », qui conserve la demande. Pas de suppression ou de classement spam automatique.
- Montants « TTC » et logique d'échéance affichée : reprendre les montants enregistrés tels quels ; la relance de facture suit la règle des 30 jours après émission, et non une nouvelle échéance calculée par la maquette.

Revue utilisateur des choix de parcours en attente ; la conception V1 déjà validée prévaut sur les comportements supplémentaires imaginés par Stitch.
