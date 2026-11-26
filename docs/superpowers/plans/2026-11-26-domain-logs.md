# Journaux par domaine

Objectif validé : tracer les départs réels et les échecs des mails, synchrones ou issus des workers, dans le var/log du backend, avec rotation quotidienne (30 fichiers).

Architecture : listeners Symfony SentMessageEvent / FailedMessageEvent, attribut WithMonologChannel, sans corps ni jetons. Conserver les domaines existants et ajouter mail et messenger. Production : journal général dès warning, rotation du journal général et des dépréciations. Aucun changement du routage des invitations, aucune migration pour afficher une date dans une fiche.

- [x] Tester les événements réels de transport : succès, échec, métadonnées sans contenu sensible.
- [x] Ajouter le listener et les handlers rotatifs ; préparer var/log au déploiement.
- [x] Vérifier PHPUnit, style, analyse statique et conteneur prod ; documenter les chemins.

Constat production en lecture seule : kernel.logs_dir = /home/riem3079/repositories/jardin-sonore-backend/var/log, répertoire existant et accessible au compte de déploiement. Les redirections des crons sont indépendantes.

Vérifications ciblées : 4 tests, 29 assertions ; PHPStan sans erreur ; PHP CS Fixer sans correction ; listener vérifié dans le cache prod régénéré ; syntaxe bash et git diff --check valides. Suite complète : 758 tests, 4 024 assertions, zéro échec ; 2 dépréciations et 15 notices PHPUnit signalées. Le test d’intégration ajouté après le démarrage de la suite a été exécuté séparément avec les tests de logging (4 tests, 29 assertions). Mise en ligne autorisée par l’utilisateur le 26 novembre ; livraison prévue sous le tag `deploy-domain-logs-20261126-01`, sans migration. Relecture locale du diff effectuée ; ROADMAP.md déjà modifié avant cette tâche, laissé intact.
