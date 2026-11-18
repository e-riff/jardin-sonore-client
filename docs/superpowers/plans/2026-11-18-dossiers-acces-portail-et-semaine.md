# Dossiers : accès portail et semaine — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Alléger l'accueil des dossiers, retrouver les séances de la semaine et donner accès au portail depuis un dossier.

**Architecture:** Réutiliser la file commerciale et son LiveComponent pour les vues ; ajouter une requête bornée sur les interventions locales synchronisées. Réutiliser les services portail dans une commande applicative appelée depuis un formulaire Symfony du dossier.

**Tech Stack:** Symfony, Doctrine ORM, Twig, LiveComponent, Stimulus existant, AssetMapper.

**Spec:** [Conception approuvée](../specs/2026-11-18-dossiers-acces-portail-et-semaine-design.md)

## Global Constraints

- Libellés traduits ; aucune dépendance nouvelle.
- Pas de commit ni déploiement sans demande ; préserver la modification préexistante de ROADMAP.md.
- Pas d'envoi réel pendant les tests ; pas de serveur supplémentaire.

## Review Focus

- Un validé avec rappel doit rester dans la file des actions.
- Les bornes de semaine sont calculées à Paris, avec fin exclusive lundi suivant.
- Les séances annulées et sans dossier ne figurent pas dans l'aperçu.
- Un compte ou accès désactivé ne doit pas être réactivé implicitement.
- Une seconde validation ne crée ni doublon ni nouvelle invitation.

### Task 1: Accueil et vue des validés

**Files:** CommercialProjectQueue.php, CommercialProjectTable.php, CommercialInterventionDoctrineRepository.php, CommercialProjectController.php, templates commercial/project/index.html.twig et components/CommercialProjectTable.html.twig, traductions et CSS communs ; tests CommercialProjectQueueTest.php et CommercialProjectControllerTest.php.

**Interfaces:** `CommercialInterventionDoctrineRepository::forWeek(DateTimeImmutable $now): array` fournit les interventions liées, non annulées, triées par date ; la vue confirmée utilise `status=confirmed`.

- [ ] Ajouter puis exécuter des tests couvrant les validés avec/sans rappel et les bornes de semaine.
- [ ] Corriger le classement et masquer le groupe confirmé uniquement en vue active.
- [ ] Ajouter la navigation dédiée et le tableau de semaine compact avec liens couvrant les lignes.
- [ ] Exécuter les tests concernés : résultat attendu PASS.

### Task 2: Accès au portail depuis le dossier

**Files:** nouveau GrantOrganizationPortalAccess.php, nouveau CommercialProjectPortalAccessType.php, CommercialProjectController.php, nouveau partiel _portal_access.html.twig, traductions ; nouveau test fonctionnel CommercialProjectPortalAccessTest.php.

**Interfaces:** `GrantOrganizationPortalAccess::grant(OrganizationEntity $organizationEntity, string $emailAddress): string` retourne le suffixe du message de succès ; utilise les invitations existantes seulement pour un nouveau compte.

- [ ] Ajouter puis exécuter des tests de création, rattachement existant, répétition, adresses invalides, refus des comptes/accès inactifs et CSRF.
- [ ] Ajouter le formulaire avec choix connu ou email libre ; restreindre les choix à la structure active.
- [ ] Ajouter le dialogue du dossier, rouvert en cas d'erreur, et la commande sans doublon.
- [ ] Exécuter les tests concernés : résultat attendu PASS.

### Task 3: Vérification et restitution

- [ ] Lancer PHPUnit complet avec memory_limit=1G, contrôles de style/PHPStan, lint Twig et compilation AssetMapper.
- [ ] Vérifier le rendu via le navigateur existant si accessible, ordinateur et mobile.
- [ ] Relire le diff et mettre à jour la roadmap sans écraser les changements présents.
- [ ] Restituer les fichiers modifiés, commandes, résultats et proposition de commit.

Pre-flight: les tâches 1 et 2 partagent le contrôleur et les traductions ; exécution séquentielle dans la session autorisée.
