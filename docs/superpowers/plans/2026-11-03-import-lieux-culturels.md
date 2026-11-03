# Import des lieux culturels Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox syntax for tracking.

**Goal:** Préparer et reprendre un import par département avec rapprochement sûr et sources visibles.

**Architecture:** Préparer un fichier local figé issu de l'API tabulaire ; enregistrer les décisions au fil de l'eau ; appliquer les décisions explicitement avec contrôle des liens externes et conservation des données existantes.

**Tech Stack:** PHP 8.4+, Symfony Console, Doctrine, Twig, PHPUnit.

**Spec:** docs/superpowers/specs/2026-11-03-import-lieux-culturels-design.md

## Global Constraints

- Aucun changement de production ni import effectif avant clarification de licence.
- Préserver les informations existantes et les désactivations ; les nouvelles adresses suivent le choix newsletter demandé.
- Aucune migration sans confirmation pour son exécution.

## Review Focus

- API paginée, tableau JSON encodé en chaîne et identifiants corses : tests de conversion et pagination.
- Plusieurs candidats, champs manquants et reprise : tests de décision persistante et comparaison.
- Relance après écriture partielle : tests d'idempotence et de lien externe.
- Adresse désactivée, partagée ou désabonnée : tests de non-réactivation.
- Données locales modifiées après revue : contrôle avant application et refus des décisions périmées.

### Task 1: Source et fichier de travail

- [x] Tester puis implémenter la récupération paginée, le filtre département et catégories, les priorités jeunesse et la persistance atomique du fichier.
- [x] Tester la reprise et le refus d'une page incomplète ; les erreurs HTTP interrompent la préparation avant écriture du fichier.

### Task 2: Revue et application

- [x] Tester puis implémenter la comparaison tabulaire et les décisions persistantes.
- [x] Tester puis implémenter l'application idempotente, les contrôles entre revue et application et les règles de conservation des contacts.

### Task 3: Sources visibles

- [x] Tester puis afficher les sources liées et leurs identifiants sur la fiche de l'annuaire.

### Task 4: Vérification

- [ ] Exécuter les tests ciblés, la suite backend, PHPStan, style, Twig et compilation des assets.
- [x] Documenter la procédure et les limites de licence et préparer une simulation sans écriture.
