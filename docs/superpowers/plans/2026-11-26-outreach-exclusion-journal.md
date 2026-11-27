# Exclusion des démarchages et journal — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Espacer les démarchages par établissement et rendre leur historique consultable.

**Architecture:** Un service de sélection applique la fenêtre aux snapshots ciblés, puis un contrôle partagé entre workers refait la vérification avant SMTP. Le journal lit les mêmes traces sans reconstruction depuis l’annuaire courant. La file conserve ses contrats existants, enrichis des raisons d’annulation.

**Tech Stack:** PHP/Symfony, Doctrine DBAL et ORM, MySQL, Messenger, Twig et styles communs ; aucune dépendance nouvelle prévue.

**Spec:** [Conception](../specs/2026-11-26-outreach-exclusion-journal-design.md).

## Global Constraints

- Exclusion par campagne ; newsletters hors règle ; aucun ancien envoi mixte classé rétroactivement.
- Adresse partagée : un seul établissement ciblé exclu suffit à bloquer l’adresse entière, décision utilisateur.
- Défaut proposé : six semaines, zéro désactive ; valeur entière non négative. Nouvelle campagne démarchage : défaut six ; campagnes historiques déjà typées : zéro jusqu’à choix explicite, afin de ne pas modifier un envoi en cours.
- Compter `status=sent` et `sent_at` réel des autres campagnes typées outreach ; même campagne exclue du calcul pour préserver ses contacts multiples et extensions.
- Une fenêtre est récente si `sent_at > now - N semaines` ; à la frontière exacte, elle ne bloque plus.
- Désabonnement global préservé. Aucun changement d’audience newsletter, de consentement ou de suivi des réponses.
- Textes dans les traductions mailing ; nouveaux fichiers PHP stricts et conventions existantes.
- Migration générée, relue, puis exécutée uniquement après accord. Aucun commit ou déploiement supplémentaire sans instruction.

## Review Focus

1. Deux messages identiques ou deux campagnes concurrentes : une ligne terminale n’est pas renvoyée et le contrôle d’historique est sérialisé (tâche 3).
2. Adresse multi-établissements : bloquée même si un autre établissement reste éligible (tâche 1).
3. Plusieurs contacts d’une campagne et extension : les envois de cette même campagne ne se bloquent pas entre eux (tâches 1/2).
4. Désabonnement pendant l’attente : vérifié avant SMTP, avec raison distincte de l’exclusion (tâche 3).
5. Annuaire modifié et regroupements : journal sur snapshots, adresses et établissements distincts comptés séparément (tâche 5).

---

### Tâche 1 : calcul d’exclusion historique

**Files:** créer `src/Application/Mailing/OutreachHistoryInterface.php`, `OutreachAudiencePolicy.php`, `OutreachAudienceSelection.php`, `src/Infrastructure/Mailing/DoctrineOutreachHistory.php` ; tests `tests/Unit/Application/Mailing/OutreachAudiencePolicyTest.php`, `tests/Integration/Infrastructure/Mailing/OutreachHistoryTest.php` (racine backend).

**Interfaces:** `OutreachHistoryInterface::findRecentlyContactedOrganizationUuids(string $excludedCampaignUuid, DateTimeImmutable $since): array` retourne une liste d’UUID RFC4122 ; `OutreachAudiencePolicy::select(MailingCampaign $campaign, array $recipients, DateTimeImmutable $now): OutreachAudienceSelection` retourne `recipients` et `excludedRecipientCount`, typés et immuables.

- [x] Écrire les tests : newsletter et zéro inchangés ; outreach filtre uniquement organisations ciblées ; adresse partagée bloquée ; échec/pending/ancienne campagne non typée ignorés ; frontière exacte autorisée ; campagne courante ignorée ; adresses sans snapshot conservées avec limite explicitée (aucun établissement connu à protéger).
- [x] Lancer les tests ciblés et constater les échecs avant implémentation.
- [x] Implémenter la lecture DBAL avec UUID binaire converti explicitement, jointure destinataire/campagne/snapshot et conditions composées ; aucune lecture des relations vivantes.
- [x] Implémenter la politique déterministe avec horloge passée en argument et vérifier les tests.

### Tâche 2 : délai persistant et préparation

**Files:** modifier `MailingCampaign`, entité/mapper/mapping correspondants, `SendMailingCampaign`, `ExtendMailingCampaignAudience`, `ExtendMailingCampaignAudienceResult`, `MailingController`, `templates/mailing/send.html.twig`, `audience_extend.html.twig`, traductions ; créer modèle et type de formulaire de réglage dédiés, migration via commande ; étendre tests existants.

**Interfaces:** `MailingCampaign::getOutreachExclusionWeeks(): int`, `setOutreachExclusionWeeks(int $weeks): void` (modifiable avant démarrage seulement). Paramètre optionnel final au constructeur pour préserver les appels existants. Initialisation explicite six pour nouvelles campagnes outreach via création, zéro par défaut en hydratation historique. Résultat d’extension ajoute `excludedRecipientCount` sans changer les compteurs de doublons existants.

- [x] Tests RED : persistance du délai ; négatif refusé ; modification après mise en file refusée ; récapitulatif avant/après exclusions ; mise en file et extension appliquent la même politique ; tous exclus donne une erreur métier claire ; contacts de la même campagne préservés.
- [x] Implémenter réglage sur préparation avec POST/CSRF sans déclencher d’envoi ; afficher zéro comme désactivé. Au POST d’envoi, utiliser le délai persisté et recalculer la sélection complète, pas l’échantillon aperçu. Le champ n’apparaît que pour outreach.
- [x] Réutiliser la politique dans aperçu/préparation/extension ; différencier total ciblé, déjà rattaché, exclus et nouveaux retenus.
- [x] Exposer le délai en lecture MCP ; toute modification MCP reste derrière verrou et révision existants, valeur validée, omis signifie préservé.
- [x] Générer la migration avec `make:migration`, supprimer tout SQL parasite, ajouter description et relire up/down. Ne pas l’appliquer sans accord.
- [x] Vérifier tests ciblés, schéma SQL attendu et compatibilité des campagnes anciennes.

### Tâche 3 : contrôle au dernier moment et coordination

**Files:** modifier `DoctrineMailingDeliveryQueue`, `MailingDeliveryQueueInterface`, `DispatchPendingMailingCampaignsCommand`, `SendMailingCampaignRecipientMessageHandler` ; créer `MailingDeliveryCoordinatorInterface`, adapter MySQL et tests d’intégration/concurrence ; migration ajoute `cancellation_reason` nullable à la ligne de livraison.

**Interfaces:** `MailingDeliveryCoordinatorInterface::run(string $campaignUuid, int $recipientId, callable $operation): mixed` prend toujours un verrou par destinataire, puis un verrou global outreach si nécessaire, libère en finally ; callbacks typés via PHPDoc générique. Le worker recharge ligne/statut/snapshots via `findDeliveryRecipient(int $id)` qui retourne un DTO dédié nullable, et vérifie campagne + adresse du message. `markCancelled(int $id, ?string $reason = null)` préserve appels existants ; raisons contrôlées `unsubscribed`, `recent_outreach`, `campaign_stopped`.

- [x] RED : double traitement du même message ; deux campagnes pour une organisation ; verrou indisponible ; statut sent/cancelled ; campagne arrêtée dans la file ; désabonnement récent ; même campagne multi-contact ; collision deux dispatchers.
- [x] Revendiquer les lignes pending atomiquement dans une transaction courte avec verrouillage SQL compatible MySQL ; mise à jour conditionnelle pending uniquement. Coordonner aussi le calcul de capacité entre dispatchers pour préserver le plafond existant.
- [x] Adapter de coordination : verrous nommés MySQL `GET_LOCK` sur connexion dédiée stable pendant l’opération et libération explicite. Attente bornée ; indisponibilité provoque un report Messenger récupérable, jamais un marquage échec définitif ni un envoi sans contrôle. Ordre constant destinataire puis global outreach ; ce dernier partagé même par les campagnes délai zéro pour que leur succès soit visible aux autres.
- [x] Sous verrou : recharger état, ignorer ligne terminale, vérifier arrêt et désabonnement, reconstruire destinataire depuis snapshots ; politique avant SMTP ; exclusion marque cancelled avec raison. Envoyer puis markSent avant libération. Newsletter prend seulement le verrou destinataire.
- [x] Ne pas englober SMTP dans une transaction SQL. Une acceptation SMTP suivie d’une perte du processus reste une incertitude : aucune reprise automatique aveugle des lignes processing orphelines ajoutée dans ce lot.
- [x] Tester la concurrence avec deux connexions DB indépendantes ; vérifier libération des verrous lors des exceptions. Préserver compteurs et notification de fin quand tout le reste est annulé.

### Tâche 4 : raisons et bilan d’exclusion

**Files:** suivi/rapport existants et adaptateurs de lecture mailing, templates `mailing/send.html.twig`, `mailing/report.html.twig` selon routes existantes, traductions et tests HTTP.

- [x] RED : une exclusion après mise en file apparaît comme « exclu : démarchage récent », pas comme erreur ou désabonnement ; compteur distinct sans double décompte.
- [x] Implémenter libellés accessibles, explication des adresses partagées et rappel que prévision avant envoi peut changer. Les anciennes annulations null gardent un libellé générique.
- [x] Vérifier le parcours HTTP complet sans SMTP réel et les notifications de fin de campagne.

### Tâche 5 : journal consultable

**Files:** créer `src/Application/Mailing/Query/OutreachJournalFilter.php`, `OutreachJournalPage.php`, `OutreachJournalReaderInterface.php`, `src/Infrastructure/Mailing/DoctrineOutreachJournalReader.php`, `src/Application/Controller/OutreachJournalController.php`, `templates/mailing/outreach_journal/index.html.twig` ; navigation interne et traductions ; tests intégration/HTTP dédiés.

**Interfaces:** `OutreachJournalReaderInterface::search(OutreachJournalFilter $filter): OutreachJournalPage` ; filtre typé : UUID établissement/campagne nullable, type historique nullable, dates début/fin nullable, page >=1 ; page de 50 résultats maximum, ordre date réelle décroissante avec identifiant stable en second tri. Résultat expose campagnes, dates, états, établissements/noms/types/communes historiques et adresses, compteurs distincts.

- [x] RED : autorisation admin ; filtres combinés ; type partiellement ciblé ; deux contacts/une structure ; adresse partagée/deux structures ; changement de type/nom live sans effet ; pagination stable et validation dates inversées.
- [x] Implémenter lecture sur snapshots et campagnes typées uniquement. Synthèse par campagne/type : dernière date effective, nombre d’établissements distincts et d’adresses distinctes ; dates et périmètres explicités. Détail permet états non envoyés, séparés du bilan envoyé.
- [x] Interface utilisant tableau commun, filtres, pagination et actions compactes ; lien depuis mailing et lien contexte établissement vers journal filtré. Aucune création de segment ni familles.
- [x] Vérifier rendu Twig/HTTP, mobile et clavier sur serveur existant sans envoi réel.

### Tâche 6 : validation et livraison préparée

- [x] Regrouper les ajouts SQL du lot dans une seule migration avant application si aucune version intermédiaire n’a été appliquée ; demander accord avant toute suppression de fichier.
- [x] Présenter SQL final et demander accord d’exécution local/test ; vérifier up et schéma synchronisé. Retour arrière relu et simulé ; exécution destructive sur base jetable non réalisée.
- [x] Lancer `docker compose exec -T php php -d memory_limit=1G ./bin/phpunit`, `make backend-stan`, `make backend-cs-check`, lint Twig/conteneur et `make symfony-assets` si assets modifiés.
- [x] Revue finale indépendante selon le mode direct déjà choisi ; résoudre les défauts importants puis répéter les vérifications justifiées.
- [x] Actualiser roadmap et limites SMTP. Aucun envoi réel de recette ; commit/tag/push/déploiement seulement sur demande explicite.

## Auto-relecture

Les six tâches couvrent sélection, persistance, coordination, restitution et journal. Signatures productrices et consommatrices alignées ; les cinq risques de revue possèdent chacun un test propriétaire. Aucun ancien envoi reclassé ; aucune préférence distincte ajoutée. Points nouveaux proposés : défaut six semaines configurable, verrou global outreach MySQL, taille de page 50. Les raisons d’annulation appartiennent à la migration finale, pas à une migration déjà livrée.

## Passage à l’exécution

Mode conservé : exécution directe dans ce dossier, puis revue indépendante finale. Plan prêt pour relecture utilisateur avant implémentation. La règle des adresses partagées est validée ; aucune modification du code de ce lot n’a commencé.

## Suivi d’exécution

Plan approuvé, mode direct conservé. Implémentation locale sur `feat/newsletter-outreach-lot-1`, sans commit ni mise en production du nouveau lot.

Tâches 1–5 implémentées : sélection/persistance, réglage web et MCP, filtrage/extension et récapitulatifs, file protégée et contrôle avant SMTP, journal historique. Le réglage général vient de l’environnement ; chaque campagne fige sa valeur. Les tests fonctionnels vérifient que sauvegarder le délai n’envoie rien.

Tâche 6 : migration générée `Version20261126195323`, SQL limité à deux colonnes, appliquée en développement et test après accord explicite. Schéma valide, nouvelle génération vide ; down relu et simulation réussie, pas de rollback destructif exécuté. Aucun asset source modifié : réutilisation du JavaScript et du CSS existants.

Revue indépendante : aucun Critical ; quatre Important corrigés avec régressions : absence de reprise automatique après SMTP accepté, rafraîchissement du statut sous verrou, conservation des campagnes de démarchage commencées, erreur et compteur d’extension entièrement exclue. Les trois Minor sont traités (sélecteurs nommés, raison d’arrêt, test métier de deux campagnes).

Validation complète : 812 tests, 4 196 assertions sans échec ; 2 dépréciations et 15 notices PHPUnit. Tests ciblés complètent les derniers libellés et liens de restitution. Twig, YAML et conteneur valides ; schéma synchronisé. Recette navigateur du Twig réel avec données fictives : filtres nommés, deux tableaux, aucun débordement à 390 px ni erreur JavaScript, session fermée. Aucun SMTP réel.

Écarts explicités : interface de verrou générique réutilisée par distribution et workers plutôt qu’un coordinateur monolithique ; rafraîchissement dédié `findFreshByUuid` ; borne 520 semaines ; suppression bloquée pour tous les démarchages commencés (plus conservateur qu’un blocage seulement après succès). Le journal n’ajoute pas de préférences distinctes ni de nouveaux segments.

Contrôles statiques finaux : PHPStan sans erreur, style sans correction, conteneur valide, YAML 16 fichiers et Twig 32 fichiers valides ; `git diff --check` propre. Aucun commit ni déploiement demandé pour ce nouveau lot.

Derniers contrôles HTTP après liens et libellés : 6 tests / 40 assertions sans échec.
