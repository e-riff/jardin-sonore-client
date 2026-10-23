# Sélecteurs de structures Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Afficher « Nom — code postal — ville » et chercher sur ces trois informations dans les sélecteurs de structures du backoffice et d'EasyAdmin.

**Architecture:** Centraliser le libellé et les critères de recherche métier. Garder les widgets Symfony UX Autocomplete, TomSelect spécifique et EasyAdmin déjà installés ; les brancher sur la même représentation, sans un contrôleur HTTP artificiellement commun.

**Tech Stack:** Symfony Forms, Symfony UX Autocomplete/TomSelect, EasyAdmin, Doctrine, PHPUnit.

**Spec:** [Conception](../specs/2026-10-23-simplification-seances-medias-design.md)

## Global Constraints

- Le code INSEE identifie la commune et ne remplace pas le code postal affiché.
- Respecter les filtres métier et droits de chaque écran ; ne pas élargir les résultats disponibles.
- Garder les choix déjà enregistrés compatibles ; ne pas ajouter de dépendance.
- Ne faire aucun commit sans demande ou accord explicite de l'utilisateur.

## Review Focus

- Deux structures de même nom mais communes différentes restent distinctes (Task 1).
- Plusieurs adresses actives ne produisent pas de doublons dans les résultats (Task 1).
- Code postal ou commune absents : le libellé se dégrade proprement et la structure reste recherchable par nom (Task 1).
- Les résultats EasyAdmin et du backoffice utilisent le même libellé sans changer les valeurs soumises (Task 2).
- Une recherche par code postal retourne la bonne structure dans chaque mécanisme d'autocomplétion (Task 2).

---

### Task 1: Représentation et recherche communes

**Files:**
- Create: `jardin-sonore-backend/src/Application/Directory/OrganizationChoiceLabel.php` pour formater nom, code postal et ville depuis une structure ou des valeurs déjà lues.
- Modify: `jardin-sonore-backend/src/Application/Form/OrganizationAutocompleteType.php`, `src/Infrastructure/Mailing/DoctrineNewsletterAudienceOptionsQuery.php`, `src/Infrastructure/Doctrine/Entity/DirectoryEntryEntity.php` seulement si la méthode de localisation actuelle ne suffit pas.
- Test: `jardin-sonore-backend/tests/Functional/Application/Form/OrganizationAutocompleteTypeTest.php`, tests du query mailing existants ou nouveau test ciblé.

**Interfaces:** `OrganizationChoiceLabel::format(string $name, ?string $postalCode, ?string $city): string` donne le même libellé à tous les widgets ; l'UUID ou l'ID reste la valeur du choix.

- [ ] **Step 1:** Ajouter des tests en échec pour nom/CP/ville, valeurs manquantes, noms identiques et plusieurs adresses ; la recherche sur chaque morceau renvoie une fois la structure.
- [ ] **Step 2:** Lancer les tests ciblés et confirmer l'échec.
- [ ] **Step 3:** Implémenter le formatage commun et intégrer code postal et ville aux filtres Doctrine avec paramètres échappés, dédoublonnage par structure et ordre stable. Choisir l'adresse active pertinente selon la règle actuelle, sans inventer une localisation supplémentaire.
- [ ] **Step 4:** Relancer les tests ciblés et confirmer leur réussite.

### Task 2: Brancher les sélecteurs du backoffice et EasyAdmin

**Files:**
- Modify: `jardin-sonore-backend/src/Application/Form/MailingAudienceType.php`, `src/Application/Controller/MailingController.php`, `src/Infrastructure/Admin/OrganizationCrudController.php`, `src/Infrastructure/Admin/UserCrudController.php`, `src/Infrastructure/Admin/UserOrganizationAccessCrudController.php`, `src/Infrastructure/Admin/PersonCrudController.php`, et les autres champs d'association à `OrganizationEntity` trouvés par inventaire `rg`.
- Modify: `jardin-sonore-backend/assets/controllers/organization_autocomplete_controller.js` seulement si le format des réponses l'exige.
- Test: `jardin-sonore-backend/tests/Functional/Application/Form/MailingAudienceTypeTest.php`, `tests/Unit/Infrastructure/Admin/UserOrganizationAccessCrudControllerTest.php` et un test fonctionnel de l'endpoint portail.

**Interfaces:** Les endpoints existants gardent leurs contrats respectifs ; ils utilisent le libellé de Task 1 et cherchent nom, CP et ville.

- [ ] **Step 1:** Faire l'inventaire des sélecteurs et ajouter des tests en échec pour les libellés et recherches de chaque famille de widgets.
- [ ] **Step 2:** Lancer les tests ciblés et confirmer l'échec.
- [ ] **Step 3:** Brancher UX Autocomplete, endpoint TomSelect et associations EasyAdmin sur la représentation commune, avec les mêmes critères de recherche et leurs restrictions métier actuelles.
- [ ] **Step 4:** Lancer les tests ciblés, `composer cs-check`, `composer stan`, puis vérifier manuellement un sélecteur de chaque famille et `git diff --check`.
- [ ] **Step 5:** Reporter les fichiers modifiés, commandes et résultats ; proposer un conventional commit sans le créer.
