# Pièces jointes mail MCP — Implementation Plan

> **For agentic workers:** utiliser superpowers:executing-plans pour exécuter ce plan dans la session.

**Goal:** compléter les outils mail MCP existants pour les pièces jointes.
**Architecture:** réutiliser MailDraftAttachment, les constructeurs MIME et les protections de révision et de dédoublonnage existants.
**Tech Stack:** PHP 8.5, Symfony, PHPUnit, IMAP et transport Symfony Mailer.
**Spec:** ../specs/2026-11-18-mcp-mail-attachments-design.md — approuvée le 18 novembre.

## Global Constraints

- 10 fichiers, 5 Mio par fichier, 10 Mio au total ; filename/mimeType/base64.
- Aucun chemin serveur ni téléchargement arbitraire d'URL ; aucun envoi réel. Déploiement demandé après validation, avec contrôle HTTP/MCP bref.
- Conserver les fichiers et destinataires omis ; vérifier la révision avant remplacement.
- Ne pas changer les empreintes historiques des demandes sans pièce jointe.
- Ne pas toucher aux modifications préexistantes de ROADMAP.md ; commit, tag et push autorisés pour la mise en ligne demandée.

## Review Focus

- Une relance avec fichier changé ne doit pas renvoyer une notification.
- Une édition interrompue ne doit pas réutiliser un remplacement aux fichiers différents.
- Une réponse à tous avec fichier conserve To/Cc et le contexte de fil.
- Une demande invalide ne doit pas réserver une opération ni toucher IMAP/SMTP.
- Les appels historiques sans pièce jointe conservent leur dédoublonnage.

### Task 1: Création de réponses et envois directs

**Files:** MailDraftAttachment.php, MailDraftWriter.php, SaveMailDraft.php, ReplyDraftMimeBuilder.php, PersonalNotificationSender.php dans leurs répertoires actuels ; tests unitaires associés.
**Interfaces:** save(..., bool $replyAll = false, array $attachments = []) ; build(..., bool $replyAll = false, array $attachments = []) ; fromPayloads(mixed $payloads): array produit une liste de MailDraftAttachment validés.

- [x] Ajouter les tests MIME de réponse à tous avec fichier, validation avant réservation, répétition de création et notifications avec fichiers modifiés/retirés/réordonnés.
- [x] Lancer les tests ciblés et constater les échecs attendus.
- [x] Implémenter validation commune de liste, attachement MIME et empreinte incluant les métadonnées empreintées des fichiers ; conserver l'empreinte historique sans fichier.
- [x] Relancer les tests et vérifier le succès.

### Task 2: Édition et contrat MCP

**Files:** MailDraftEditor.php, UpdateMailDraft.php, WriteToolRegistry.php et leurs tests.
**Interfaces:** update(..., ?string $htmlBody = null, array $attachments = [], array $removeAttachmentIds = []) ; les paramètres MCP restent facultatifs.

- [x] Ajouter les tests d'ajout/retrait avec conservation des autres fichiers, des To/Cc et des références, conflit de révision et reprise d'édition avec fichiers différents ; tester schémas et passage des paramètres MCP.
- [x] Lancer les tests et constater les échecs attendus.
- [x] Transmettre les paramètres aux services, appliquer les changements par l'éditeur général et inclure les fichiers dans la clé de reprise ; valider avant réservation/écriture.
- [x] Relancer les tests ciblés.

### Task 3: Documentation et vérification

**Files:** docs/mcp-mail-tools.md, conception et présent plan.

- [x] Mettre à jour les descriptions MCP et la documentation des parcours, limites et rafraîchissement des outils.
- [x] Lancer PHPUnit complet, style PHP, PHPStan et lint du conteneur Symfony ; aucun changement front.
- [x] Faire une revue finale du diff ; consigner résultats et limites. Committer, taguer, pousser branche et tag puis déployer le backend ; vérifier HTTP et les schémas MCP.

## Journal d'exécution

- Branche de travail : feat/mcp-mail-attachments ; dépendances locales et conteneur existants réutilisés.
- Vérification préalable : 36 tests ciblés, 176 assertions, succès. État préexistant : ROADMAP.md modifié par l'utilisateur.
- Choix : exécution dans le checkout existant sur branche dédiée afin de réutiliser le conteneur PHP monté sur ce dossier ; aucun autre serveur ni copie de secrets.

- Task 1 : tests vus en échec puis succès (28 tests, 121 assertions).
- Task 2 : tests vus en échec puis succès (23 tests, 178 assertions), reprise et conflit de fichiers vérifiés.
- Demande complémentaire : déployer, limiter les vérifications après livraison, fournir un message prêt à transmettre à l’IA.

- Vérifications avant livraison : 659 tests, 3434 assertions, succès (2 dépréciations et 4 notices PHPUnit) ; PHPStan, style, conteneur Symfony, Composer, 81 assets compilés et lint front réussis. Aucun nouveau serveur ni migration.

- Revue indépendante : trois points importants corrigés par tests RED→GREEN : noms de fichiers altérés par le parseur IMAP, fichiers vides ignorés par la bibliothèque, objets JSON acceptés comme listes. Tests de relecture MIME réelle et entrée HTTP ajoutés (34 tests ciblés, 187 assertions).

- Validation finale après corrections de revue : 668 tests, 3471 assertions, succès (2 dépréciations, 4 notices PHPUnit) ; PHPStan et style passent. Aucun point important laissé ouvert. Les dernières corrections ont un test observé en échec puis au vert ; pas de seconde revue exhaustive.

- Livraison : commit afab252a intégré en avance rapide à main, branche et tag deploy-mcp-mail-attachments-20261118-01 poussés avant make deploy-backend CPANEL_BACKEND_RUN_MIGRATIONS=0. Succès ; HTTP 200 site/MCP, 53 outils avec attachments/replyAll/removeAttachmentIds. Aucun mail/brouillon de recette. Les notes utilisateur préexistantes de ROADMAP.md restent hors commit.
