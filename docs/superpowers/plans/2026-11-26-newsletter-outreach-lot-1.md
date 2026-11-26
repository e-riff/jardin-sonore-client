# Newsletter et démarchage — plan d'implémentation du premier lot

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Distinguer les nouvelles campagnes newsletter/démarchage, fournir leurs modèles éditoriaux et conserver les établissements réellement visés par chaque nouveau destinataire, sans changer les préférences de réception existantes.

**Architecture:** Étendre le modèle de campagne et ses DTO existants ; réutiliser le moteur de blocs et les recommandations. Enrichir les destinataires résolus avant déduplication, puis persister leurs associations historiques dans une table complémentaire au moment de la mise en file. Préserver les anciens contenus et les anciennes campagnes non classées.

**Tech Stack:** PHP 8.5, Symfony, Doctrine ORM/DBAL, MariaDB, Twig, formulaires Symfony, AssetMapper, PHPUnit 12 ; versions exactes à confirmer depuis le projet au démarrage. Aucune nouvelle dépendance.

**Spec:** [Cadrage validé, sections 18 et 19](../../mailing-newsletter-demarchage-cadrage-2026-11-26.md). Les sections précédentes décrivent aussi les lots futurs ; elles n'élargissent pas ce premier lot.

## Global Constraints

- « Deux types de nouvelles campagnes : `newsletter` et `outreach`. »
- « Conservation du fonctionnement actuel du ciblage et du désabonnement global pendant ce premier lot. » Exception explicite : les abonnés libres ne constituent pas une source de démarchage.
- « Les quatre parties sont facultatives et activables séparément. » Ordre newsletter : édito, idée du mois, Jardin Sonore, recommandations.
- « Une réinscription confirmée à la newsletter peut réactiver cette publication, mais ne doit pas lever un refus des démarchages. » Ce comportement final appartient au lot préférences : ne pas modifier les parcours d'inscription dans ce lot.
- « Aucun ancien envoi n'est requalifié automatiquement. »
- « Les recommandations masquées par la désactivation de leur rubrique ne sont pas enregistrées comme utilisées. »
- Hors périmètre : exclusion glissante, journal et vues historiques, masque réservé, préférences séparées, articles, extension géographique et calendrier.
- Respecter `.codex/jardin-sonore-guidelines.md` et les instructions locales ; anglais dans les noms de code, textes UI dans `translations/mailing+intl-icu.fr.yaml` selon le format existant.
- Nouveaux PHP : `declare(strict_types=1);`, constantes typées, classes natives importées, callbacks explicitement typés.
- Pas de commit sans demande de l'utilisateur. Les messages suggérés en fin de tâche ne sont pas des autorisations de commit.
- Générer la migration avec Doctrine ; aucune exécution de migration sans confirmation explicite. Aucune écriture en production ni envoi réel pendant les tests.
- Pas de serveur supplémentaire ; recette éventuelle sur les services locaux existants.

## Review Focus

1. Une adresse partagée n'attribue l'envoi qu'aux établissements effectivement retenus, y compris si elle vient simultanément du portail et de l'annuaire — tests tâche 3.
2. Une extension d'une ancienne campagne capture seulement les nouveaux destinataires, sans réécrire l'histoire des adresses déjà envoyées — tests tâche 4.
3. Une rubrique masquée conserve ses données, sans fuite dans le texte, le pré-en-tête ou les traces d'utilisation des recommandations — tests tâche 2.
4. Un client MCP ancien ou une révision périmée ne peut ni classer implicitement une campagne, ni écraser son contenu structuré — tests tâche 5.
5. Une erreur après insertion d'un destinataire annule aussi la capture historique et la transition de campagne ; un désabonnement en attente reste respecté — tests tâche 4.

## Préparation et vérification de référence

- [ ] Lire la spécification, les instructions et `git status --short`. Préserver le document de cadrage et ce plan ; aucun nettoyage des changements utilisateur.
- [ ] Au début de l'exécution, utiliser la compétence d'isolation adaptée ; si worktree, vérifier qu'il peut accéder aux services de test sans modifier les services du répertoire principal.
- [ ] Vérifier les versions avec `docker compose exec -T php php -v` et les dépendances verrouillées.
- [ ] Exécuter la référence : `docker compose exec -T php php -d memory_limit=1G ./bin/phpunit tests/Integration/Infrastructure/Mailing tests/Integration/Infrastructure/Portal/PortalNewsletterSubscriptionManagerTest.php tests/Integration/Application/Mailing/NewsletterAudienceCampaignFlowTest.php`.

Référence observée pendant l'audit : **50 tests, 236 assertions, sans échec**. Les tests d'intégration doivent viser une base dont le nom finit par `_test` ; ne jamais remplacer cette cible par la production.

## Structure des fichiers et contrats transversaux

Les chemins ci-dessous sont relatifs à `jardin-sonore-backend/`. Les noms nouveaux sont des décisions d'implémentation proposées dans ce plan.

### Modèle

- Nouveau `src/Domain/Model/Mailing/MailingCampaignType.php` : enum string `NEWSLETTER = 'newsletter'`, `OUTREACH = 'outreach'`.
- Nouveau `src/Domain/Model/Mailing/MailingCampaignContent.php` : contenu structuré version 1, validation et lecture des rubriques ; pas de dépendance ORM ou HTTP.
- Nouveau `src/Domain/Model/Mailing/MailingRecipientOrganizationSnapshot.php` : valeur historique immuable.
- `MailingCampaign.php` : type nullable pour l'historique, contenu structuré nullable, vérifications avant diffusion et accès aux recommandations effectivement visibles.
- `NewsletterRecipient.php` : liste de snapshots, vide par défaut pour compatibilité.

### Persistance et cas d'usage

- Campagne : modifier entité, mapping PHP et mapper existants ; conserver les dates à l'hydratation.
- Nouveau couple `Infrastructure/Doctrine/Entity/MailingDeliveryRecipientOrganizationEntity.php` et mapping PHP correspondant : associations historiques ; accès technique DBAL depuis la queue existante, sans repository de domaine artificiel.
- `DoctrineNewsletterAudienceResolver.php` : collecte par source et union des associations avant la limite.
- `DoctrineMailingDeliveryQueue.php`, `SendMailingCampaign.php`, `ExtendMailingCampaignAudience.php` : insertion et transaction commune.
- Migration générée dans `migrations/Version<timestamp>.php` ; le nom exact est attribué par Doctrine à l'exécution du plan.

### Édition et rendu

- Étendre les inputs Create/Update, leurs modèles de formulaires et les types existants ; ajouter des types de rubrique dédiés si nécessaires pour réutiliser proprement la collection de blocs.
- Modifier `Application/Controller/MailingController.php`, les vues `templates/mailing/new.html.twig`, `content.html.twig`, `index.html.twig`, `preview.html.twig`, `send.html.twig` selon leurs responsabilités réelles.
- Nouveaux `templates/mailing/email/newsletter.html.twig`, `newsletter.txt.twig`, `outreach.html.twig`, `outreach.txt.twig` ; mutualiser les fragments existants utiles sans changer le rendu `default`.
- Modifier `TwigNewsletterRenderer.php` et `RecordNewsletterRecommendationUsages.php`.
- Modifier `Application/Mcp/MailingDraftManager.php`, `ReadToolRegistry.php`, `WriteToolRegistry.php` et leurs tests de contrat.

## Tâche 1 — Type de campagne et compatibilité historique

**Files:** enum et modèle campagne ; entité, mapping et mapper campagne ; inputs et services Create/Update ; nouveaux tests `tests/Unit/Domain/Model/Mailing/MailingCampaignTypeTest.php` et `tests/Unit/Infrastructure/Doctrine/Mapper/MailingCampaignTypeMapperTest.php`.

**Interfaces:**
- `MailingCampaign::getCampaignType(): ?MailingCampaignType`.
- `MailingCampaign::setCampaignType(MailingCampaignType $campaignType): void`, uniquement avant démarrage de diffusion ; même valeur autorisée sans mutation superflue.
- Paramètre nullable ajouté en fin de constructeur pour ne pas casser l'hydratation historique ; Create impose un type explicite.
- `MailingCampaign::assertReadyForDelivery(): void` refusera un ancien brouillon jamais envoyé et non classé ; extension d'une campagne historique déjà diffusée non classée reste possible.

- [ ] Écrire les tests : absence de type conservée en aller-retour historique ; `newsletter` et `outreach` conservés ; changement de type après début d'envoi rejeté ; ancien brouillon à classer sans conversion forcée de `default` ; nouvelle création sans type refusée.
- [ ] Exécuter `docker compose exec -T php php -d memory_limit=1G ./bin/phpunit --filter 'MailingCampaignTypeTest|MailingCampaignTypeMapperTest'` ; constater un échec lié aux nouveaux contrats.
- [ ] Implémenter enum, modèle, mapping et validations Create/Update. Garder le type indépendant de `templateKey` : un brouillon historique classé peut conserver `default`.
- [ ] Relancer ces tests ; attendu : aucun échec. Adapter les fixtures existantes de création aux types explicites sans classifier les fixtures historiques.
- [ ] Point de revue ; commit uniquement si demandé : `feat: distinguish newsletter and outreach campaigns`.

## Tâche 2 — Contenu structuré et rendu des deux modèles

**Files:** `MailingCampaignContent.php`, modèle/entité/mapping/mapper campagne ; `TwigNewsletterRenderer.php`, quatre nouveaux templates email, `RecordNewsletterRecommendationUsages.php`, processor de blocs existant si nécessaire ; nouveaux tests `MailingCampaignContentTest.php` dans `tests/Unit/Domain/Model/Mailing/` ; étendre `TwigNewsletterRendererTest.php`, `NewsletterRecommendationHistoryTest.php` et mapper tests.

**Interfaces:**
- `MailingCampaignContent::fromArray(array $content): self`, `toArray(): array`, `isSectionEnabled(string $sectionKey): bool`, `getBlocks(string $sectionKey): array` (`list<array<string,mixed>>` documentée).
- JSON version 1 : `version: 1`, `sections` indexées par clés ; chaque section contient `enabled: bool` et `blocks: list`. Newsletter : `editorial`, `monthly_idea`, `jardin_sonore`, `recommendations`. Démarchage : `proposal`, `approach`, `recommendations`.
- Newsletter : les trois premières rubriques contiennent les blocs existants ; les recommandations utilisent la relation existante, sans duplication dans le JSON. Même règle pour les recommandations de démarchage.
- CTA conservé dans les champs campagne existants ; visible dans `jardin_sonore` si newsletter, après le corps/approche si démarchage. Libellé et URL renseignés ensemble.
- `MailingCampaign::getStructuredContent(): ?MailingCampaignContent`, `getVisibleRecommendations(): array` (`list<MailingRecommendation>`).
- Aucun contenu structuré => rendu historique. Templates par défaut des nouvelles campagnes : `newsletter` ou `outreach` suivant leur type.

- [ ] Écrire les tests : ordre des quatre rubriques ; chacune peut être masquée sans effacement de contenu ; titre/bannière seuls insuffisants ; newsletter avec recommandations seules valide ; démarchage exige `proposal` textuel non vide ; HTML vide comme `<p><br></p>` ne compte pas comme texte.
- [ ] Ajouter au renderer les assertions HTML **et** texte : pas de CTA newsletter si Jardin Sonore masqué ; pas de recommandation masquée ni dans le pré-en-tête calculé ; pré-en-tête explicite conservé ; anciens modèles inchangés. Tester le rejet d'une version JSON ou d'un type de bloc inconnu.
- [ ] Ajouter au test d'historique : recommandation inactive ou rubrique recommandations masquée => aucune utilisation enregistrée. Utiliser la même méthode `getVisibleRecommendations()` que le renderer ; conserver le comportement des campagnes historiques non structurées hors corrections nécessaires à cette cohérence.
- [ ] Exécuter les tests ciblés avant implémentation, constater les échecs ; implémenter la validation versionnée, le traitement/sanitization des blocs, la persistance et les rendus sans second moteur de rich text.
- [ ] Réutiliser les stockages d'images existants ; vérifier la propriété et la conservation des images lors de sauvegardes de rubriques masquées. Tester qu'un échec de validation ne supprime pas une image déjà sauvegardée.
- [ ] Relancer `docker compose exec -T php php -d memory_limit=1G ./bin/phpunit --filter 'MailingCampaignContentTest|TwigNewsletterRendererTest|NewsletterRecommendationHistoryTest|MailingCampaignMapper'` ; attendu : aucun échec.
- [ ] Point de revue ; commit uniquement si demandé : `feat: add newsletter and outreach content templates`.

## Tâche 3 — Associations d'établissements dans l'audience résolue

**Files:** `MailingRecipientOrganizationSnapshot.php`, `NewsletterRecipient.php`, `DoctrineNewsletterAudienceResolver.php`, `tests/Integration/Infrastructure/Mailing/NewsletterAudienceResolverTest.php`.

**Interfaces:**
- Snapshot readonly : `organizationUuid: Uuid`, `organizationName: string`, `organizationType: ?string`, `municipalityInseeCode: ?string`.
- `NewsletterRecipient::getOrganizationSnapshots(): array` (`list<MailingRecipientOrganizationSnapshot>`), dernier paramètre constructeur `array $organizationSnapshots = []`.
- Signature `resolve(NewsletterAudienceFilter $newsletterAudienceFilter, ?int $limit = null): NewsletterAudienceResolution` inchangée.
- Déduplication des adresses selon la normalisation existante ; union des snapshots par UUID établissement ; priorité du nom affiché maintenue autant que possible. Aucun traitement spécial des points ou suffixes `+` des adresses.

- [ ] Ajouter les tests d'intégration : une adresse liée à A/B/C et seul B ciblé => `[B]` ; plusieurs sources ciblent A/B => un destinataire avec `[A,B]` ; utilisateur portail lié à deux structures mais une seule ciblée => celle-ci seulement.
- [ ] Ajouter les tests : abonné libre seul hors zone => zéro snapshot ; personne autonome sélectionnée => conservée ; rattachements inactifs exclus ; variantes de casse dédupliquées ; limite appliquée après union complète.
- [ ] Fixer la commune : une seule commune distincte d'adresse active de l'établissement => ce code ; zéro ou plusieurs communes distinctes => `null`. Ne pas inventer la commune à partir du masque ou d'une adresse de personne.
- [ ] Exécuter le test d'audience, constater l'échec des nouvelles assertions ; enrichir les requêtes avec les UUID/types/adresses des établissements effectivement éligibles, y compris sélections explicites et héritage de personne.
- [ ] Relancer `docker compose exec -T php php -d memory_limit=1G ./bin/phpunit tests/Integration/Infrastructure/Mailing/NewsletterAudienceResolverTest.php` ; attendu : aucun échec, anciens totaux inchangés.
- [ ] Point de revue ; commit uniquement si demandé : `feat: retain targeted organizations in mailing audiences`.

## Tâche 4 — Migration additive et capture historique atomique

**Files:** nouvelle entité/mapping associations historiques ; migration générée ; `DoctrineMailingDeliveryQueue.php`, `SendMailingCampaign.php`, `ExtendMailingCampaignAudience.php`, `DeleteMailingCampaign.php` si nécessaire ; nouvelle interface `src/Application/Mailing/MailingCampaignDeliveryTransactionInterface.php` et adapter `src/Infrastructure/Mailing/DoctrineMailingCampaignDeliveryTransaction.php` ; nouveaux tests `tests/Integration/Infrastructure/Mailing/MailingRecipientOrganizationHistoryTest.php`, étendre `NewsletterAudienceCampaignFlowTest.php` et handler tests existants.

**Interfaces:**
- `MailingDeliveryQueueInterface::seedCampaignRecipients(string $campaignUuid, array $newsletterRecipients): void` reste inchangée ; chaque nouvelle ligne est insérée avec ses snapshots.
- `MailingCampaignDeliveryTransactionInterface::run(callable $operation): mixed` ; PHPDoc du callback et générique de retour, adapter DBAL. Les cas d'usage enveloppent insertion des destinataires/associations et sauvegarde de campagne dans cette même transaction. Au rollback, ne pas continuer avec une campagne mutée en mémoire ; recharger ou invalider explicitement l'objet avant toute réutilisation.
- Table `mailing_delivery_recipient_organization` : PK technique, `delivery_recipient_id` de même type SQL que le parent, `organization_uuid` binaire(16), `organization_name` varchar(255), `organization_type` nullable varchar(32), `municipality_insee_code` nullable varchar(5). Unique destinataire/UUID ; index UUID ; FK parent avec suppression en cascade, aucune FK vers l'établissement vivant.
- Campagne : `campaign_type` nullable varchar(16) ; `structured_content` JSON nullable selon le mapping MariaDB existant. Aucun UPDATE de classification ou consentement dans la migration.

- [ ] Écrire les tests : snapshots immuables après changement de nom/type/commune ou suppression de la fiche ; établissement hors cible absent ; suppression de destinataire nettoie les enfants ; succès qualifié par `sent_at`, pas par simple présence d'une association.
- [ ] Écrire les tests : extension ajoute les snapshots des nouvelles adresses, ne touche pas les anciennes ; extension historique non classée reste non classée ; erreur à la seconde insertion => rollback total et campagne rechargée dans l'état initial.
- [ ] Exécuter les tests et constater les échecs attendus ; implémenter les contrats de transaction et de persistance. Conserver les dates `queued_at`, `dispatched_at`, `sent_at` existantes, sans les confondre.
- [ ] Générer : `docker compose exec -T php php bin/console make:migration`. Relire `up()`/`down()`, renseigner la description, supprimer le commentaire automatique inutile. S'il existe d'autres changements de schéma, les isoler sans modifier leurs fichiers ni leur historique.
- [ ] Inspecter le SQL avec `docker compose exec -T php php bin/console doctrine:migrations:migrate --dry-run --no-interaction`. Ne pas exécuter la migration à cette étape.
- [ ] Présenter le SQL et demander confirmation pour appliquer la migration sur la base locale de développement et préparer la base de test si nécessaire. Cette confirmation n'autorise aucune migration en production.
- [ ] Après confirmation seulement, appliquer la migration locale et préparer le schéma test selon la convention existante ; relancer les tests précédents et `NewsletterAudienceCampaignFlowTest.php`, ainsi que le test du handler couvrant un désabonnement après mise en file. Aucun envoi réel.
- [ ] Vérifier `doctrine:schema:validate` et le SQL restant de `doctrine:schema:update --dump-sql` ; attendu : aucun écart parasite. Tester le rollback sur une base locale jetable après confirmation adaptée, jamais sur une base contenant le travail utilisateur.
- [ ] Point de revue ; commit uniquement si demandé : `feat: snapshot mailing recipient organizations transactionally`.

## Tâche 5 — Backoffice et MCP cohérents

**Files:** Create/Update inputs/services, modèles et types de formulaires campagne ; nouveaux modèles/types de rubrique ; `MailingController.php`, vues campagne listées plus haut, `translations/mailing+intl-icu.fr.yaml` ; `MailingDraftManager.php`, `ReadToolRegistry.php`, `WriteToolRegistry.php` ; tests `MailingDraftManagerTest.php`, registries tests existants et nouveau `tests/Functional/Application/Controller/MailingCampaignContentControllerTest.php`.

**Interfaces:**
- Champs d'entrée partagés : `campaignType` (`newsletter`/`outreach`) et `structuredContent` (format tâche 2). Création typée obligatoire ; update partiel conserve les champs omis.
- MCP : lecture/search renvoient `campaignType` nullable et contenu structuré ; révision inclut ces deux éléments et reste stable après hydratation inchangée. Création sans type => erreur explicite invitant à le fournir, jamais de défaut silencieux.
- Choix du type à la création ; ancien brouillon classable sans remplacement de son contenu ; pas de conversion automatique entre modèles. Chaque rubrique peut être activée/désactivée et conserve son contenu.
- Pour `outreach`, refuser `includeFreeSubscribers = true` dans tous les chemins d'application du ciblage : édition, application d'un masque, extension et MCP ; erreur claire, pas de retrait silencieux du critère. Une adresse également rattachée à une structure reste sélectionnable via ce rattachement selon les règles actuelles.

- [ ] Écrire les tests : création des deux types ; ancien brouillon classé conserve son texte ; modification après démarrage refusée ; update MCP sans `structuredContent` conserve celui-ci ; requête périmée refusée ; lecture révision stable ; masquage/réactivation sans perte ; abonné libre refusé comme source de démarchage par UI et MCP.
- [ ] Exécuter les tests pour constater les échecs ; implémenter formulaire et sérialisation avec les processors existants. Les images en rubrique et les recommandations réutilisent leurs outils/catalogues actuels ; une sauvegarde partielle ne détache pas les recommandations omises.
- [ ] Ajouter un indicateur lisible du type dans index, aperçu et confirmation d'envoi ; historique non classé explicitement nommé. Vérifier les textes « désabonnement » : pas de promesse de préférences séparées tant que le global demeure.
- [ ] Vérifier les schémas MCP et les clés autorisées des registries ; rendre le verrou d'écriture existant inchangé. Aucun outil nouveau d'envoi réel.
- [ ] Relancer les tests ciblés UI/MCP ; attendu : aucun échec. Recette locale sans envoi : créer un brouillon de chaque type, masquer/réactiver une rubrique, aperçu HTML/texte, édition depuis MCP puis relecture depuis le backoffice, contrôle clavier/mobile des champs.
- [ ] Point de revue ; commit uniquement si demandé : `feat: expose typed mailing content in backoffice and MCP`.

## Tâche 6 — Vérification globale et livraison préparée

**Files:** documentation de cadrage, ce plan, `ROADMAP.md` après implémentation vérifiée ; éventuels ajustements strictement liés aux tâches précédentes.

**Interfaces:** aucune nouvelle API ; vérifier que tous les contrats des tâches 1–5 coexistent et que le type ne change pas les consentements actuels.

- [ ] Exécuter la suite complète : `docker compose exec -T php php -d memory_limit=1G ./bin/phpunit`. Attendu : aucun échec ; rapporter séparément notices/dépréciations.
- [ ] Exécuter `make backend-lint`, puis `docker compose exec -T php php bin/console lint:twig templates`, `docker compose exec -T php php bin/console lint:container` et `make symfony-assets` ; attendu : succès de chaque commande.
- [ ] Contrôler le diff de schéma local, le SQL de migration et les changements git. Regrouper les migrations intermédiaires propres au sujet si aucune n'est déjà appliquée en production ; toute suppression de fichier requiert confirmation.
- [ ] Relire spécification/plan contre le résultat : données historiques intactes, nouvelles campagnes typées, rubriques facultatives, absence de classification rétroactive, snapshot réel des cibles, absence d'ajout automatique des abonnés libres aux démarchages.
- [ ] Faire une revue finale indépendante selon le mode d'exécution choisi ; résoudre les défauts et ne répéter que les contrôles justifiés par les modifications.
- [ ] Mettre à jour la roadmap avec l'état réel, les validations et les limites : aucune exclusion glissante ni préférence distincte encore livrée. Proposer un commit conventional ; aucun commit ni déploiement sans demande.

## Auto-relecture du plan

- Couverture de la section 19 : type/historique (1), contenu et recommandations (2), cibles réelles (3), persistance et migration (4), UI/MCP et validation d'audience (5), non-régression et livraison (6).
- Les cinq risques de Review Focus possèdent un test propriétaire explicite.
- Interfaces cohérentes : `campaignType`, `structuredContent`, `getVisibleRecommendations()`, `getOrganizationSnapshots()` ; la queue garde son contrat public actuel.
- Le snapshot est une sélection figée à la mise en file ; la réussite dépend de l'état et de `sent_at`. Les préférences sont revérifiées avant envoi.
- Le plan conserve les évolutions futures hors scope et ne préjuge ni de l'élargissement géographique ni de la classification de campagnes anciennes.
- La confirmation de migration constitue un point d'arrêt nécessaire à son exécution, pas à la préparation du code, du SQL et des tests.

## Passage à l'exécution

Plan rédigé après validation de la conception par l'utilisateur. Aucun code applicatif modifié pendant sa rédaction.

Deux modes possibles : exécution directe tâche par tâche dans cette session, puis revue finale indépendante ; ou implémentation par sous-agents avec revue de chaque tâche. Recommandation : **exécution directe**, car les six tâches partagent fortement les mêmes contrats de campagne, contenu et destinataire. Attendre le retour de lecture et le choix du mode avant d'implémenter, conformément à la compétence writing-plans.

## Suivi d'exécution — 26 novembre 2026

Tâches 1 à 5 implémentées sur `feat/newsletter-outreach-lot-1`, sans commit, dans le dossier existant selon le choix utilisateur. Tâche 6 vérifiée : revue indépendante et corrections importantes couvertes par régressions ; 793 tests PHP / 4 138 assertions sans échec (2 dépréciations et 15 notices PHPUnit), 8 tests JavaScript, PHPStan/style/Twig/conteneur valides, compilation de 86 assets.

La migration `Version20261126112154` est générée via `make:migration`, relue, simulée puis appliquée après accord utilisateur uniquement en développement et test. Nouvelle génération : aucun changement détecté. Validation du schéma : synchronisé. Les migrations historiques en attente ne sont pas exécutées. Le `down()` est relu et simulé avec succès ; son exécution réelle n'est pas testée, afin de ne pas supprimer les données locales sans autorisation dédiée. Le rollback transactionnel des envois est testé en intégration.

Recette navigateur du formulaire Twig réel, sans sauvegarde ni envoi : ajout/suppression/réordre des blocs, désactivation conservant le texte, noms de champs uniques, aucune erreur JavaScript ; largeur mobile 390 px sans débordement. Navigateur de recette fermé.

Arbitrages d'exécution :
- Dossier courant et nouvelle branche autorisés ; aucun worktree ni changement de serveur local.
- Création typée et contrôles HTTP/MCP regroupés dans la tâche 5 pour préserver les interfaces intermédiaires.
- Création par le premier corps puis redirection vers l'éditeur complet des rubriques, conservant le parcours existant.
- Type verrouillé dès que le contenu est structuré ; changer de modèle exige un nouveau brouillon, sans conversion silencieuse.
- Contrôleur Stimulus retiré de la rubrique recommandations sans collection : défaut de revue reclassé important et corrigé.
- Aucun commit ni nettoyage de fichiers sans demande utilisateur ; travaux conservés sur la branche.
- Exclusion, journal complet, coordination générale des workers et préférences restent hors de ce lot.

Deux finitions mineures issues de la revue sont différées : nettoyage des images des rubriques lors de la suppression d'une campagne ; compteur de recommandations de l'aperçu tenant compte de la rubrique masquée. Le rendu et l'enregistrement des utilisations respectent déjà le masquage.

### Ajout utilisateur : prolongements

Le champ était désactivé explicitement dans l'action d'édition de séance. Option retirée pour rétablir le sélecteur existant, ses commandes d'ordre et l'enregistrement. Index enrichi d'une colonne de miniatures reprenant le style du catalogue mailing (masquée sur mobile comme ce catalogue), traduction dédiée et correction d'une fermeture de ligne HTML en double. Test HTTP couvrant affichage, ajout, persistance après redirection et miniature : échec sur le champ absent avant correction, succès après.

