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

- Vérifier les flux d’upload et de permissions en production après redéploiement.
- Ajouter un vrai aperçu visuel dans EasyAdmin pour les images et fichiers liés.
- Affiner le CRUD EasyAdmin du répertoire pour retrouver la fluidité complète de l’écran interne compact.
- Consolider le script de synchronisation des uploads locaux face aux permissions créées par Docker.
- Préparer un import initial de matière pédagogique dans la BDD depuis les fichiers de séances: instruments, comptines/jeux de doigts et contenus liés avec dédoublonnage.
