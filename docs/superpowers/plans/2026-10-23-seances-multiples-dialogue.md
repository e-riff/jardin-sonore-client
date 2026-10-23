# Création de plusieurs séances depuis un dossier — Implementation Plan

> **For agentic workers:** Plan executed inline in this session; no subagent tooling is available.

**Goal:** Depuis un dossier, préparer plusieurs séances ou options dans un dialogue, les créer dans Google Calendar, puis afficher un bilan précis après le rechargement du dossier.

**Architecture:** Le dialogue Symfony/Twig fournit une première ligne et un contrôleur Stimulus en ajoute ou retire. Une nouvelle route POST traite les lignes une par une, persiste chaque réussite séparément et conserve dans les flashes le résultat global ainsi que les lignes en échec. Le formulaire pleine page existant reste fonctionnel.

**Tech Stack:** Symfony, Twig, Stimulus, Google Calendar gateway existant, traductions YAML.

**Spec:** Flux confirmé par l’utilisateur le 23 octobre 2026 : dialogue sur le dossier, ajout de plusieurs séances/options, envoi groupé, redirection et compte rendu des erreurs et de leurs séances.

## Global Constraints

- Garder les dates et heures saisies par séance, avec les valeurs initiales déjà utilisées : 09:00 et 11:30.
- Garder les suggestions d’adresse du dossier et le choix option/confirmée.
- Continuer le traitement après une erreur Google sur une ligne et conserver les séances déjà créées.
- Ne pas ajouter ni exécuter de tests dans cette tâche.
- Ne pas déployer.

## Review Focus

- Réponse Google échouée au milieu : les autres lignes sont tout de même traitées et les réussites persistent.
- Toutes les lignes invalides ou aucun lien Google : le bilan identifie les séances concernées sans erreur 500.
- Retrait/ajout de lignes : chaque ligne garde des noms de champs et une clé de requête uniques.
- Formulaire historique de création directe : reste compatible avec sa route existante.
- Échappement des titres et détails de flash : rendu Twig échappé et traduisible.

## Files

- Modify `jardin-sonore-backend/templates/commercial/project/_interventions.html.twig`: ouvrir le dialogue depuis l’action d’ajout et afficher le formulaire répété.
- Create `jardin-sonore-backend/templates/commercial/project/_intervention_create_row.html.twig`: champs d’une ligne, incluant date, horaires, adresse, statut et clé de requête.
- Modify `jardin-sonore-backend/src/Application/Controller/CommercialInterventionController.php`: route POST groupée, traitement isolé par ligne et bilan flash.
- Create `jardin-sonore-backend/assets/controllers/commercial_intervention_collection_controller.js`: cloner/supprimer des lignes et attribuer une clé hexadécimale unique.
- Modify `jardin-sonore-backend/translations/commercial.fr.yaml`: libellés et messages de bilan.
- Modify `jardin-sonore-backend/assets/styles/app.css`: présentation compacte et responsive des lignes dans le dialogue.

## Tasks

### Task 1: Dialogue et collection de séances

- Garder l’action « rattacher des séances existantes ».
- Remplacer le lien vers la création unitaire depuis le menu par un bouton qui ouvre un `<dialog>` suivant le pattern `internal-dialog`.
- Rendre une ligne initiale avec valeurs par défaut; ajouter un bouton « Ajouter une autre séance » et une action de retrait sur les lignes supplémentaires.
- Donner à chaque ligne un identifiant de requête unique de 32 caractères hexadécimaux, compatible avec `GoogleCalendarGateway`.
- Laisser le bouton de soumission unique envoyer toutes les lignes vers la route groupée.

### Task 2: Traitement indépendant et bilan

- Ajouter une route POST dédiée, protégée par un token CSRF lié au dossier.
- Valider et traiter chaque ligne séparément, avec `draftFromCreateRequest` adapté aux valeurs indexées.
- Pour chaque succès, créer l’événement Google, persister l’intervention puis `flush()` avant la ligne suivante.
- Capturer les erreurs de validation, conflit et API Google par ligne; poursuivre le lot.
- Rediriger vers le dossier et afficher le nombre de créations, puis pour chaque échec son titre/date et un message traduit.

### Task 3: Finitions et vérification

- Traduire tous les nouveaux libellés et messages via `commercial.fr.yaml`.
- Vérifier Twig, YAML, PHP et le diff whitespace avec les commandes de lint disponibles; ne pas lancer de tests.
- Examiner le diff final pour confirmer que les modifications antérieures du workspace restent intactes.
