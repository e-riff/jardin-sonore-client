# Portail structures — répertoire partagé et URLs par slug

## Intention

Donner aux structures connectées un accès simple aux comptines et jeux de doigts réellement utilisés dans les séances qui leur sont partagées. Le répertoire ne doit jamais exposer un contenu de catalogue non partagé avec le compte connecté.

Les séances et les éléments de répertoire doivent utiliser des adresses lisibles et pérennes, basées sur un slug métier plutôt que sur un UUID technique.

## Portée

Cette évolution couvre :

- des slugs persistants, uniques et éditables pour les séances et les éléments de répertoire ;
- la migration des routes de fiche de séance vers leurs slugs ;
- une API portail authentifiée pour le catalogue de répertoire accessible ;
- les écrans liste et fiche de comptines dans le portail ;
- l’affichage de badges de thèmes colorés dans les listes et fiches des séances et du répertoire.

Elle ne couvre pas les activités, la création de contenus par les structures, ni un catalogue public.

## Slugs

Les entités `SessionSummary` et `RepertoireItem` possèdent un champ `slug` unique, non vide et indexé. Il est généré à partir du titre à la création, avec un suffixe numérique en cas d’homonymie (`comptine`, `comptine-2`).

Le back-office affiche et permet d’éditer ce slug. Une modification du titre ne modifie pas automatiquement un slug déjà enregistré : les liens envoyés aux structures restent ainsi valides. Le back-office peut proposer une régénération explicite à partir du titre, soumise à l’unicité.

Une migration crée les colonnes, génère les slugs existants de façon déterministe, puis applique les contraintes d’unicité. Les nouveaux slugs sont validés avant persistance.

Les routes portail deviennent :

- `/portail/seances` et `/portail/seances/{slug}` ;
- `/portail/comptines` et `/portail/comptines/{slug}`.

Les UUID restent internes : ils peuvent rester employés par les traitements de document PDF, mais ne font plus partie des URL ou contrats de navigation du portail. Les anciennes routes UUID peuvent retourner une redirection canonique lorsque le slug est résolu et autorisé, ou être retirées si aucun lien externe n’a été diffusé.

## Accès et API

Le backend est l’unique autorité d’accès. Pour un compte connecté, le répertoire accessible est l’ensemble dédupliqué des éléments `RepertoireItem` actifs, de type `nursery_rhyme` ou `fingerplay`, qui sont référencés par une séquence d’au moins une `SessionSummary` partagée avec l’une de ses organisations autorisées.

Les entrées de séquence sans source de répertoire ne créent aucun droit d’accès au catalogue. Un élément inactif est invisible, même s’il est référencé dans une séance historique. Une fiche demandée avec un slug inconnu, inactif ou non accessible répond comme une ressource absente.

Deux endpoints authentifiés sont ajoutés :

- `GET /api/portal/repertoire` retourne les éléments accessibles, avec slug, titre, type, thèmes `{label, color}`, et la première ressource vidéo YouTube utilisable comme miniature ;
- `GET /api/portal/repertoire/{slug}` retourne l’élément accessible avec ses blocs de contenu, paroles, gestes, consignes générales, notes, thèmes et toutes ses ressources liées.

Les endpoints de séance acceptent et retournent désormais le slug. Tous les contrôles de structure existants sont conservés avant lecture.

## Interface portail

Le lien « Comptines » devient actif dans les navigations desktop et mobile. « Activités » reste indisponible et marqué « À venir ».

La page liste affiche une introduction explicitant le périmètre : « Retrouvez les comptines et jeux de doigts utilisés dans les séances partagées avec votre structure. » Elle fournit :

- une recherche sur le titre et les libellés de thèmes ;
- un filtre par type (tous, comptines, jeux de doigts) ;
- un filtre par thème ;
- un tri par titre, type ou thème ;
- des lignes cliquables avec miniature YouTube à gauche si disponible, puis type, titre et badges de thèmes colorés à droite.

L’absence de vidéo ne crée pas de zone vide : la ligne conserve son alignement et son contenu textuel. Les contrôles sont utilisables au clavier et produisent un état vide explicite.

La fiche affiche le type, le titre et les badges de thèmes colorés, puis les médias. Les paroles et gestes sont rendus à partir des blocs structurés : paroles à gauche et gestes associés à droite lorsqu’ils existent, titres de section et séparations préservés. Les consignes générales et notes sont affichées seulement lorsqu’elles sont renseignées. Les autres médias sont affichés de manière adaptée : intégration YouTube sans cookie pour une vidéo YouTube, lien externe sécurisé pour les autres ressources.

## Thèmes dans les séances

La liste et la fiche de séances remplacent l’affichage texte du thème unique par les badges colorés issus du catalogue de thèmes. Le contrat de la séance évolue donc de `theme: string | null` vers `themes: Array<{uuid, label, color}>`. L’interface présente tous les thèmes disponibles de façon compacte et conserve une lecture correcte sur mobile.

## Frontend et traductions

Le client Next conserve son rôle de BFF serveur : le jeton HTTP-only est lu seulement côté serveur, puis transmis à l’API backend. Les types TypeScript décrivent explicitement les résumés et fiches de répertoire, les thèmes et les médias plutôt que d’utiliser des `Record<string, unknown>` pour cette nouvelle fonctionnalité.

Les libellés de navigation, filtres, tris, états vides, descriptions et sections de fiche sont ajoutés au dictionnaire français. Les composants de liste sont client uniquement pour les filtres, la recherche et le tri ; la récupération initiale et les fiches restent rendues côté serveur.

## Erreurs et vérification

Les erreurs d’API portail suivent le comportement existant : indisponibilité vers la page dédiée, contenu absent ou non autorisé vers la page introuvable. Les miniatures et intégrations médias sont chargées paresseusement et n’exposent pas de jeton.

Les vérifications prévues sont :

- tests unitaires de génération, unicité et stabilité des slugs ;
- tests fonctionnels des routes portail : accès autorisé, refus d’un contenu d’une autre structure, déduplication, contenu inactif, résolution par slug et médias ;
- tests des contrats de thèmes dans les séances ;
- lint et build Next.js ;
- vérifications PHP et tests backend ciblés.
