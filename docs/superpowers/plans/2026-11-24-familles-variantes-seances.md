# Familles et variantes de séances — plan d’implémentation

> **Exécution :** reprendre demain ; suivre les consignes du dépôt et les étapes ci-dessous. Ne pas engager ni déployer sans autorisation applicable. Cocher les étapes au fur et à mesure.

**Objectif :** retrouver les variantes d'une famille et composer une nouvelle séance en réutilisant leurs éléments, sans perte ni modification des séances existantes.

**Architecture :** une famille facultative par séance, des tags propres à la famille et des séquences qui restent embarquées dans chaque séance. Une origine stable relie les copies futures d'un élément libre ; les éléments de répertoire gardent leur source et leurs consignes locales facultatives. Les vues familiales alimentent une création ordonnée en brouillon, puis l'éditeur existant.

**Technologies :** Symfony, PHP, Doctrine, Twig, Stimulus/Turbo selon les usages existants, PHPUnit et MariaDB.

**Conception :** [Familles, variantes et composition de séances](../specs/2026-11-24-familles-variantes-seances-design.md).

**État local au 24 novembre :** branche `feat/familles-variantes-seances`, changements non commités et non publiés. Le premier parcours est implémenté ; la migration `Version20261124164210` est appliquée en développement et en test, avec `down`/`up` vérifiés sur la base de test. La base de développement est synchronisée ; la base de test a quatre migrations historiques non exécutées et des écarts sans rapport avec ce lot. Ne pas les appliquer sans examen. La recette navigateur locale a vérifié le compositeur, l'ordre de sélection, le formulaire en fenêtre et les lignes de paroles/gestes sur mobile ; compléter la recette métier et la revue avant toute livraison.

## Contraintes et vérifications transversales

- Relire `AGENTS.md`, `.codex/jardin-sonore-guidelines.md`, la roadmap et l'état Git ; préserver la modification locale préexistante de `ROADMAP.md`.
- Respecter les frontières domaine/infrastructure, les traductions et les contrôles de sécurité et CSRF existants ; ne pas introduire de dépendance sans besoin.
- Ne jamais fusionner automatiquement des éléments existants ni synchroniser des copies libres ; conserver la publication, les structures, le portail et les PDF existants.
- Générer la migration avec `make:migration` si possible, relire SQL et `down()`, vérifier l'absence de diff parasite ; l'utilisateur a autorisé son exécution en local le 24 novembre. Ne jamais réécrire une migration déjà appliquée en production ; aucune mise en ligne ni push demandés.
- Tester avant/après : anciennes séquences sans origine, blancs et gestes seuls, sources de répertoire modifiées ou indisponibles, ordre explicite de sélection, suppression de la séance source, détachement et accès portail. Chaque étape ci-dessous précise la vérification associée.
- Aucun nouveau serveur de développement parallèle. Pour les tests backend complets : `docker compose exec -T php php -d memory_limit=1G ./bin/phpunit`.

## 1. Reconnaissance et contrat des données

**Fichiers à examiner :** `src/Domain/Model/Session/SessionSummary.php`, `SessionSequence.php`, `src/Application/Session/{SessionSequenceView,DuplicateSessionSummary}.php`, `src/Infrastructure/Doctrine/Entity/SessionSummaryEntity.php`, son mapping, son mapper et repository ; `src/Application/Controller/SessionSummaryController.php`, formulaires et tests des séances.

- [x] Repérer les points de création, modification, duplication et sérialisation du JSON ; documenter où famille et origine traversent domaine, DTO et persistance, ainsi que les parcours portail/PDF.
- [x] Confirmer les migrations et les contraintes du schéma local ; relever la commande réelle de génération et les outils de tests disponibles.
- [x] Vérifier la sémantique des champs propres à la séance pour une séquence de répertoire : sans consignes locales, `body`, rôle, instruments et médias propres sont omis ; avec consignes, ils sont copiés. Le lien au répertoire reste commun.

## 2. Familles et rattachement

**Fichiers pressentis :** modèle de famille dans `src/Domain/Model/Session/`, repository et adapter associés ; `SessionSummary.php`, `SessionSummaryEntity.php`, mappings/mapper ; migration Doctrine générée ; formulaires et contrôleurs de séance ; traductions `sessions+intl-icu.fr.yaml`.

- [ ] Écrire les tests : créer/renommer une famille et ses tags ; séance sans famille ; rattacher, déplacer et détacher ; conserver contenu, publication et partages ; empêcher une référence vers une famille inexistante.
- [x] Ajouter la persistance d'une relation facultative vers une seule famille et ses tags propres, en réutilisant le catalogue `ThemeEntity`. Garder les séances historiques sans famille ; ne pas propager les tags de famille aux séances.
- [x] Générer puis relire la migration ; tester son `up`, son `down` et le diff de schéma sur une base locale adaptée, sans modifier d'historique déjà livré.
- [ ] Tester la création et le changement de rattachement par HTTP, y compris jeton CSRF invalide et affichage mobile/clavier. Ne pas proposer de suppression de famille dans ce lot.

## 3. Origine des éléments libres et duplication

**Fichiers :** `src/Domain/Model/Session/SessionSequence.php`, `src/Application/Session/DuplicateSessionSummary.php`, mapper et tests `tests/Unit/Application/Session/DuplicateSessionSummaryTest.php`.

Décision d'implémentation : une ancienne séquence sans UUID reçoit à la lecture un UUID déterministe dérivé de la séance et de sa position, sans écriture de masse. Cela permet de la sélectionner entre deux requêtes ; dès qu'elle est enregistrée, son UUID est persisté normalement. Un ancien élément sans origine reste distinct des autres.

- [ ] Écrire les tests : ancienne séquence JSON sans origine lisible ; première copie d'un élément libre utilise son UUID source comme origine ; copies suivantes conservent cette origine et reçoivent chacune un nouvel UUID ; modification indépendante ; suppression de la séance source sans perte.
- [x] Ajouter une origine facultative rétrocompatible au JSON des séquences libres, sans réécriture massive des anciennes séances. Conserver l'origine lors de la sauvegarde d'une séquence et lors de toute nouvelle copie ; ne pas réutiliser le champ de source du répertoire.
- [ ] Tester la duplication d'une séance familiale et sans famille : famille conservée, titre de copie, contenu complet, brouillon, aucune structure rattachée. Les références au répertoire restent identiques.

## 4. Éditeur paroles et gestes ligne par ligne

**Fichiers :** `src/Application/Form/{SessionSequenceType,Model/SessionSequenceFormModel}.php`, `templates/session/{sequence_form,composer_activity_form}.html.twig`, `templates/repertoire/_block_row.html.twig` et `assets/controllers/repertoire_blocks_controller.js` à examiner avant réutilisation ; tests des formulaires et rendus de séance.

- [ ] Écrire les tests d'aller-retour d'un ancien contenu multiligne : retours de début/fin, lignes vides, geste seul, parole seule, espaces et contenus inchangés ; ajouter/supprimer/déplacer une ligne conserve son appariement.
- [x] Concevoir un adaptateur explicite entre champs historiques et lignes éditables, sans appliquer aveuglément la validation du répertoire qui interdit actuellement un geste seul. Les deux champs existants restent canoniques ; les lignes sont assemblées côté navigateur sans migration.
- [ ] Réutiliser les composants et comportements éprouvés quand compatibles ; prendre en charge les deux formulaires d'élément libre et le mobile/clavier. Vérifier aperçu, portail, PDF, duplication et anciennes données.

## 5. Explorer une famille et composer une variante

**Fichiers pressentis :** nouvelles vues/contrôleurs sous `templates/session/` et `src/Application/Controller/`, services sous `src/Application/Session/`, adaptations de `templates/session/index.html.twig`, composants et styles existants, traductions `sessions+intl-icu.fr.yaml`.

- [ ] Tester la liste des familles et des séances sans famille, le détail avec variantes, dates, tags et éléments regroupés par types existants ; traiter familles vides et éléments homonymes sans origine commune.
- [ ] Construire la sélection ordonnée : une fiche de répertoire regroupée par source, choix explicite des consignes locales, versions libres regroupées seulement quand leur origine est connue et sélection d'une version précise. La séance source est indiquée ; les occurrences anciennes sans origine restent séparées.
- [ ] Tester puis créer une séance en brouillon dans la famille : ordre exactement égal à l'ordre de sélection, nouveau titre/date saisis, aucun partage structure automatique, nouvelles occurrences libres indépendantes et lien répertoire maintenu. Tester sélection vide, source supprimée/inaccessible, double soumission et CSRF invalide.
- [ ] Préserver la recherche et les fonctions déjà présentes sur la liste des séances. Vérifier création, duplication, publication séparée, navigation, fermeture des dialogues, écran étroit et clavier.

## 6. Vérifications et livraison

- [ ] Lancer les tests ciblés, puis la suite backend complète ; lancer `make backend-cs-check`, `make backend-stan`, lint Twig/YAML et conteneur, compilation `make symfony-assets`, ainsi que `npm run lint` depuis `jardin-sonore-client/`.
- [ ] Relire les migrations : SQL, `down`, exécution locale autorisée et diff Doctrine sans changements hors sujet ; vérifier aucune migration en attente avant tout déploiement futur.
- [ ] Effectuer une recette authentifiée locale mobile et ordinateur avec données de test : famille vide et existante, déplacement, tags, éléments libres anciens et nouveaux, consignes du répertoire, ordre de sélection, brouillon, portail et PDF. Ne pas créer de données de recette en production sans accord.
- [ ] Revoir les différences Git, mettre à jour la roadmap sans écraser les notes préexistantes ; si la mise en ligne est autorisée, respecter commit, tag et push de la branche et du tag avant déploiement. Vérifier la disponibilité après livraison et indiquer les limites de la recette.

## Suites après le premier lot

Composition par thème à travers plusieurs familles, avec recherche utilisant les tags propres aux familles et ceux de leurs séances ; regroupement manuel éventuel des anciens éléments libres ; ajout d'une sélection à une séance déjà commencée. Ces points restent hors du premier lot.
