# Démarchage : exclusion glissante et journal

État : plan approuvé et implémenté en local. Le défaut général est configurable en environnement et chaque campagne conserve son réglage. Suite du lot éditorial livré sous `deploy-newsletter-outreach-20261126-01` et des finitions `deploy-mailing-sections-fixes-20261126-01`.

## Intention

Espacer les propositions commerciales aux mêmes établissements et voir qui a été sollicité, quand et dans quel périmètre. Conserver masques géographiques et types existants ; aucun suivi des réponses ni nouveau segment métier.

## Approches

1. Filtrer seulement au calcul d'audience : simple mais insuffisant quand plusieurs campagnes attendent dans la file.
2. Filtrer à la préparation puis vérifier avant envoi avec coordination entre workers : approche recommandée, car elle respecte les changements survenus depuis la préparation.
3. Ajouter un CRM et un historique des réponses : écarté, données absentes et besoin non demandé.

## Règles reprises du cadrage

- Réglage par campagne de démarchage ; proposition de défaut : six semaines, modifiable, zéro désactive explicitement.
- Les newsletters ne déclenchent ni ne subissent cette exclusion.
- Compter uniquement les destinataires de campagnes typées démarchage ayant un `sent_at` réel. Ni ancien envoi mixte, ni échec, ni simple mise en file.
- L'identité établissement vient des UUID figés lors de la mise en file. Les changements ultérieurs de nom, type ou adresse ne réécrivent pas le journal.
- Les ajouts forcés passent par la même règle.
- Plusieurs contacts du même établissement dans une même campagne : préserver le ciblage initial ; la fenêtre protège contre les autres campagnes. Ne pas bloquer les destinataires suivants à cause du premier envoi de cette même campagne.
- Extension d'une campagne : contrôler les autres campagnes et préserver les traces des envois antérieurs.

## Adresse partagée — décision validée

Adresse partagée par plusieurs établissements : bloquer l'adresse entière si au moins un des établissements ciblés est exclu. Décision confirmée par l'utilisateur. Le récapitulatif expose cette raison.

## Audit et implications techniques

`SendMailingCampaign` résout actuellement l'audience puis fige les destinataires dans une transaction. Les snapshots existent dans `mailing_delivery_recipient_organization`. Le worker reconstruit actuellement un destinataire sans ces rattachements : le contrôle futur devra charger les snapshots de la ligne de livraison, sans interroger les rattachements vivants.

`claimPendingRecipients` sélectionne puis marque les lignes en deux opérations ; ce n'est pas une preuve d'exclusivité entre deux dispatchers. Le lot doit sécuriser cette revendication et la réexécution d'un même message avant d'annoncer une protection contre la concurrence.

La coordination doit couvrir le contrôle d'historique, l'envoi et l'enregistrement du succès. Préférer un verrou partagé entre processus pour les démarchages (granularité globale initiale possible, vu le volume), avec vérification du statut de la ligne après acquisition ; aucune transaction SQL longue pendant l'appel SMTP. Évaluer le mécanisme déjà disponible avant d'ajouter une dépendance. Si le verrou est indisponible, différer l'envoi plutôt que l'expédier sans contrôle.

SMTP et la base ne forment pas une transaction : une interruption après acceptation SMTP et avant `markSent` laisse une incertitude. Ne pas promettre une garantie de livraison exactement une fois ; garder ce risque visible dans le plan et éviter une relance aveugle des états incertains.

## Journal

Une page de journal, filtrable par établissement, type historique, période et campagne. Lignes donnant campagne, date réelle, établissements distincts, adresses et état ; accès au détail de campagne. Regroupement par type avec nombres et communes effectivement touchées, sans prétendre qu'un type entier a été sollicité.

Une structure peut avoir plusieurs contacts : compter séparément les adresses et les établissements distincts. Une adresse partagée ne doit pas doubler les compteurs d'adresses. Les états non envoyés restent identifiés comme tels et ne comptent pas dans la fenêtre.

## Interface et données

Réglage dans la préparation d'envoi, visible uniquement pour le démarchage ; récapitulatif indiquant effectif avant filtre, exclus et retenus, avec raison. Recalcul à la mise en file puis juste avant SMTP. Distinguer dans le suivi les annulations pour exclusion des désabonnements et des échecs techniques.

Le délai est persisté sur la campagne ; les anciens démarchages conservent leur historique. Une migration additive devra être générée puis relue et soumise avant exécution. Les préférences distinctes et l'audience newsletter restent hors de ce lot.

## Vérification à prévoir

Frontières de fenêtre et délai nul ; newsletter sans effet ; ajouts forcés ; multi-contact même campagne ; adresse multi-établissements ; extension ; nouveaux envois entre préparation et distribution ; concurrence de deux campagnes et de deux workers ; statut déjà traité ; désabonnement intervenu dans la file ; historique figé malgré modification de l'annuaire ; compteurs distincts du journal.

## Arbitrages d’implémentation

- `MAILING_OUTREACH_EXCLUSION_WEEKS=6` initialise les nouvelles campagnes, web et MCP ; réglage par campagne entre zéro et 520 semaines. Changement de l’environnement sans effet rétroactif.
- Les campagnes de démarchage commencées sont conservées et ne peuvent plus être supprimées. C’est une protection de l’historique, également pour une campagne arrêtée ou en échec.
- La ligne passe à `sending` avant SMTP puis `sent` après succès enregistré. Après acceptation SMTP suivie d’un échec de persistance, elle passe à `uncertain` si la base répond, sinon reste `sending`. Ni l’un ni l’autre ne sont repris automatiquement ; examen manuel nécessaire. Les échecs avant succès SMTP déclaré restent réessayables selon le mécanisme existant.
- Le verrou MySQL utilise une connexion dédiée et est libéré en `finally` ; zéro attente, report récupérable sur contention. Les newsletters prennent seulement le verrou destinataire, les démarchages prennent aussi le verrou partagé de leur régime, même avec délai zéro.
- Le statut de campagne est rafraîchi depuis Doctrine sous verrou pour ne pas ignorer un arrêt récent.
- Le mapping HTTP vers le DTO du journal est explicite, faute de composant Serializer installé ; aucun ajout de dépendance. Les filtres utilisateur présentent des noms et libellés, jamais des UUID à saisir.
- La synthèse présente les 50 derniers groupes campagne/type ; le détail pagine à 50 lignes historiques. Une adresse multi-établissements figure sur plusieurs lignes, les compteurs de synthèse restent distincts.
- Le retour arrière de migration est simulé, pas exécuté sur des données locales existantes.
