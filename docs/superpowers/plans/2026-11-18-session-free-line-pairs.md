# Appariement des paroles et indications libres — Implementation Plan

> **For agentic workers:** utiliser superpowers:executing-plans pour l'exécution dans la session.

**Goal:** afficher les paroles et indications des éléments libres par paires de lignes.
**Architecture:** construire des RepertoireBlockView dans SessionSequenceView::fromDomain pour les textes locaux ; les affichages existants consomment déjà ces blocs.
**Tech Stack:** PHP, Symfony, Twig ; contrat API du portail existant.
**Spec:** cadrage court approuvé dans la conversation le 18 novembre 2026 : conserver les deux champs ; ligne 1 ↔ ligne 1, etc. ; conserver les lignes vides et les lignes sans correspondance ; même rendu que le répertoire sur compositeur, aperçu, portail et PDF.

## Global Constraints

- Deux champs de saisie et format des données conservés, aucune réécriture des données ni modification du répertoire.
- Aucune migration, dépendance ou refonte du front ; conserver les sauts de ligne LF/CRLF/CR.
- Les vrais blocs du répertoire restent prioritaires et inchangés.
- Préserver les notes préexistantes de ROADMAP.md ; l'édition structurée reste une évolution différée.
- Commit, push et déploiement autorisés le 18 novembre après validation ; un seul contrôle HTTP regroupé après livraison.

## Review Focus

- Lignes vides dans une seule colonne : ne pas décaler la suivante.
- Nombre de lignes différent : conserver toutes les paroles et indications.
- Indications seules : afficher leur contenu malgré l'absence de paroles.
- Deux colonnes vides : ne pas créer de panneau sans contenu.
- Source répertoire : conserver sections, pauses et associations explicites.

### Task 1: Projection des textes locaux en blocs de rendu

**Files:** SessionSequenceView.php, SessionSequenceViewTest.php, SessionSequence.php, SessionSequenceType.php et SessionSequenceTypeTest.php dans les répertoires actuels.
**Interfaces:** fromDomain conserve sa signature ; contentBlocks reçoit des RepertoireBlockView LINE ou BREAK calculés sans modifier lyrics/gestures.

- [x] Ajouter des tests par données pour les correspondances, lignes vides, excédentaires, CRLF/CR et champs absents ; vérifier la conservation des textes source.
- [x] Constater les échecs, puis ajouter un helper privé d'appariement et le brancher uniquement au rendu des textes locaux.
- [x] Vérifier la projection et les templates existants, PHPStan, style, Twig, conteneur et lint front.
- [x] Relire le diff, consigner le résultat et mettre à jour la roadmap sans écraser les notes préexistantes.

## Exécution

- Tests vus en échec puis au vert : 14 cas d'appariement, conservation des textes et aller-retour stockage ; test de soumission des deux champs avec lignes de début/fin.
- Choix nécessaire : désactiver le trim de ces deux textarea et préserver leurs retours de ligne dans SessionSequence::fromArray. Sans cela, une première indication vide disparaissait avant le rendu et décalait toute la colonne. Aucun changement du schéma ni de la structure du formulaire.
- Tests ciblés : 68 tests, 398 assertions, succès. Twig : 17 templates valides ; conteneur et lint front valides.
- Revue indépendante : aucun défaut important ; rendu existant partagé confirmé sur compositeur, aperçu, portail et PDF.

- Vérification finale : 683 tests backend, 3558 assertions, succès (2 dépréciations et 4 notices PHPUnit inchangées par rapport au lot précédent) ; PHPStan, style, 17 templates Twig, conteneur et lint front réussis. Diff relu ; aucun changement JavaScript, aucune migration. Travail conservé non committé sur feat/session-free-line-pairs.

- Livraison autorisée : commit, tag deploy-session-free-line-pairs-20261118-01, push main et tag avant déploiement backend sans migration. Vérifications déjà passées conservées ; contrôle après livraison réduit à HTTP site/backend.

- Livré : e23dd2fa, tag deploy-session-free-line-pairs-20261118-01, commit et tag poussés avant déploiement backend réussi sans migration. Contrôle HTTP regroupé site/backend : 200/200. Les notes préexistantes de ROADMAP.md restent hors commit ; aucun nouveau test après déploiement.
