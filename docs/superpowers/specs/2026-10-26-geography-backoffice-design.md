# Gestion géographique dans le backoffice métier

## Intention

Déplacer les écrans EasyAdmin de Régions, Départements et Communes dans l’onglet Technique du backoffice métier, avec une entrée secondaire unique « Géographie » qui ouvre directement la liste des communes. Le transfert conserve les capacités métier existantes ; il ne modifie ni les données, ni les règles de synchronisation et de rattachement.

## Objectifs

- Rendre les communes rapidement consultables et modifiables depuis l’interface utilisée au quotidien.
- Garder les trois référentiels géographiques dans un seul espace de navigation.
- Afficher une liste de communes paginée, filtrable et triable sans charger l’ensemble des enregistrements ni leur géométrie dans le navigateur.
- Conserver les opérations actuelles de création, modification et suppression ainsi que tous les champs modifiables.

## Hors périmètre

- Aucun changement de schéma, d’import ou de synchronisation géographique.
- Aucun changement de comportement des liens vers les communes depuis l’annuaire, les filtres EasyAdmin ou le ciblage géographique du mailing.
- Pas de page de consultation détaillée : les lignes ouvrent directement l’édition.
- Pas de changement aux valeurs ni aux relations géographiques déjà enregistrées.

## Approches considérées

1. **Espace Géographie unique dans Technique (retenue).** L’entrée ouvre les Communes ; une navigation secondaire dans la page donne accès aux Départements et Régions. Les trois CRUD sont transférés ensemble, tout en conservant les données et les usages d’EasyAdmin. Cela répond à l’usage quotidien des communes et évite de disperser le référentiel.
2. **Transférer les Communes d’abord, puis les deux autres référentiels.** Cette option isole le risque de volumétrie, mais laisse temporairement les fonctions géographiques réparties entre deux interfaces alors qu’elles forment un même ensemble.

## Écrans et comportements

### Navigation

- Ajouter une seule entrée « Géographie » à la navigation secondaire de Technique.
- Elle ouvre par défaut l’index des communes.
- Dans l’espace Géographie, proposer les accès Communes, Départements et Régions et signaler clairement la page active.
- Après retrait des liens de menu EasyAdmin, garder les contrôleurs CRUD EasyAdmin nécessaires aux usages relationnels existants (autocomplétion, filtres et liens depuis d’autres écrans) tant qu’ils ne sont pas remplacés.

### Communes

- L’index présente : **Nom**, **Code INSEE**, **Code postal**, **Département** (code et nom).
- Au premier chargement sans filtre explicite, limiter les résultats au département de la Loire, code `42`.
- Le filtre géographique est un arbre Région → Départements avec cases à cocher aux deux niveaux. Cocher une région sélectionne ses départements ; cocher quelques départements permet une sélection mixte entre régions. Une région indique visuellement une sélection partielle de ses départements.
- Au premier chargement sans sélection explicite, le département de la Loire (`42`) est coché. Réinitialiser la sélection enlève tous les filtres.
- Recherche réactive, sans rechargement complet, par nom, code INSEE, code postal et code ou nom du département ; tout changement de recherche ou filtre ramène à la première page.
- Pagination côté serveur, 50 lignes par page par défaut. L’index utilise une projection légère des seules colonnes utiles : il ne charge pas les entités complètes ni leur champ `geoShape`.
- Tri initial par nom croissant ; tri interactif sur les quatre colonnes. Le tri du département utilise son code.
- Chaque ligne ouvre directement la page d’édition avec les actions compactes habituelles du backoffice (icônes accessibles, titres et libellés accessibles). Aucun écran de détail.
- L’édition et les actions de création/suppression gardent les champs EasyAdmin : nom, code INSEE, code postal, département, adresse, téléphone, e-mail, SIREN et SIRET.
- La forme géographique et les coordonnées centrales sont affichées en lecture seule sur une carte avec le contour de la commune et son point central. Elles ne sont pas modifiables depuis le backoffice métier pour le moment.

### Départements

- Index avec **Code** avant **Nom**, tri initial par code croissant et recherche AJAX sur le nom et le code.
- Formulaire conservant le code, le nom et le rattachement à une région, actuellement modifiable dans EasyAdmin.
- Créer, modifier et supprimer restent possibles ; pas de détail séparé.

### Régions

- Index et formulaire simple avec code et nom ; recherche AJAX sur le nom uniquement.
- Créer, modifier et supprimer restent possibles ; pas de détail séparé.

### Suppression et relations

- Demander confirmation avant toute suppression.
- Ne supprimer aucune donnée liée en cascade : les contraintes relationnelles actuelles continuent à protéger les référentiels. En cas de dépendance, expliquer que la suppression est refusée et conserver l’enregistrement.
- Ne pas modifier les règles actuelles si une action échoue pour une autre raison ; rendre le retour d’erreur compréhensible.

## Contraintes techniques et expérience

- Dans chaque page de l’espace Géographie, afficher une navigation secondaire locale **Communes / Départements / Régions** sous le titre de page. La navigation principale de Technique ne reçoit qu’un lien Géographie, qui ouvre les Communes.
- Suivre les composants et styles du backoffice métier, notamment son index, ses filtres AJAX, ses actions compactes par icônes avec nom accessible et ses formulaires dans des dialogues lorsque le pattern est adapté.
- Les requêtes de liste doivent appliquer filtres, recherche, tri et pagination en base de données. Aucun chargement complet de communes, notamment avec GeoJSON, ne doit être fait pour construire les choix ou l’index.
- Les sélections de région et de département restent recherchables/autocomplétées si une liste exhaustive risque d’être coûteuse.
- Toute interaction réactive reste accessible au clavier, conserve les paramètres de filtre pertinents et expose l’état de chargement ou d’erreur.
- Le modèle de données, les relations et les champs persistés restent inchangés.
- Le code contient des commandes manuelles de synchronisation avec geo.api.gouv.fr. Elles peuvent réécrire les coordonnées centrales si lancées avec la synchronisation du centre activée. Aucune planification de ces commandes n’a été trouvée dans le dépôt ; une tâche planifiée hors dépôt n’a pas été vérifiée. Le backoffice métier affichera le contour et le centre sans permettre leur édition.

## Points laissés à l’implémentation

- Réutiliser le pattern Symfony UX LiveComponent déjà employé par la table des comptes portail pour la recherche AJAX, l’arbre de sélection, le tri et la pagination, sauf contrainte observée lors de l’intégration.
- Choisir une projection Doctrine/DBAL bornée qui ne sélectionne pas la grande colonne géographique ; garder l’écriture sur le flux Doctrine existant.
- Conserver les liens de navigation EasyAdmin internes encore nécessaires et retirer uniquement les entrées de menu des trois CRUD géographiques.
- Reprendre les libellés et validations existants depuis les traductions et formulaires EasyAdmin.
