# Schéma Doctrine et modale de suppression

## Objectif

Rendre le schéma Doctrine local vierge de toute différence, puis finaliser la modale de suppression sans modifier les données métier existantes.

## Schéma

La migration `Version20260723120000` crée les thèmes et leurs liaisons. Le diff Doctrine local révèle aussi des écarts historiques de colonnes UUID et dates, d’index de tables de liaison et d’une colonne de campagne. Une migration de normalisation séparée reprendra exactement le SQL fourni par Doctrine : aucune table, colonne ou donnée ne sera supprimée.

Après exécution locale, `doctrine:schema:update --dump-sql` doit être vide. La migration de normalisation sera relue avant application, puis commitée avec le reste du lot.

## Modale de suppression

La modale globale conserve le contrôleur Stimulus `confirmation-dialog`. La fermeture sera accessible par un bouton croix en haut à droite, par Annuler, Échap et par clic direct sur le fond. Les actions sont alignées horizontalement sur desktop et espacées lorsqu’elles sont empilées sur mobile.

## Vérification et livraison

Les contrôles sont : PHP-CS-Fixer, PHPStan, lint Twig, contrôle des traductions, migration appliquée et diff de schéma vide. La livraison suit ensuite la séquence commit Conventional Commit, tag, push de la branche et du tag, puis `make deploy-backend`.
