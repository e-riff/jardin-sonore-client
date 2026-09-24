# Portail structures — comptines et filtres partagés

## Intention

Permettre à une structure connectée de retrouver les comptines et jeux de doigts réellement utilisés dans ses séances partagées. Les listes de séances et de comptines doivent être faciles à explorer, accessibles et partageables dans un état de filtre précis.

## Portée

- Ajouter le répertoire portail limité aux comptines et jeux de doigts autorisés par les séances partagées.
- Remplacer le faux tri par catégorie des séances par un filtre de catégories cumulables.
- Offrir les mêmes principes de filtrage aux comptines, avec un filtre supplémentaire de type.
- Conserver filtres et tris dans l'URL pour restaurer une vue après navigation ou partage.

Les activités, l'édition de contenu et un catalogue public restent hors périmètre.

## Accès et API

Symfony reste l'unique autorité d'accès. Un élément de répertoire est visible seulement s'il est actif, de type `nursery_rhyme` ou `fingerplay`, et référencé par au moins une séance partagée avec une organisation autorisée de l'utilisateur.

Les endpoints de listes appliquent les critères en SQL avant pagination :

- `q` recherche dans le titre et les libellés de catégories ;
- `organization` limite à une structure autorisée ;
- `theme` peut être répété et conserve un élément dès qu'au moins une catégorie sélectionnée correspond ;
- `type` limite les comptines à `nursery_rhyme` ou `fingerplay` ;
- `sort` et `direction` définissent l'ordre.

Les critères se combinent en ET, sauf les catégories répétées qui se combinent en OU. Une structure demandée mais non autorisée ne révèle aucun contenu.

`GET /api/portal/repertoire` renvoie les éléments accessibles, leurs catégories colorées et les structures par lesquelles ils sont accessibles. `GET /api/portal/repertoire/{slug}` garde la même règle d'autorisation et répond 404 pour un slug absent, inactif ou non accessible.

Les réponses de séance passent de `theme` à `themes`, une liste explicite de catégories `{uuid, label, color}`. Les résumés de séance et de répertoire exposent leurs structures pour permettre le filtre dédié.

## Interface portail

Les états sont encodés dans les paramètres de requête et lus côté serveur par les pages Next. Les contrôles client modifient l'URL, ce qui recharge la liste BFF correspondante ; le retour arrière, un rechargement et un lien partagé restituent donc la même vue.

La structure est un sélecteur unique, visible uniquement pour un compte associé à plusieurs structures. Les catégories sont des badges activables cumulables, avec `aria-pressed`, et un contrôle réinitialise tous les critères.

### Séances

- recherche texte ;
- filtre structure conditionnel ;
- filtre catégories cumulables ;
- tri par date ou titre, dans les deux directions.

### Comptines

- recherche texte ;
- filtre structure conditionnel ;
- filtre catégories cumulables ;
- filtre de type : toutes, comptines ou jeux de doigts ;
- tri par titre ou date, dans les deux directions ;
- liste et fiche accessibles à partir de `/portail/comptines` et `/portail/comptines/{slug}`.

La liste présente une vignette uniquement lorsqu'une vidéo YouTube exploitable existe. La fiche affiche les blocs structurés, paroles et gestes, médias et catégories ; les sections non renseignées sont omises.

## Accessibilité et erreurs

Tous les contrôles ont un libellé, sont navigables au clavier et rendent leur état programmatique. Une liste vide décrit clairement l'absence de résultat et propose de réinitialiser les filtres.

Les erreurs d'API suivent le comportement existant : indisponibilité vers la page dédiée, contenu absent ou non autorisé vers la page introuvable. Les médias n'exposent jamais le jeton portail.

## Vérification

- Tests fonctionnels Symfony : droits inter-structures, item inactif, déduplication, recherche, structure, type, cumul de catégories, ordre ascendant/descendant et 404.
- Tests de contrat : catégories et structures dans les réponses de séance et de répertoire.
- Vérification Next : restauration d'une URL filtrée, état vide, navigation clavier et fiche collée directement.
- Lint et build Next, tests et analyse statique Symfony, contrôle du diff de schéma Doctrine.
