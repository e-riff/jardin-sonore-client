# Notifications de première disponibilité par structure — Implementation Plan

**Goal:** Notifier une séance lorsqu'elle apparaît pour la première fois dans l'espace d'une structure, même si sa publication globale est ancienne.

**Spec:** [Décision utilisateur et conception](../specs/2026-09-30-session-availability-notifications-design.md), approuvées dans la conversation.

**Architecture:** historique durable séance/structure, programmation sous le verrou de séance existant, déduplication séance/compte et contrôle des structures déclencheuses avant SMTP. Worker et cron de production inchangés.

## Partie 1 — Disponibilité et livraison

- [x] Ajouter et exécuter les tests : publication sans structure puis ajout, brouillon rattaché puis publié, ancienne séance et nouvelle structure, retrait/rétablissement, compte multi-structures, suppression du rattachement déclencheur avant envoi.
- [x] Ajouter `SessionOrganizationAvailabilityEntity`, son mapping et `SessionOrganizationAvailabilityStore::recordNewAvailability(SessionSummaryEntity): list<int>` ; le repository l'appelle à chaque sauvegarde publiée dans sa transaction.
- [x] Étendre `eligibleUsers(SessionSummaryEntity, ?array $organizationIds = null)` et `schedule(SessionSummaryEntity, array $userEntities, ?array $organizationIds = null)` ; stocker les structures déclencheuses, exclure les livraisons existantes et vérifier les structures courantes avant l'envoi.
- [x] Générer/relire la migration : historique + JSON nullable des livraisons ; initialiser les rattachements des séances déjà publiées sans envoi. La présenter avant exécution locale.
- [x] Vérifier tests, style, analyse, migration en simulation et absence de diff de schéma parasite.

## Partie 2 — Distribution locale Docker

- [x] Ajouter le service `session-notification-dispatcher` et un script de boucle à 60 secondes, sans nouveau serveur HTTP ni modification du worker existant. Ne pas distribuer les campagnes newsletter dans cette boucle.
- [x] Valider la configuration Compose et le script shell ; démarrer le service sur demande utilisateur avec la base locale compatible ; contrôler sa première exécution et ses logs.

## Partie 3 — Suivi

- [x] Mettre à jour roadmap, cadrage historique et README. Le cron production est installé ; l'évolution par structure est livrée sous le tag de clôture décrit ci-dessous.
- [x] Reporter la vérification rapide du compteur mailing : logs de distribution à 0 au moment du contrôle, 574 livraisons `sent` réparties en 205/174/195 ; le « 1 mail » rapporté n'a pas été reproduit dans les logs consultés. Aucun compteur modifié sans anomalie établie.
- [x] Reprendre ensuite la partie 2 du plan newsletter, laissée en attente pendant ce changement prioritaire. Pas de commit automatique.

**Vérification :** 25 tests de programmation/livraison, 113 assertions ; suite backend 228 tests / 1 296 assertions avant le profil newsletter. Style, PHPStan et conteneur validés. Simulation puis application de `Version20260930140509` en développement/test autorisées ; schéma synchronisé. Compose et script shell validés ; service démarré, plusieurs passages réussis à 0 livraison. Vérification locale avant la livraison de clôture décrite ci-dessous.

**Clôture demandée :** l’utilisateur autorise commit, tag, push et déploiement du lot le 30 septembre. Tag de livraison : `deploy-availability-newsletter-20260930-01`, commit `afbcece`. Prochaine reprise : partie 3 newsletter, les parties 1–2 étant terminées. Branche/tag poussés puis backend/front déployés depuis ce tag. Les deux migrations sont appliquées par le script habituel de déploiement ; schéma synchronisé, files vides, trois crons présents, backfill des séances publiées complet sans rattrapage.

**Contrôle HTTP après livraison :** vitrine et connexion 200, destination de séance conservée après connexion, lien public avec jeton fictif inexistant 200 sans modification d’un destinataire réel, profil sans authentification 401. Aucun mail externe de test envoyé ; Gmail/Outlook reste à contrôler.
