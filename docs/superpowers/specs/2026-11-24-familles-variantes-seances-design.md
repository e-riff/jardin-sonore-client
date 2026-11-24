# Familles, variantes et composition de séances

## Intention

Permettre de retrouver et réutiliser les contenus de nombreuses séances sans copier-coller. Une famille rassemble des variantes autonomes ; depuis cette famille, le compositeur permet de choisir des éléments déjà utilisés et de créer une nouvelle séance.

## Premier lot convenu

- Créer et renommer une famille ; lui donner un nom et des tags issus du catalogue partagé avec les séances, le répertoire et les médias. Ses tags sont propres à la famille et ne sont pas automatiquement ajoutés aux séances.
- Une séance appartient à zéro ou une famille. Rattacher une séance existante, la détacher ou la déplacer vers une autre famille ne modifie pas son contenu, sa publication ni ses accès. Une duplication d'une séance familiale reste dans sa famille ; une séance sans famille reste sans famille.
- La liste permet de trouver et ouvrir les familles et de retrouver les séances sans famille. Dans une famille, afficher les variantes avec titre, date, tags et éléments distinctifs, puis tous leurs éléments regroupés par type existant. Les variantes restent des séances indépendantes.
- Choisir des éléments de cette famille dans l'ordre souhaité et créer une nouvelle variante en brouillon. La sélection détermine l'ordre initial ; l'éditeur actuel permet ensuite de réordonner. La nouvelle séance n'est liée à aucune structure ni publiée automatiquement.
- Pour un élément du répertoire présent dans plusieurs variantes, proposer une seule entrée générale. On peut ajouter la fiche sans consignes propres à une séance, ou choisir une séance source et copier ses consignes spécifiques. Le lien à la fiche répertoire et son comportement de synchronisation actuels sont conservés ; la fiche répertoire n'est pas modifiée.
- Copier les éléments libres avec leur contenu, médias, instruments et réglages pertinents. Chaque copie est indépendante. Les copies futures gardent un identifiant d'origine stable, indépendant de l'identifiant propre à chaque occurrence ; les versions d'une même origine peuvent être regroupées et une version précise choisie. Une suppression de la séance d'origine ne doit pas casser les copies. Aucun rapprochement automatique d'anciens éléments ressemblants.
- Éditer les paroles et gestes des éléments libres ligne par ligne, dans l'esprit de l'éditeur du répertoire : ajout, déplacement et retrait de lignes. Les anciennes séances doivent s'ouvrir sans perte : préserver les lignes vides, les gestes sans parole, les paroles sans geste, les retours en début et fin et le contenu non modifié. Vérifier édition, duplication, portail, aperçu et PDF.
- Interfaces adaptées au téléphone et au clavier, traductions dans les domaines existants, accès et protections CSRF existants maintenus.

## Parcours par thème — étape suivante

Rechercher dans le catalogue commun de tags : un tag sur la famille permet de retrouver celle-ci et de parcourir toutes ses variantes ; un tag porté par une séance retrouve cette séance et sa famille. Le compositeur réutilise le parcours du premier lot pour piocher parmi plusieurs familles. Les éléments libres ne portent pas de tags propres ; ceux du répertoire ont leurs propres tags. Ce parcours ne fait pas partie de la première livraison.

## Différé et limites

- Pas d'ajout d'une sélection à une séance déjà commencée dans le premier lot.
- Pas de fusion automatique ou manuelle des anciens éléments libres ; les occurrences existantes sans origine sont présentées séparément avec leur séance source, sauf copie future explicitement tracée.
- Pas de synchronisation des modifications entre copies libres, ni de suppression des séances ou des documents existants.
- Pas de suppression de famille dans le premier lot ; un détachement ou déplacement explicite évite toute perte accidentelle.
- Ne pas modifier les règles de publication, de partage portail ou de génération documentaire existantes.

## Choix et points d'attention techniques

Avant ce lot, les séquences étaient stockées en JSON dans `session_summary.sequences` et `SessionSequenceView` appariait les deux anciens champs multiligne à la lecture. La duplication régénérait les UUID des séquences sans origine commune. Le modèle famille requiert une migration Doctrine ; l'origine des séquences peut être ajoutée au JSON de manière rétrocompatible sans réécriture massive. L'édition doit garantir un aller-retour sans perte, y compris les gestes seuls : la validation de `RepertoireBlockFormModel` exige un texte pour une ligne et ne peut pas être réutilisée telle quelle.

Implémentation locale : les deux champs multiligne restent la source enregistrée ; l'éditeur les présente en lignes appariées et conserve leurs longueurs respectives. Une ancienne séquence sans UUID reçoit à la lecture un UUID déterministe propre à sa séance et sa position, sans écriture de masse. Sans JavaScript, le compositeur utilise l'ordre de la liste et l'indique ; le réordonnancement reste possible ensuite dans la séance.

Les approches envisagées étaient de rattacher les variantes par héritage, de traiter chaque séance comme indépendante sans regroupement, ou d'utiliser une famille regroupant des séances autonomes. Le dernier choix est retenu : il permet de composer sans propager les modifications entre variantes. Le composage transversal par thème est conservé comme étape suivante plutôt que supprimé.
