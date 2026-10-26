# Taxonomies techniques du backoffice — plan d’implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Gérer les thèmes et tags d’instruments depuis un onglet Technique du backoffice, et retirer les tags de structures/personnes devenus inutiles.

**Architecture:** Réutiliser les routes, tables, formulaires, dialogues et styles du backoffice Symfony métier. Garder les thèmes et tags d’instruments comme vocabulaires distincts, avec une interface de gestion cohérente. Retirer le modèle et la relation de tags d’annuaire, avec une migration de schéma dédiée.

**Tech Stack:** Symfony 8.1, Doctrine ORM, Twig, Symfony UX Turbo/Stimulus, PHPUnit existant.

**Spec:** Décisions formulées dans la demande utilisateur du 26 octobre 2026 et confirmées par l’option recommandée : vocabulaires distincts, tags d’annuaire supprimés.

## Contraintes globales

- Le backoffice métier reste le point d’entrée ; EasyAdmin reste disponible pour les autres fonctions techniques non migrées.
- Les libellés d’interface utilisent les traductions existantes.
- L’ajout et la modification de thèmes et de tags d’instruments se font depuis des dialogues sur l’index, sans page de formulaire séparée.
- Les suppressions sont des actions POST protégées par CSRF et confirmation native `dialog`.
- Une suppression de thème ou de tag d’instrument affiche le nombre d’éléments liés et demande confirmation avant de détacher les relations.
- Ne pas fusionner `Theme` et `InstrumentTag` : les thèmes catégorisent séances, répertoire et médias ; les tags catégorisent les instruments.
- La migration de suppression des tags d’annuaire sera créée et relue, mais ne sera pas exécutée sans confirmation de l’utilisateur.
- Aucun test ne sera ajouté ou exécuté dans cette tâche, sauf demande explicite ultérieure.

## Points de revue

- Un thème lié à plusieurs séances, éléments du répertoire et médias affiche le bon compte avant confirmation de suppression.
- Un tag d’instrument lié à plusieurs instruments affiche le bon compte avant confirmation de suppression.
- Une requête de suppression invalide ou sans jeton CSRF ne modifie aucune donnée.
- Le retrait des tags d’annuaire supprime uniquement `directory_entry_tag` et `tag`, sans toucher aux personnes, structures ou coordonnées.
- Les contrôles de navigation mobile continuent de fonctionner avec un groupe Technique.

---

### Task 1 : Onglet Technique et gestion des thèmes

**Fichiers concernés :**
- Modifier : `jardin-sonore-backend/templates/internal/base.html.twig`, `translations/internal+intl-icu.fr.yaml`.
- Créer : contrôleur, composant de liste, formulaire et fragments Twig pour `Theme` dans les répertoires Application/Form/Twig/templates cohérents avec les catalogues existants.
- Modifier : `jardin-sonore-backend/src/Domain/Repository/ThemeRepositoryInterface.php` et son adapter Doctrine pour les opérations de gestion nécessaires.
- Créer : tests fonctionnels couvrant index, création/édition en dialogue et suppression protégée.

**Interface produite :** index compact avec bouton d’ajout ouvrant un dialogue ; action de modification ouvrant le même dialogue ; action de suppression sur la ligne ; confirmation affichant le nombre de rattachements.

- [ ] Définir les commandes métier minimales de création, modification et suppression d’un thème, avec une lecture de liste comprenant le nombre de rattachements.
- [ ] Écrire les tests de parcours et vérifier l’échec avant implémentation.
- [ ] Ajouter les routes et fragments Turbo/dialog ; conserver l’index comme seule page de gestion.
- [ ] Ajouter le groupe Technique à la navigation interne et y référencer les outils techniques concernés.
- [ ] Vérifier la suppression de thème utilisé et non utilisé ; ne pas exécuter de migration pour ce lot sauf si le schéma l’exige.

### Task 2 : Gestion des tags d’instruments

**Fichiers concernés :**
- Créer : contrôleur, lecture, commandes, formulaire et vues d’index/dialogue adaptés à `InstrumentTag`.
- Modifier : navigation Technique et traductions.
- Créer : tests fonctionnels de création, édition et suppression avec comptage des instruments associés.

**Interface produite :** même ergonomie que les thèmes, mais stockage, identité et vocabulaire restent propres aux instruments.

- [ ] Ajouter un test de suppression qui confirme le détachement explicite des instruments associés.
- [ ] Ajouter les routes d’index, de dialogue sauvegardé et de suppression CSRF.
- [ ] Réutiliser les styles et interactions du mini-écran de thèmes sans fusionner les modèles.
- [ ] Vérifier que l’édition des instruments conserve ses associations existantes.

### Task 3 : Retrait des tags de structures et personnes

**Fichiers concernés :**
- Modifier : `DirectoryEntryEntity`, mapping Doctrine, `OrganizationCrudController`, `PersonCrudController`, filtres et libellés associés.
- Supprimer après confirmation de l’utilisateur si requis par les consignes dépôt : `TagEntity`, son CRUD et les classes de filtre spécifiques devenues inutiles.
- Créer : migration Doctrine supprimant la table de jointure `directory_entry_tag`, puis la table `tag`.
- Créer/mettre à jour : tests de formulaire, annuaire et génération de schéma concernés.

**Interface produite :** aucun champ, filtre, écran ou menu de tags sur les structures et personnes ; données et relations retirées au niveau du schéma.

- [ ] Recenser toutes les références aux tags d’annuaire dans les formulaires, recherches, exports, commandes, MCP et tests.
- [ ] Écrire les tests qui montrent que les écrans et lectures annuaire n’exposent plus ces tags.
- [ ] Retirer le modèle et ses références sans toucher aux tags d’instruments.
- [ ] Générer puis relire une migration limitée à `directory_entry_tag` et `tag`, avec description et `down()` cohérent.
- [ ] Vérifier le diff Doctrine et `git diff --check` ; demander confirmation avant exécution de la migration.

### Task 4 : Vérification et mise à jour documentaire

- [ ] Rechercher les références EasyAdmin restantes et mettre à jour les libellés/documentation devenus faux.
- [ ] Vérifier visuellement les dialogues, leurs états d’erreur, Échap, fermeture et focus selon les comportements déjà utilisés dans le backoffice.
- [ ] Rapporter fichiers modifiés, commandes exécutées, migration en attente et risques connus.
