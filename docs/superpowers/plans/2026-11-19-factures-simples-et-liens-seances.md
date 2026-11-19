# Factures simples et liens des séances — Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans. Do not commit without the user's request.

**Goal:** Faciliter l'accès aux lieux et suivre les factures à faire puis envoyées sans suivi du paiement pour les nouvelles factures.

**Architecture:** Conserver les préparations mensuelles existantes comme travaux à faire et distinguer les factures historiques avec suivi de paiement des nouvelles factures simples. Réutiliser les mécanismes de validation, sécurité et confirmation MCP, tout en traitant explicitement la résolution immédiate de la préparation associée.

**Tech Stack:** Symfony, Doctrine, Twig, MCP et PHPUnit.

**Spec:** docs/superpowers/specs/2026-11-19-factures-simples-et-liens-seances-design.md

## Global Constraints

- Ne pas modifier les factures historiques ni leurs effets ; aucune relance de paiement pour les nouvelles.
- Exécution de migration uniquement après confirmation explicite conformément à AGENTS.md.
- Ne pas envoyer de mail réel pendant les tests ; préserver les modifications préexistantes de ROADMAP.md.

## Review Focus

- Une préparation mensuelle doit disparaître immédiatement après l'enregistrement ; l'exécution planifiée ne doit pas la recréer.
- Les factures anciennes impayées conservent relances et garde de clôture.
- Une nouvelle facture envoyée ne génère ni relance ni garde de clôture.
- Les écritures MCP vérifient les confirmations, références dupliquées et dates.
- Les liens de carte utilisent une URL encodée et n'apparaissent que si le lieu existe.

### Task 1: Liens des séances
- [ ] Ajouter le nouvel onglet au lien dossier et les liens Maps dans les mails HTML/texte.
- [ ] Vérifier le rendu et l'encodage avec tests ciblés et lint Twig.

### Task 2: Modèle et backoffice
- [ ] Écrire les tests couvrant anciennes/nouvelles factures, préparation immédiatement terminée, résumé et clôture.
- [ ] Étendre l'entité et son mapping ; générer et relire une migration additive réversible.
- [ ] Modifier la saisie pour enregistrer référence, montant, description et mois de prestation ; réutiliser la date d'envoi.
- [ ] Restreindre rappels de paiement et garde de clôture aux anciennes factures, sans altérer leurs données.
- [ ] Exécuter les tests ciblés et vérifier le diff du schéma ; demander confirmation avant d'exécuter la migration.

### Task 3: Connecteur
- [ ] Écrire les tests pour lister les préparations et les factures et enregistrer une facture envoyée, avec contrôle de confirmation et absence de doublon.
- [ ] Implémenter lecture et écriture suivant les conventions MCP existantes ; documenter les outils et leurs paramètres.
- [ ] Lancer les tests MCP, contrôles PHP, Twig et suite backend appropriée.

### Task 4: Livraison
- [ ] Faire une revue du diff et vérifier la recette locale sans envoi réel.
- [ ] Documenter les changements, les commandes exécutées et la proposition de commit.
