# Dialogue Interlocuteurs et cycle de fermeture — Implementation Plan

> **For agentic workers:** utiliser superpowers:executing-plans dans la session.

**Goal:** remplacer les dépliants d'ajout/rattachement d'interlocuteur par un dialogue fiable.
**Architecture:** conserver les deux formulaires POST et leur protection CSRF ; Stimulus choisit lequel est visible. Le contrôleur internal-dialog suit les événements natifs et ferme ses dialogues lors du cache Turbo ou de sa déconnexion.
**Tech Stack:** Symfony, Twig, Stimulus, AssetMapper, dialog natif.
**Spec:** demande du 18 novembre : réalisation autonome selon les styles communs ; vrai bouton ouvrant un dialogue, sélection d'une personne ou création selon le choix ; Échap/fond/croix/Annuler ne doivent bloquer ni la réouverture ni les autres dialogues de la page.

## Global Constraints

- Réutiliser internal-button, internal-form, internal-form-dialog et les traductions.
- Actions Annuler/Enregistrer côte à côte, champs et cases harmonisés, labels espacés.
- Aucun changement métier, suppression de contact, migration ni dépendance.
- Préserver les notes utilisateur préexistantes de ROADMAP.md ; commit, push et déploiement autorisés le 18 novembre ; contrôle HTTP regroupé après livraison.
- L'utilisateur a demandé l'autonomie ; ne pas ajouter d'étape de validation de conception.

## Review Focus

- Un dialogue invisible resté modal peut intercepter les clics sur toute la page.
- Une fermeture native doit remettre l'état du contrôleur en cohérence sans casser LiveComponent.
- Une navigation Turbo ne doit pas restaurer un dialogue ouvert depuis le cache.
- Les champs requis du formulaire masqué ne doivent pas empêcher l'autre soumission.
- Les deux parcours conservent CSRF, rattachement à la structure et choix du contact principal.

### Task 1: Parcours Interlocuteurs

**Files:** templates/commercial/project/_contacts.html.twig, assets/controllers/commercial_contact_mode_controller.js, translations/commercial.fr.yaml ; test fonctionnel de contacts existant.

- [x] Ajouter et exécuter les assertions de formulaire/dialogue puis implémenter le bouton, les deux choix et les formulaires conservés.
- [x] Vérifier le rattachement et la création avec les protections existantes.

### Task 2: Cycle de vie et recette

**Files:** assets/controllers/internal_dialog_controller.js ; tests navigateur locaux avec les conteneurs existants.

- [x] Reproduire les fermetures natives, cache Turbo et changements de dialogue ; corriger la cause constatée.
- [x] Tester Échap/fond/croix/Annuler, alternance avec les dialogues tâches/notes et retour de navigation, y compris mobile.
- [x] Compiler les assets, valider Twig/YAML/PHP, tests backend adaptés et lint ; revue indépendante ciblée, puis bilan et roadmap.

## Résultats et décisions

- Reproduction réelle avant correctif : après turbo:before-cache, les dialogues de formulaire et de confirmation restaient modaux. Après correction : fermeture native et état open-on-connect synchronisés, fermeture avant cache et lors de la déconnexion.
- Choix : la confirmation hérite du contrôleur commun plutôt que recopier le cycle de vie. Aucun changement de ses données d'action, jeton, texte ni envoi.
- CSS : un formulaire avec hidden restait affiché en grid ; la règle commune internal-form[hidden] corrige ce cas. Les choix réutilisent les styles communs et seul le formulaire actif est activé ; changer de choix conserve la saisie.
- Recette navigateur locale authentifiée : six fermetures/réouvertures (Annuler, croix, fond, close natif, cancel, cache), Échap clavier réel, clic réel sur le fond, alternance tâche/note puis interlocuteurs, reconnexion DOM. Mobile 390×844 : aucun débordement, actions côte à côte, écart label/champ de 20 px. Confirmation fermée avant cache, aucun envoi de suppression.
- Annuaire LiveComponent : ouvrir « Créer une structure », Échap, attendre la fermeture serveur, rouvrir le même dialogue ; succès, puis fermeture sans création. Pas de données créées par la recette navigateur.
- Tests finaux : 683 tests, 3572 assertions, succès avec 4 notices PHPUnit. PHPStan et style passent ; 16 templates Twig et YAML valides ; conteneur valide ; lint front valide ; 82 assets compilés ; syntaxe JavaScript contrôlée.
- Revue indépendante : aucun défaut important. Aucun commit ni déploiement ; pas de migration ni de dépendance. Session navigateur de recette fermée et fichier local d'authentification vidé.

- Mise en ligne autorisée après les validations : commit, tag deploy-dossier-contact-dialogs-20261118-01 et push avant déploiement backend sans migration. Aucun nouveau test complet après livraison.

- Livré : commit ac6eae54, tag deploy-dossier-contact-dialogs-20261118-01, push main et tag avant make deploy-backend CPANEL_BACKEND_RUN_MIGRATIONS=0. Déploiement réussi, 82 assets compilés ; contrôle après livraison regroupé site/backend HTTP 200/200. Aucun nouveau test ni écriture métier en production. Notes utilisateur préexistantes de ROADMAP.md conservées hors commit.
