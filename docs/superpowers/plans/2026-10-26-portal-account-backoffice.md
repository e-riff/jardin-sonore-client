# Transfert des comptes portail Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Recréer dans l’onglet Technique les écrans EasyAdmin de gestion des comptes portail à comportement constant.

**Architecture:** Ajouter un contrôleur de backoffice, des formulaires Symfony et des templates Twig qui réutilisent les mêmes entités et services que le CRUD actuel. La gestion des invitations, mots de passe et impersonation continue d’utiliser les mêmes gestionnaires et endpoints métier.

**Tech Stack:** Symfony, Doctrine ORM, Symfony Forms, Twig, Stimulus.

**Spec:** `docs/superpowers/specs/2026-10-26-portal-account-backoffice-design.md`

## Global Constraints

- Aucun changement de comportement métier, de données ou de règles de sécurité.
- Aucun transfert des comptes administrateurs internes.
- Aucun changement de schéma Doctrine.
- Tout texte d’interface suit les domaines de traduction du backend.
- Le protocole de recette locale est livré dans un fichier Markdown avec cases à cocher.

## Review Focus

- Création liée à une adresse existante ou ajoutée à la structure / personne : garder les mêmes associations et l’invitation.
- Adresse e-mail déjà liée à un compte portail : ne pas créer de doublon ; le parcours actuel doit être conservé.
- États pending/active/inactive : afficher les mêmes actions admissibles et conserver les POST protégés par CSRF.
- Accès multiples : mêmes formulaires et même suppression de collection que dans EasyAdmin.
- Échec SMTP et lancement d’impersonation : réutiliser exactement les services et le rendu de lancement existants.

---

### Task 1: Écrans métier des comptes portail

**Files:** contrôleur et types de formulaires sous `jardin-sonore-backend/src/Application`, vues sous `jardin-sonore-backend/templates/technical/portal_user`, traductions backend.

- [x] Recréer l’index avec tri e-mail, recherche, état, préférence de notification et résumé des accès actifs.
- [x] Recréer création, détail et édition en reprenant les champs et possibilités de la configuration EasyAdmin actuelle.
- [x] Reprendre l’autocomplete des structures, le filtrage des e-mails de contact et les champs de création d’un contact/personne.
- [x] Reprendre les actions POST d’invitation, de réinitialisation, d’impersonation et de suppression avec leurs protections.
- [x] Reprendre le remplacement et la suppression de la photo de profil.
- [x] Brancher la navigation Technique et enlever le lien EasyAdmin vers Comptes portail.

### Task 2: Protocole de recette locale

**Files:** `docs/recettes/gestion-comptes-portail-local.md`.

- [x] Ajouter les scénarios d’index, création, accès multiples, invitation, SMTP, réinitialisation et impersonation sous forme de cases à cocher.
- [x] Indiquer les prérequis locaux et les résultats attendus sans exécuter les parcours.

### Task 3: Vérification de livraison

- [x] Relire le diff pour confirmer qu’il ne contient aucune modification de données ou de règles métier.
- [x] Lancer les contrôles statiques/lint appropriés, sans exécuter la suite de tests automatisés.
- [x] Vérifier qu’aucune entité ou mapping Doctrine n’a été modifié, donc qu’aucune migration n’est requise.
