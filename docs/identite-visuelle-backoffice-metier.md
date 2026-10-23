# Identité visuelle du backoffice métier

Document vivant — 14 octobre 2026. Il décrit les règles de l’interface Symfony utilisée au quotidien pour les dossiers, séances, catalogues et campagnes e-mail. Les valeurs effectives sont dans `jardin-sonore-backend/assets/styles/app.css`.

## Ambiance et hiérarchie

- Fond général clair et neutre (`#faf9f6`), surfaces presque blanches (`#fffdfb`), bordures discrètes (`#d9d4cc`). Éviter les grands aplats roses et les cartes imbriquées sans raison.
- Terre cuite (`#be4f41`) pour l’action principale, le retour détouré, l’accent de page et les urgences. Vert (`#47664b`) pour les liens et actions secondaires, la progression et les confirmations. Le rouge fort est réservé aux erreurs et suppressions.
- Titres en Noto Serif, lecture et contrôles en Plus Jakarta Sans. Un titre de page, une courte explication si elle aide, puis le contenu. Les états passent par des badges sobres.
- Les informations qui aident à décider sont visibles en premier : prochaine tâche, date, structure et personne, dernier fait du fil. Les détails secondaires peuvent être repliés.

## Navigation et actions

- Sur les pages secondaires, afficher le bouton « Retour » **avant le titre**, au même emplacement sur mobile et ordinateur. Utiliser `templates/components/_internal_back_button.html.twig` : fond blanc, contour et texte terre cuite, flèche à gauche ; l’intitulé précise la destination. Une action « Annuler » près de la validation peut rester utile pour les longs formulaires.
- Garder la création ou l’action principale près du titre ou du début du contenu. Dans les tableaux, toute ligne navigable se surligne au survol, montre un curseur pointeur et reste utilisable au clavier ; les boutons propres à la ligne ne déclenchent pas sa navigation.
- Une navigation contextuelle vers une fiche liée (par exemple EasyAdmin depuis un dossier) reste un lien HTML, mais adopte le rendu d’un bouton icône compact existant (`.internal-icon-button` ou `.commercial-dossier__icon-action`) avec `title` et nom accessible. Ne pas afficher ces accès comme des liens texte verts soulignés.
- Mettre la **validation à la fin du formulaire**, dans `.internal-form__actions`, après les champs et les aides. Les actions destructrices ont leur couleur et leur confirmation propres.
- Pour les listes, placer recherche et filtres immédiatement **au-dessus du tableau**. Le résultat change sans bouton « Rechercher » : LiveComponent pour les catalogues qui en disposent, Stimulus pour les listes GET plus simples. Conserver l’URL de la recherche pour pouvoir retrouver la vue. Les en-têtes et le tri mobile doivent préserver les filtres actifs.

## Mobile et contenu

- Concevoir d’abord la largeur du téléphone : contrôles empilés, aucun défilement horizontal de page, liens `tel:` et `mailto:` accessibles, actions de ligne séparées et assez grandes pour le toucher.
- Un tableau peut devenir une liste de lignes compactes sur mobile ; garder le libellé principal, l’état, l’échéance et les actions. Les colonnes secondaires peuvent passer en texte de second niveau.
- Dans un dossier, « À faire » décrit les tâches encore ouvertes ; « Fil du dossier » décrit les faits dans l’ordre chronologique inverse. L’élément le plus récent est ouvert par défaut ; les textes longs se déplient individuellement. Les documents restent retrouvables sans repousser le fil sous une grande carte.
- Sur la fiche dossier, « Contacts » et « Documents et suivi » sont deux blocs repliables signalés par une icône et un chevron. Les contacts sont ouverts par défaut sur ordinateur et repliés sur téléphone ; les actions pour ajouter une tâche ou un échange viennent ensuite, juste avant les listes qu’elles alimentent.
- Sur l’index des dossiers, les groupes « Urgents », « À venir » et « Sans tâche prévue » s’ouvrent s’ils contiennent des dossiers ; un groupe vide reste fermé. Une icône propre à chaque groupe et une très légère teinte d’en-tête aident à les distinguer sans colorer tout le tableau.
- Dans les formulaires d’édition, le retour reste au-dessus du titre, l’enregistrement reste à la fin du formulaire et la suppression se place dans les actions de l’en-tête.

## État d’application

| Écran | Recherche / filtre au-dessus de la liste | Point à suivre |
| --- | --- | --- |
| Dossiers | LiveComponent | Recette authentifiée mobile et ordinateur à faire. |
| Instruments, répertoire, médias | LiveComponent | Vérifier les actions et le tri sur téléphone. |
| Séances, prolongements, recommandations e-mail | Stimulus, mise à jour sans bouton | Vérifier clavier, chargement et tri avec filtres actifs en navigateur connecté. |
| Campagnes e-mail, masques d’audience | Stimulus, mise à jour sans bouton | Vérifier les mêmes parcours en navigateur connecté. |
| Factures commerciales | Vue historique en cartes | Reprendre comme vraie liste recherchable lors du lot facturation ; garder l’accès depuis chaque dossier. |
| Adresses invalides | Formulaire de traitement ponctuel, résultats après envoi | Ce n’est pas un catalogue persistant à rechercher. |

La refonte visuelle du dossier et ces règles communes sont locales à la branche de travail. Elles demandent encore une recette connectée avant déploiement.
