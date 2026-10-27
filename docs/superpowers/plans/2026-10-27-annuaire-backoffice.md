# Annuaire du backoffice — plan d’implémentation

**Livré en production le 27 octobre 2026** sous `deploy-backoffice-annuaire-mailing-20261027-03` (`38a2e67f`). L’annuaire comprend le catalogue LiveComponent paginé par 10, recherche/filtres, navigation alphabétique qui ignore les guillemets initiaux, fiches responsives, copie et actions téléphone/e-mail, carte communale en lecture seule, formulaires Symfony et dialogues d’édition par bloc pour les structures et les personnes. Les géoshapes n’ont pas de marqueur central ni d’épingle d’adresse ; le géocodage a été écarté. La migration `Version20261027111508` a supprimé les anciens champs ville/code postal après rattachement des communes sûres ; la migration `Version20261027160000` rattache ensuite le secteur « Pays du Gier (18 communes) » du Ludobus à Saint-Chamond comme point de référence. Le ciblage ainsi obtenu couvre Saint-Chamond uniquement, pas les 18 communes ; leur relation dédiée reste à modéliser. EasyAdmin et sa dépendance Composer sont retirés.

**Complément préparé le 27 octobre, en attente de déploiement :** les téléphones français s’afficheront avec des groupes de chiffres espacés visuellement et seront copiés en format national sans séparateurs ; la sous-navbar est retirée de l’annuaire et son entrée principale ouvre directement celui-ci, avec une transition douce lorsqu’on passe d’une rubrique à l’autre ; le bouton de repli du panneau de fiche pointera horizontalement et sa colonne se repliera avec une transition, désactivée pour les préférences de mouvement réduit. La migration `Version20261027160000` rattache l’adresse secteur du Ludobus à Saint-Chamond afin de l’inclure dans les campagnes ciblant cette seule commune.

> **Pour les agents d’exécution :** mettre en œuvre les étapes dans l’ordre dans la branche de travail courante.

**But :** remplacer progressivement l’annuaire EasyAdmin par une interface métier Symfony responsive, centrée sur les structures et leurs personnes de contact.

**Architecture :** une page métier sous le layout interne, avec un composant Live pour la recherche, les filtres, le tri, la pagination et la sélection. Des formulaires Symfony dédiés gèrent les structures, les personnes et leurs coordonnées ; les règles de partage des contacts existantes restent la référence. L’aperçu cartographique réutilise la géographie liée à la commune.

**Technologies :** Symfony, Doctrine, Twig, Symfony UX LiveComponent, Stimulus, Turbo, Leaflet existant.

**Maquette de référence :** `.codex/Design annuaire/jardin_sonore_carnet_d_adresses_desktop_v4/code.html` et `.codex/Design annuaire/jardin_sonore_carnet_d_adresses_mobile_v4/code.html`, avec la dernière consigne utilisateur : boutons mobiles d’action à icône seule, de gabarit commun et alignés à droite.

## Contraintes

- Préserver les tables de structures et de personnes ainsi que les comportements actuels des contacts partagés, des consentements newsletter et des liens portail.
- Permettre plusieurs téléphones et e-mails, chacun avec son libellé ; chaque action téléphone/e-mail vise l’entrée correspondante.
- Rechercher et filtrer côté serveur, trier alphabétiquement et paginer ; proposer une navigation par lettre et un accès direct à une page. Ne pas charger tout l’annuaire dans le navigateur.
- Montrer le contour géographique de la commune liée sans rendre la géométrie modifiable.
- N’ajouter ni notes ni jours de présence au modèle, ces champs n’existent pas.
- EasyAdmin a été retiré après la recette utilisateur du nouvel annuaire.
- Les colonnes historiques `city` et `postalCode` ont été supprimées par `Version20261027111508` ; les formulaires métier utilisent la commune liée.

## Décisions d’interface

- L’onglet principal s’appelle « Annuaire & Contacts » ; l’index distingue « Structures » et « Personnes ».
- Sur grand écran, liste filtrable à gauche et fiche à droite ; la fiche peut être repliée.
- Sur mobile, la liste ouvre une fiche dédiée ; les coordonnées générales viennent en haut, les personnes avant la carte.
- Garder la recherche visible sur mobile et replier les filtres secondaires par défaut. Appliquer un accent ocre aux structures et vert foncé aux personnes.
- Modifier l’identité, les contacts et les adresses dans des dialogues séparés ouverts depuis chaque bloc de la fiche, pour les structures comme pour les personnes. Garder les écrans dédiés de formulaire comme parcours de secours.
- Les contacts affichent valeur et libellé ; le clic sur la valeur copie l’adresse e-mail ou le numéro normalisé, tandis que le bouton icône adjacent ouvre `mailto:` ou `tel:` pour ce même contact.
- Les modifications passent par les petits dialogues ou sections d’édition déjà prévus par la maquette.

## Étapes

### 1. Route, navigation et catalogue

- Ajouter les routes métier de l’annuaire et ses libellés français dans le domaine de traduction adapté.
- Ajouter le lien « Annuaire & Contacts » au groupe principal du layout interne.
- Créer un LiveComponent de catalogue avec filtres URL, recherche AJAX, ordre alphabétique, filtres repliables, navigation par lettre, accès direct à une page et sélection persistante de la fiche.
- Construire les requêtes de liste et de détail avec des sélections limitées et des relations explicites pour éviter le chargement en masse et les N+1.

### 2. Fiches et carte communale

- Rendre les fiches de structure et de personne avec leurs contacts actifs et leurs libellés.
- Afficher les personnes rattachées avant l’adresse sur desktop et mobile.
- Réutiliser la carte communale existante, en lecture seule, à partir de `MunicipalityEntity.geoShape`.
- Retirer du nouvel affichage les champs de localisation libres redondants avec la commune liée ; garder les valeurs historiques en base dans cette passe.

### 3. Édition et création

- Ajouter des formulaires Symfony pour créer/modifier les structures et les personnes, y compris les personnes sans structure.
- Ouvrir la création et la modification dans un dialogue depuis l’index ou la fiche, en réutilisant les mêmes formulaires Symfony.
- Gérer plusieurs liens de téléphone/e-mail/adresse et leurs libellés en réutilisant les types existants.
- Réutiliser `SharedContactLinkResolver` pour normaliser/rattacher les contacts partagés, sans modifier le consentement newsletter d’une adresse existante.
- Ajouter une confirmation explicite avant toute suppression et conserver les références/contraintes existantes.
- Implémenter la copie au clic sur les valeurs et les actions `tel:`/`mailto:` à droite, avec boutons mobiles à icône seule, nom accessible et titre explicite.

### 4. Vérification et recette

- Vérifier le lint PHP, le rendu Twig et la compilation des assets, sans exécuter de tests automatisés dans cette passe.
- Contrôler visuellement l’index et les fiches sur desktop/mobile, puis faire la recette fonctionnelle interactive avec l’utilisateur.
- Retirer les écrans EasyAdmin de l’annuaire uniquement après validation utilisateur.

## Points de revue

- Une recherche sur un grand nombre d’entrées conserve pagination et tri stables.
- Une personne sans structure reste consultable et modifiable.
- Une adresse e-mail déjà partagée ne perd pas son opt-in, son désabonnement ou sa confirmation lorsqu’un lien d’annuaire est modifié.
- Une suppression ne casse pas les accès portail, demandes commerciales ou contacts référencés.
- Une commune sans géométrie valide garde une adresse et une fiche utilisables.
