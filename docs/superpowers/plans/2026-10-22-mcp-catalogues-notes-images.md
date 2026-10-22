# MCP catalogues, notes et images — plan d'implémentation

> **Exécution :** dans ce workspace, sans sous-agent, sans commit ni exécution de migration. Les consignes du tour interdisent d'ajouter ou de lancer des tests ; utiliser les vérifications statiques adaptées.

**Goal:** Ouvrir les catalogues et les notes de dossier au MCP, relier ou détacher les recommandations du catalogue mailing et envoyer leurs photos comme fichiers Base64 validés.

**Architecture:** Réutiliser les cas d'usage Symfony des catalogues, le rattachement mailing existant et le formateur de notes. Ajouter des adaptateurs MCP typés autour d'eux. Stocker les images décodées après validation dans les dossiers locaux existants. Renommer le chemin du visuel des prolongements avec une migration de colonne.

**Tech Stack:** Symfony, Doctrine ORM/Migrations, MCP JSON-RPC actuel, Twig/EasyAdmin, PHP 8.4.

**Specs:** [Catalogues](../specs/2026-10-22-mcp-catalogues-design.md), [Notes](../specs/2026-10-22-mcp-notes-dossier-design.md).

## Contraintes

- Les écritures MCP exigent la confirmation du client et un verrou serveur ; aucune action ne publie ou n'envoie de mailing.
- Les images sont JPEG, PNG ou WebP, 5 Mo maximum, contrôlées sur les octets décodés.
- Le catalogue mailing et sa copie dans une campagne restent distincts ; une copie liée garde `sourceRecommendationUuid`.
- Ne pas toucher aux URL de contenu des prolongements ni aux anciennes séquences sérialisées.
- Aucun nouveau test ni exécution de tests pour ce tour ; vérifier syntaxe, style, analyse statique et schéma sans appliquer la migration.

## Étapes

### 1. Simplifier le visuel des prolongements

- Renommer `imageUrl` en `imagePath` dans le modèle, l'entité Doctrine, le mapper, les entrées et vues de catalogue.
- Retirer la saisie URL des deux formulaires, préserver l'aperçu du fichier, mettre à jour les traductions et les lecteurs qui recopient le visuel dans une séquence.
- Ajouter une migration `image_url` → `image_path` avec `down()` inverse ; ne pas l'exécuter sans confirmation.

### 2. Ouvrir les catalogues MCP

- Créer un adaptateur pour les cinq catégories, avec recherche bornée, lecture exacte, termes et révision, puis création/modification avec les cas d'usage existants.
- Ajouter les définitions et le routage MCP, avec `MCP_CATALOG_WRITE_ENABLED` pour les catalogues de séance et `MCP_MAILING_WRITE_ENABLED` pour le catalogue mailing.

### 3. Permettre l'envoi des photos

- Ajouter un décodeur Base64 strict et borné, validation réelle Image 5 Mo et fichier temporaire nettoyé.
- Ajouter un outil de rattachement de photo à la fiche prolongement et un autre à la fiche du catalogue mailing, avec révision et nettoyage en cas d'échec.

### 4. Relier et détacher les recommandations d'un mailing

- Exposer `add_catalog_recommendation_to_mailing` et `remove_mailing_recommendation`, avec UUID exact, révision, contrôle de l'état modifiable et cas d'usage métier existants.
- Afficher `sourceRecommendationUuid` dans la lecture d'un mailing. Ne plus créer de recommandation propre à une campagne lors de `create_mailing_draft` et `update_mailing_draft` MCP.

### 5. Notes libres de dossier

- Exposer `create_project_note` sans mail source, avec formatage HTML sûr, vérification de dossier et identifiant de requête pour éviter les doublons. Garder `set_project_appointment_summary` intact.

### 6. Revue et vérifications statiques

- Relire le diff et les outils exposés, contrôler `php -l`, Twig, PHPStan et le style, ainsi que le SQL `up()`/`down()` et le diff Doctrine.
- Mettre à jour `ROADMAP.md` avec ce qui est réellement prêt, les drapeaux de production à activer et la recette Mistral restant à faire.
