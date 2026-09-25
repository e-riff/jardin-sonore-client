# Roadmap

## Livré

- Backoffice interne pour le répertoire, les médias et les prolongements.
- CRUD EasyAdmin pour les comptines/jeux de doigts, les médias et les prolongements.
- Création inline et liaison de médias depuis le répertoire.
- Uploads backend pour médias et prolongements.
- Commande `make sync-backend-uploads` pour rapatrier les uploads de prod vers le dev local.
- Gestion des séances et des séquences dans l'interface Symfony dédiée.
- Écrans EasyAdmin de dépannage disponibles sur les modèles backend en complément.

## Prochaines étapes

- Traiter le [bilan technique backend et front du 25 septembre 2026](docs/bilan-technique-2026-09-25.md), selon le [plan de suites](docs/superpowers/plans/2026-09-25-audit-remediation.md) :
  - en priorité, sécuriser les actions administrateur d'invitation et de réinitialisation ; vérifier la provenance de l'IP utilisée pour limiter les tentatives du portail ;
  - aligner les règles de mot de passe et traiter la réussite partielle possible de l'édition du profil ;
  - consolider les chargements et contrats du portail, puis refactorer progressivement les frontières Doctrine et le contrôleur de séance ;
  - attendre Symfony 8.2 stable pour expérimenter les formulaires sur DTO avec un petit formulaire.
- Compléter la vitrine et le portail selon les priorités du même bilan :
  - **d'abord :** page légale et présentation concrète du portail, de ses séances et ressources dans la partie « En séance » ;
  - **ensuite :** page partenaires, accueil `/portail` utile avec accès aux rubriques et messages aux structures, et messages temporaires publics pilotés depuis le back métier ;
  - **plus tard :** actualités avec sélection de newsletters publiables ;
  - **petite finition :** empêcher le point du menu actif du portail desktop de décaler les liens.
- Ajouter un vrai aperçu visuel dans EasyAdmin pour les images et fichiers liés.
- Affiner le CRUD EasyAdmin du répertoire pour retrouver la fluidité complète de l’écran interne compact.
- Préparer un import initial de matière pédagogique dans la BDD depuis les fichiers de séances: instruments, comptines/jeux de doigts et contenus liés avec dédoublonnage.
- Perfectionner le compositeur de séances avant d’ouvrir le chantier de facturation :
  - refaire l’aperçu métier en déroulé vertical, cohérent avec le compositeur ;
  - gérer plusieurs médias par séquence, avec un média mis en avant et lecteur YouTube intégré ;
  - régénérer un PDF canonique unique après chaque sauvegarde, sans multiplier les versions ;
  - ouvrir ensuite un espace structure dans le front Next.js : liste, lecture HTML et téléchargement PDF ;
  - synchroniser enfin le PDF canonique vers Google Drive.
