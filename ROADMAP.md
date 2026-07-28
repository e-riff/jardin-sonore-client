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

- Ajouter un vrai aperçu visuel dans EasyAdmin pour les images et fichiers liés.
- Affiner le CRUD EasyAdmin du répertoire pour retrouver la fluidité complète de l’écran interne compact.
- Préparer un import initial de matière pédagogique dans la BDD depuis les fichiers de séances: instruments, comptines/jeux de doigts et contenus liés avec dédoublonnage.
- Perfectionner le compositeur de séances avant d’ouvrir le chantier de facturation :
  - refaire l’aperçu métier en déroulé vertical, cohérent avec le compositeur ;
  - gérer plusieurs médias par séquence, avec un média mis en avant et lecteur YouTube intégré ;
  - régénérer un PDF canonique unique après chaque sauvegarde, sans multiplier les versions ;
  - ouvrir ensuite un espace structure dans le front Next.js : liste, lecture HTML et téléchargement PDF ;
  - synchroniser enfin le PDF canonique vers Google Drive.
