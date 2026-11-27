# Roadmap Jardin Sonore

Mise à jour le 27 novembre 2026. Ce document est la **seule source de l'état courant, des priorités et du point de reprise**. `AGENTS.md` et `.codex/jardin-sonore-guidelines.md` décrivent la méthode de travail ; les plans, conceptions et bilans datés gardent le détail technique et l'état constaté à leur date. Ne pas reprendre leurs anciennes listes de tâches sans vérifier cette page. L'ordre général est : **visibilité et nouvelles demandes**, puis **gain de temps dans le backoffice**. Les priorités indiquent un ordre de travail, pas un engagement de livraison simultanée.

## Newsletter et démarchage — premier lot déployé

**Correctif des transitions d'envoi en local** : un échec ponctuel ne bloque plus les vagues restantes ; les changements d'état sont conditionnels afin qu'un arrêt concurrent ne soit pas écrasé par le distributeur ou un worker. Une campagne en échec avec des destinataires en attente peut reprendre et reste arrêtable. Suite backend : 830 tests / 4 317 assertions sans échec (16 notices), puis 18 tests ciblés après ajout de l'arrêt en échec ; PHPStan, style et conteneur valides. Non commité et non déployé ; pas d'envoi réel. Les refus par type et l'aperçu de masque signalés dans la revue restent à traiter séparément.

**Suite préférences et audience — déployée le 27 novembre sous `deploy-newsletter-preferences-20261127-01` (`b2e1c877`).** Branche `feat/newsletter-preferences` : refus newsletter/démarchage distincts, abonnement confirmé indépendant du ciblage, managers public/portail/backoffice harmonisés, contrôle par type jusqu’au worker, masque newsletter réservé et protégé avec copie à la création et aperçu avant réapplication. Le portail transmet seulement les préférences explicitement changées pour ne pas lever un refus plus récent lors d’une sauvegarde de profil. Les sources annuaire/abonnés et leur recouvrement sont visibles avant envoi. Une audience moderne sans critère annuaire n’ajoute pas tout l’annuaire ; les établissements explicitement choisis restent une union ciblée.

Migrations `Version20261127083218` et `Version20261127083953` appliquées après accord en développement, test et production. Schéma dev synchronisé, nouvelle génération vide ; écarts historiques hors sujet conservés en test. Relevé local après reprise : 2 196 adresses, 42 blocages historiques conservés pour chaque type, aucune adresse historiquement bloquée nouvellement éligible. Aucune inscription volontaire créée par la reprise. Les anciens liens globaux restent globaux ; les nouveaux liens pointent vers les préférences et leur action ciblée.

Validation : suite backend 827 tests / 4 310 assertions sans échec (2 dépréciations, 15 notices), plus régression ciblée des dates globales 1 test / 3 assertions ; 9 tests client, lint front, build front, PHPStan, style, Twig/YAML/conteneur valides. Revue indépendante traitée ; recette navigateur locale des préférences publiques à 390 px, refus ciblé sans effet sur l’autre type, donnée fictive retirée et navigateur fermé. Aucun envoi réel. Le build signale une alerte de sécurité Next.js préexistante ; aucune dépendance changée dans ce lot.

Livraison backend et front effectuée après commit/tag et push de la branche et du tag. Une interruption temporaire du backend et des tâches planifiées a encadré la reprise, avec consommateurs arrêtés ; règles HTTP et crontab initiales restaurées à l'identique après vérification. Production : schéma synchronisé, 2 638 adresses, 76 exclusions historiques reprises dans chaque type et zéro adresse historiquement bloquée devenue éligible. Site et connexion backoffice HTTP 200 ; API portail sans authentification HTTP 401 et jeton de préférences inconnu HTTP 404. Aucun envoi de campagne de recette.

Prochaine action d'usage : désigner explicitement le masque réservé dans le catalogue (« Utiliser pour les newsletters »), puis préparer un numéro et un test de rendu à soi-même. Aucun territoire sélectionné automatiquement. Articles publics et calendrier éditorial restent séparés. L'alerte Next.js préexistante reste à traiter ; les jetons de préférences dans les chemins restent visibles dans les logs HTTP de l'hébergeur malgré leur masquage Monolog. Références : [audit](docs/audit-newsletter-audience-preferences-2026-11-27.md), [conception approuvée](docs/superpowers/specs/2026-11-27-newsletter-preferences-design.md), [plan et bilan](docs/superpowers/plans/2026-11-27-newsletter-preferences.md).

Conception et plan validés le 26 novembre ; livré sous `deploy-newsletter-outreach-20261126-01` (commit `397701d8`) depuis `feat/newsletter-outreach-lot-1`. Le premier lot distingue les types et modèles, ajoute les rubriques facultatives et prépare l'historique des établissements effectivement ciblés. Les préférences restent globales ; exclusion glissante, journal complet, masque newsletter réservé et articles publics appartiennent aux suites.

La migration additive `Version20261126112154`, générée avec `make:migration`, est appliquée après accord utilisateur aux bases locales de développement et de test. Une nouvelle génération ne détecte aucun changement et la validation du schéma est correcte. Les anciennes migrations en attente restent intactes. Le retour arrière est relu et simulé, pas exécuté réellement.

Validation du lot mailing : 793 tests PHP / 4 138 assertions sans échec (2 dépréciations, 15 notices), 8 tests JavaScript ; revue indépendante et corrections couvertes par régressions ; PHPStan, style, Twig et conteneur valides, 86 assets compilés. Recette navigateur du formulaire Twig réel : ajout, suppression, ordre, masquage avec conservation du contenu ; aucune erreur JavaScript ni débordement à 390 px. Aucun envoi réel de recette. Déploiement effectué le 26 novembre : migration appliquée en production, schéma synchronisé, site et connexion backoffice HTTP 200.

Deux finitions déployées sous `deploy-mailing-sections-fixes-20261126-01` (`a800be42`) : nettoyage des images des rubriques à la suppression d'une campagne et compteur d'aperçu des recommandations masquées. Vérifiées par 34 tests / 122 assertions, PHPStan et style ; site et backoffice HTTP 200. Le rendu envoyé et l'historique d'utilisation respectent déjà le masquage.

Ajout demandé ensuite : rétablissement du sélecteur de prolongements dans l'édition de séance et miniature à gauche dans leur index, suivant le catalogue des recommandations. Régression HTTP d'ajout/enregistrement et affichage couverte ; validation finale consignée dans le plan.

Références : [cadrage](docs/mailing-newsletter-demarchage-cadrage-2026-11-26.md), [plan du premier lot](docs/superpowers/plans/2026-11-26-newsletter-outreach-lot-1.md). Suite implémentée en local : [exclusion glissante et journal](docs/superpowers/specs/2026-11-26-outreach-exclusion-journal-design.md), avant préférences et audience newsletter. Adresse partagée bloquée dès qu’un établissement ciblé est exclu (validé). Voir le suivi du lot ci-dessous.

## Démarchages : exclusion et journal — déployés

Suite validée par l’utilisateur : adresse partagée bloquée dès qu’un établissement ciblé est exclu. Délai proposé pour les nouvelles campagnes via `MAILING_OUTREACH_EXCLUSION_WEEKS=6`, modifiable par campagne sur la préparation d’envoi et via MCP ; zéro désactive, bornes 0–520 semaines. Les campagnes existantes gardent zéro jusqu’au choix explicite. Les newsletters restent hors fenêtre.

Le filtre compte seulement les autres campagnes typées démarchage réellement envoyées ; il est appliqué à la mise en file, aux extensions puis juste avant SMTP. Revendication SQL atomique, verrou de distribution et verrous partagés destinataire/démarchage protègent les traitements concurrents. Les états « en cours de confirmation » et « à vérifier » évitent de renvoyer automatiquement après un succès SMTP non enregistré. Une interruption pendant SMTP reste une incertitude à examiner manuellement ; aucune promesse de livraison exactement une fois.

Journal dans le menu Mailing et depuis les fiches d’établissement : filtres par noms, type historique, campagne et dates, synthèse des campagnes/types avec compteurs d’adresses et établissements distincts, détails paginés. Les campagnes de démarchage commencées ne sont plus supprimables, afin de conserver cet historique. Les raisons d’annulation distinguent arrêt, désabonnement et sollicitation récente.

Migration `Version20261126195323` générée, relue et appliquée après accord aux bases locales de développement et de test uniquement ; schéma synchronisé et nouvelle génération sans changement. Deux colonnes additives, aucun retraitement historique. Retour arrière relu et simulé, sans exécution destructive.

Validation : revue indépendante traitée ; 812 tests / 4 201 assertions sans échec (15 notices), PHPStan et style propres. Régressions double message, deux campagnes/même établissement, incident après SMTP, statut Doctrine périmé et extension entièrement exclue. Formulaire réel et journal vérifiés ; recette navigateur Twig sur données fictives à 390 px sans débordement ni erreur JavaScript, navigateur fermé. Déployé le 27 novembre sous `deploy-outreach-journal-20261127-01` (commit `4b361cf8`). La migration ciblée a été appliquée en production sans jouer les migrations historiques en attente ; le schéma est synchronisé avec le code déployé, le site et le backoffice répondent HTTP 200. Aucun envoi réel de recette.

Références : [conception](docs/superpowers/specs/2026-11-26-outreach-exclusion-journal-design.md), [plan et suivi](docs/superpowers/plans/2026-11-26-outreach-exclusion-journal.md). Suite à cadrer séparément : préférences distinctes et audience newsletter réservée.

## Édition de séance — ajustement déployé le 27 novembre

Bloc rééquilibré en « Général », « Notes privées » repliables et « Prolongements ». Suppression du label et de l’aide redondants des notes ; choix des prolongements par bouton + et dialogue natif avec recherche et miniatures. Les éléments choisis affichent une miniature de 32 px à droite et gardent ordre/retrait. La sélection reste enregistrée avec le formulaire de séance, sans sauvegarde implicite ni changement du rendu portail/PDF.

Validation : test fonctionnel d’ajout/persistance adapté, PHPStan/style/Twig valides ; recette du formulaire Twig réel et des contrôleurs applicatifs : ajout, retrait, réajout, ordre, recherche, Échap/fond/boutons, conservation des notes, mobile 390 px sans débordement ni erreur JavaScript. Assets recompilés. Inclus dans `deploy-outreach-journal-20261127-01` ; site et backoffice répondent HTTP 200.

## Recommandations mailing et prochaines suites — 26 novembre

Lot déployé le 26 novembre sous `deploy-mailing-recommendations-20261126-01` (`c6123e99`) : correction de la lecture des utilisations par UUID binaire (les traces existantes réapparaissent), date de création visible et tri récent d’abord ; description limitée à 420 caractères visibles, liens intégrés par un petit bouton et adresses exclues du compteur. Formulaires, MCP, aperçu et versions HTML/texte des mails sont cohérents. Les images des recommandations et des blocs latéraux sont centrées verticalement. Validation : 766 tests backend, 4 062 assertions, sans échec (15 notices PHPUnit) ; PHPStan, style, Twig, conteneur et compilation de 86 assets valides. Recette navigateur du champ Twig réel sans sauvegarde ni envoi : liens, compteur Unicode et limites 420/421 valides.

Les finitions séances/compositeur/PDF sont closes pour le moment à la demande de l’utilisateur. La prochaine piste à cadrer est une petite liste de rappels généraux, facile à administrer dans le backoffice et via MCP, également incluse dans le mail du matin. Les détails de récurrence restent à décider. Voir le [plan du lot recommandations](docs/superpowers/plans/2026-11-26-mailing-recommendations.md).

## Familles, variantes et connecteur de séances — chantier terminé

Le connecteur permet de créer et modifier les familles et de composer des séances en brouillon en production. Le correctif PDF est livré sous le tag `deploy-pdf-seances-20261125-01`.

Premier lot déployé le 24 novembre sous `deploy-familles-variantes-20261124-01` (`b7a4451a`) : familles et tags propres, variantes indépendantes, composition depuis une famille et édition des éléments libres ligne par ligne. La suite backend passe (721 tests et 13 notices PHPUnit) ; une recette métier complète reste à faire, notamment pour le portail et les PDF. La migration `Version20261124164210` est appliquée aux bases locales, de test et de production ; son `down` puis son `up` ont été vérifiés sur la base de test, et le schéma de développement est synchronisé. La base de test conserve des écarts antérieurs hors sujet (quatre migrations historiques non exécutées) ; ne pas les appliquer sans vérifier leur contenu. L'ancienne migration `Version20261027160000` reste volontairement non exécutée en développement. Suivre la [conception](docs/superpowers/specs/2026-11-24-familles-variantes-seances-design.md) et le [plan](docs/superpowers/plans/2026-11-24-familles-variantes-seances.md). Le 25 novembre, le parcours actuel suffit à l’usage. La recherche dédiée de variantes par thème entre familles est écartée pour le moment.

Parcours livré : création d'une famille depuis une séance, rattachement de plusieurs séances sans famille depuis son détail, sélecteur de famille enregistré avec les informations de séance, index harmonisé et actions de séance plus compactes sur mobile. La recette visuelle de ces derniers ajustements reste à faire.

L’utilisateur indique le 24 novembre que les recettes précédemment listées au point 3 (dossiers, calendrier, Google Calendar et Mistral) sont déjà faites ; ne pas les présenter comme des tâches à refaire sans problème nouveau.

## Correction de la structure d’un dossier et mail du matin

Déployé le 24 novembre sous `deploy-dossier-structure-digest-20261124-01` (`06e8d25f`) : les interlocuteurs de l’ancienne structure cessent de suivre le dossier tout en restant dans son historique et l’annuaire ; le contact principal concerné est retiré, la demande d’origine est conservée et la correction figure dans le fil. Le mail du matin n’inclut plus les dossiers sans action et trie les tâches par jour, sans heure d’abord puis par heure croissante. Les 705 tests backend passent ; aucune donnée réelle n’a été modifiée pour la recette.

## Factures simples et liens de séances — déployés le 19 novembre

En local, le dossier et le connecteur distinguent les factures à préparer des factures envoyées, avec référence, montant et description. Les nouvelles factures n’activent pas le suivi de paiement ; les anciennes conservent leur fonctionnement. La préparation mensuelle est terminée lors de l’enregistrement. Le lien dossier des séances ouvre un nouvel onglet et le mail de synthèse contient les liens de carte. La migration `Version20261119090032` est appliquée sur les bases locales, de test et de production. Déploiement sous le tag `deploy-factures-simples-20261119-01` (commit `e8187a1d`) ; site et connexion HTTP 200, connecteur non authentifié HTTP 401. L’écriture MCP possède le verrou dédié `MCP_INVOICE_WRITE_ENABLED`, activé en production après autorisation explicite. Le conteneur et la disponibilité ont été vérifiés ; régler l’outil sur « Demander » dans Mistral et rafraîchir la liste des fonctions. Aucun enregistrement réel de facture n’a été effectué pendant ce contrôle.

## Priorité utilisateur du 17 novembre — historique

Reprendre **d’abord la présentation des formulaires dans les dossiers**, notamment
« Ajouter ou rattacher un interlocuteur » : les dépliants imbriqués et les formulaires
côte à côte rendent la hiérarchie confuse, avec des champs, cases à cocher et actions
insuffisamment harmonisés. Présenter des actions explicites et ouvrir les formulaires
d’ajout/rattachement dans des dialogues natifs, avec un peu de Stimulus pour les
interactions, en suivant les styles communs du backoffice. Vérifier sur toute la page
les fermetures par fond, Échap, croix, annulation et validation, puis la réouverture
du même dialogue et d’un autre, sans blocage. Contrôler aussi le rendu mobile et le
parcours clavier. La capture fournie le 17 novembre illustre le bloc Interlocuteurs.

**Ensuite, concevoir le regroupement des séances en familles et variantes** : besoin
confirmé et important pour retrouver les séances, rechercher et parcourir leur liste.
Piste proposée : une famille regroupe des variantes indépendantes avec des chansons
ou contenus différents. La structure exacte, les recherches et la présentation de la
liste restent à définir avec l’utilisateur ; aucun héritage automatique ni modèle
parent/enfant n’est décidé à ce stade.

Les priorités actuelles sont indiquées en tête de cette roadmap.

## Dossiers — dialogue Interlocuteurs livré le 18 novembre

Déployé sous `deploy-dossier-contact-dialogs-20261118-01` (`ac6eae54`) : le bloc « Ajouter ou rattacher un
interlocuteur » ouvre un dialogue natif avec un choix Stimulus entre rattachement
et création. Les deux formulaires, leurs jetons CSRF et l'interlocuteur principal
sont conservés ; actions côte à côte, champs et cases harmonisés. Le formulaire
inactif est masqué et désactivé, sa saisie conservée lors d'un changement de choix.
Les dialogues communs et de confirmation suivent les fermetures natives, le cache
Turbo et la déconnexion afin d'éviter les fenêtres invisibles restées modales.

Validation : 683 tests, 3 572 assertions, PHPStan, style, Twig/YAML, conteneur,
lint front et compilation des 82 assets. Recette locale sur ordinateur et mobile :
Échap/fond/croix/Annuler, réouverture et alternance tâches/notes/interlocuteurs,
cache Turbo, reconnexion DOM et réouverture annuaire LiveComponent ; succès.
Aucune donnée créée ni suppression déclenchée par le navigateur de recette.
La revue ne relève aucun défaut important. Contrôle après déploiement limité au
site et à la connexion backend : HTTP 200/200, sans relancer les tests.
Le reste de l'harmonisation des dossiers
reste à apprécier à l'usage ; les familles et variantes de séances restent à concevoir.

## Éléments libres des séances — appariement livré le 18 novembre

Déployé sous `deploy-session-free-line-pairs-20261118-01` (`e23dd2fa`) : les paroles et indications des éléments
libres sont associées par numéro de ligne à la lecture et utilisent les blocs de
rendu existants du répertoire (compositeur, aperçu, portail, PDF). Les lignes vides
conservent leur place ; les lignes sans correspondance restent visibles. Les deux
champs de saisie restent en place ; les retours de ligne de début et de fin sont
préservés à la soumission et à la relecture. Aucun changement de schéma ni réécriture
des séances existantes. L’édition en blocs séparés est notée en évolution différée.
Validation : 683 tests backend, 3 558 assertions ; PHPStan, style, Twig, conteneur
Symfony et lint front passent. Aucun défaut important en revue. Contrôle après
livraison limité à un appel regroupé : site et connexion backend HTTP 200, sans
relancer les tests ni créer de données en production.

## Pièces jointes mail MCP — livré le 18 novembre

Déployé sous `deploy-mcp-mail-attachments-20261118-01` (`afab252a`). Les pièces
jointes sont acceptées à la création d’une réponse (`save_mail_draft`, y compris
`replyAll: true`) et dans `send_personal_notification`. `update_mail_draft`
accepte ajout et retrait ; l’éditeur général conserve son accès à tous les
brouillons manuels/MCP. Format filename/mimeType/base64 ; limites inchangées :
10 fichiers, 5 Mio chacun, 10 Mio au total. Les envois incluent les fichiers dans
leur dédoublonnage ; les anciennes demandes sans fichier restent compatibles.
Les noms de fichiers et fichiers vides survivent à la relecture MIME.

Validation : 668 tests, 3 471 assertions, PHPStan, style, conteneur Symfony,
Composer, compilation des 81 assets et lint front. PHPUnit signale 2 dépréciations
et 4 notices, sans échec. Pas de migration. Contrôle après déploiement limité,
à la demande de l’utilisateur : site et MCP HTTP 200, 53 outils avec les nouveaux
paramètres ; aucun mail ni brouillon réel de recette créé. Rafraîchir les fonctions
du connecteur avant usage. [Guide](docs/mcp-mail-tools.md) et
[message prêt à transmettre à l’IA](docs/mcp-mail-assistant-message.md).

## Correctifs annuaire, dossiers, MCP et séances — 17 novembre

Lot identifié par `deploy-backoffice-small-fixes-20261117-01` : fermeture des dialogues
annuaire synchronisée avec LiveComponent (Échap, fond, croix, annulation), téléphones
français des dossiers regroupés par CSS sans espaces dans le texte ni modification
des liens `tel:`, option `replyAll` sur `save_mail_draft` (To/Cc visibles, exclusion de
la boîte et des doublons), duplication depuis la fiche séance vers une copie non
publiée, sans structure/lieu rattaché. Contenu, séquences, médias et liens au catalogue
sont conservés ; séance et séquences reçoivent de nouveaux UUID.

Validation : 645 tests backend passent (quatre notices PHPUnit préexistantes), PHPStan,
style PHP, Twig, YAML, conteneur Symfony, Composer, compilation des 81 assets et lint
front. Recette locale authentifiée : réouverture de dialogues après Échap et clic sur
le fond, y compris du même formulaire. Aucun changement de schéma ni migration.
Après livraison, rafraîchir les fonctions Mistral pour voir `replyAll` sur l’outil
existant ; aucune recréation de connecteur prévue. La création IMAP réelle en mode
répondre à tous reste à confirmer depuis Mistral, sans envoi.

Le regroupement des séances en familles et variantes reste à concevoir ; aucune
relation parent/enfant ni héritage automatique n’est introduit par la duplication.

## Ajustements du récapitulatif — livrés le 13 novembre

Les deux tâches de préparation de séance (29 et 35) ont été annulées en production
via le workflow des dossiers ; leur contenu et la trace d’annulation restent dans
l’historique. Contrôle effectué : aucune tâche ouverte « Préparer la séance … » restante.
Ne plus créer de tâches de préparation : les séances à venir servent déjà de repère.
Conserver les rappels de préparation de facture après les séances confirmées du mois.

Le récapitulatif sépare « Factures à faire », « Suivis à faire » et « Factures à relancer »
dans le mail HTML et texte, avec échéances « Aujourd’hui » / « En retard ».
La page `/amis-et-partenaires` passe en `noindex, follow` et sort du sitemap.
Vérifications locales : 640 tests / 3 273 assertions passent (deux dépréciations,
quatre notices PHPUnit), PHPStan, style, Twig et conteneur Symfony, lint/build front.
Le HTML généré contient `noindex, follow` et le sitemap ne contient plus cette page.
Déployé : commit `15d76413`, tag `deploy-digest-partners-noindex-20261113-01`,
branche et tag poussés avant livraison du backend et du front. Contrôles publics :
page partenaires HTTP 200 avec `noindex, follow`, URL absente du sitemap HTTP 200.
Templates de production valides, schéma synchronisé et 77 migrations appliquées,
aucune en attente ; aucun mail de recette envoyé. Voir le
[plan du lot](docs/superpowers/plans/2026-11-13-commercial-digest-readability.md).

## Connecteur MCP — livré le 12 novembre

L’édition générale des brouillons manuels et MCP, leur recherche, la conservation
To/Cc/Bcc/HTML/fil de réponse et l’ajout/retrait de pièces jointes sont déployés sous
`deploy-mcp-drafts-notifications-20261112-01` (commit `9c468fb9`). L’outil distinct
`send_personal_notification` utilise le transport Jardin Sonore, un destinataire
personnel fixé côté serveur et une trace persistante pour le dédoublonnage et la
limite de 10 demandes par heure. Le canal est **activé en production** ; l’adresse
personnelle et la sauvegarde préalable de configuration restent hors Git.

La migration `Version20261112080023` est appliquée en local et en production après
accord utilisateur. Le schéma Doctrine est synchronisé ; les **77 migrations de
production sont appliquées, aucune en attente**. L’ancienne migration de données
`Version20261027160000` (Ludobus) reste en attente uniquement en local, volontairement
non exécutée dans ce lot. Les trois droits de brouillon restent actifs.

Vérification : **620 tests et 3 164 assertions passent**, avec deux dépréciations et quatre notices
PHPUnit ; PHPStan, style, conteneur Symfony, validation Composer et compilation des
81 assets Symfony passent. L’ancien échec du test de dossier provenait de dates
fixes devenues passées ; les deux séances utilisent désormais deux mois futurs,
sans changement du comportement applicatif. Les 88 tests ciblés MCP/mail passent
également (498 assertions). La configuration POST PHP/Nginx passe à 16 Mio pour
les pièces jointes base64 ; la limite d’upload classique reste à 8 Mio.

Contrôles HTTP authentifiés de production : **53 outils MCP**, recherche/édition
et schémas de pièces jointes présents, destinataire arbitraire refusé par l’outil
de notification sans appel SMTP, JSON de **14 Mio accepté**. Site public HTTP 200.
La recherche IMAP réelle fonctionne et retourne zéro brouillon lors de la recette ;
aucun brouillon n’a été créé/modifié et aucun mail n’a été envoyé. La lecture/édition
d’un brouillon réel et la livraison Gmail restent à confirmer dans Mistral après
rafraîchissement de ses outils. Voir le [guide MCP mail](docs/mcp-mail-tools.md) et le
[plan du lot](docs/superpowers/plans/2026-11-12-mcp-drafts-and-personal-notifications.md).

Complément **déployé** sous `deploy-mcp-drafts-notifications-20261112-02`
(commit `91c3114b`) : `recipient` facultatif pour les notifications, afin de
sélectionner librement une adresse tout en gardant l’adresse personnelle comme
défaut. La destination effective entre dans le dédoublonnage et l’enveloppe SMTP ;
une même clé vers une autre adresse est refusée. Aucun changement de migration.
Le MCP de production expose le nouveau champ facultatif et rejette les adresses
invalides avant réservation/envoi ; 53 outils et schéma Doctrine synchronisé
confirmés après cette seconde livraison. Aucun mail de recette envoyé.

### Correction des brouillons — livrée et vérifiée

Déployée sous `deploy-mcp-draft-encoding-20261112-01` (commit `4e67dd0e`).
Le retour d’usage avait révélé une double conversion à la lecture des objets déjà
en UTF-8 : les accents disparaissaient,la vérification du remplacement échouait et
l’ancien brouillon était conservé. Le correctif décode uniquement les mots MIME,
relit aussi les créations avant succès,renvoie la référence canonique et distingue
un remplacement vérifié dont le nettoyage reste en attente. Dates ISO8601 Paris
avec décalage explicite. Pas de migration ou dépendance supplémentaire.

**631 tests et 3 192 assertions passent**,avec deux dépréciations et quatre notices
PHPUnit ; les 101 tests ciblés MCP/mail (535 assertions),PHPStan,style et conteneur
passent. Revue indépendante sans constat restant. Le déploiement a compilé les
81 assets,confirmé les 77 migrations appliquées et un schéma Doctrine synchronisé.

La recette en production a créé un brouillon technique isolé avec accents,HTML et
pièce jointe,puis l’a relu et modifié deux fois : succès,un seul message de recette
après chaque édition,références canoniques et Message-ID directement réutilisables,
HTML/pièce jointe conservés,date avec décalage correct,en-tête brut encodé en MIME.
Les seuls messages de recette ont été retirés par UID ; aucun brouillon existant
modifié, aucun mail envoyé par la recette. Les doublons précédemment signalés restent inchangés.
Site public et MCP authentifié HTTP200 ; 53 outils et descriptions correctives
exposés. **Rafraîchir les outils Mistral suffit normalement ; pas de changement
d’authentification,de noms ou de paramètres nécessitant une reconnexion.**

## Acceptation d’un devis et confirmation des séances — livré le 12 novembre

Déployé sous `deploy-quote-calendar-confirmation-20261112-01` (commit `b67a189a`).
Le passage d’un devis à « accepté » confirmait seulement le dossier,et ne retirait
pas `[OPT]` sur ses séances liées. Le chemin de signature partagé backoffice/MCP
confirme désormais les options du dossier à partir d’un événement Google frais et
de son ETag,sans écraser horaires ou lieu. Les séances annulées ou déjà confirmées
ne sont pas modifiées. Les échecs restent en option avec avertissement backoffice
et compteurs `calendarSync` dans le MCP ; une relance traite les options restantes
sans doubler l’historique de signature.

**638 tests et 3 250 assertions passent** (2 dépréciations,4 notices),ainsi que
PHPStan,style et conteneur. Les 11 tests ciblés devis/confirmation passent
(92 assertions) ; revue sans défaut substantiel. Schéma synchronisé,pas de migration
ou dépendance supplémentaire ; déploiement backend et compilation des 81 assets
réussis,site public HTTP200.

Les deux dossiers indiqués par l’utilisateur ont été contrôlés côté Google :
l’un était déjà cohérent et n’a pas été modifié. L’autre avait une seule option
malgré son devis signé ; reprise ciblée après sauvegarde privée,confirmation locale
et retrait du marqueur vérifiés,horaires et lieu conservés. Aucun autre dossier
modifié. Détails : [plan et vérifications](docs/superpowers/plans/2026-11-12-quote-calendar-confirmation-fix.md).

## Livré — hors travaux futurs

- Backoffice métier pour l'annuaire, les médias, les prolongements, le mailing, les séances et les outils techniques ; EasyAdmin a été retiré.
- Masques d'audience réutilisables, ciblage géographique et extension d'une campagne déjà envoyée.
- Compositeur de séances, publication aux structures, lecture HTML et téléchargement du PDF canonique ; la génération et le téléchargement fonctionnent. Les finitions du compositeur et du PDF sont closes pour le moment à la demande de l’utilisateur le 26 novembre.
- Portail structures accessible avec comptes, séances, comptines, profil et impersonation ; le bandeau du portail et la stabilité du menu sont livrés.
- Règle de mot de passe du portail centralisée. Le traitement d'une réussite partielle lors de l'édition du profil reste distinct et ouvert.
- Notifications de première disponibilité par structure, préférences distinctes du profil, audiences annuaire/portail/abonnés libres, contrôle du consentement avant envoi, inscription publique confirmée depuis le footer et gestion centralisée des abonnements dans Mailing.
- Récapitulatif de fin de mailing et corrections d'affichage des médias du répertoire dans le portail livrés en production le 5 octobre 2026 (`deploy-mailing-summary-media-20261005-01`).
- Page « Mentions légales et confidentialité » publiée et reliée au footer, au sitemap et aux métadonnées. Les compléments sont reportés faute d'informations disponibles ; ne pas les relancer avant le 5 novembre 2026 et limiter ensuite le rappel à une fois par mois maximum.
- Page d'offre `/eveil-musical-creche` publiée avec la fiche « Crèches & EAJE » actualisée sur l'accueil, le PDF d'exemple, les photos WebP, le fil d'Ariane, les données structurées et le sitemap (`deploy-eveil-musical-creche-20261007-01`).
- Fiche Google Business Profile existante évaluée et complétée comme activité de zone desservie le 8 octobre : adresse publique masquée, zones et horaires renseignés, photo de couverture choisie et profil de réseau social ajouté. La catégorie « Artiste » est conservée faute de meilleure catégorie proposée. Le favicon Flaticon est crédité sur la page de mentions légales dans la livraison du 8 octobre.

## Point de reprise — 2 novembre 2026

**Point de reprise — 27 octobre 2026 :** les comptes portail sont dans l’onglet Technique du backoffice métier ; l’accueil interne renvoie au suivi clients. La recette locale des parcours et e-mails est validée (`docs/recettes/gestion-comptes-portail-local.md`). Le transfert des Régions, Départements et Communes est en production depuis le 26 octobre sous `deploy-geography-backoffice-20261026-01` (`8e2a7c28`).

Le lot backoffice annuaire, abonnements newsletter et retrait d’EasyAdmin est en production depuis le 27 octobre sous `deploy-backoffice-annuaire-mailing-20261027-04` (`f6a09ce3`). Il inclut les structures et personnes autonomes, coordonnées multiples, carte de commune qui se rafraîchit au changement de fiche, recherche paginée et édition par section ; l’index newsletter distingue l’abonnement partagé de l’adresse des fiches rattachées, avec tri et actions compactes. Les téléphones français s’affichent en groupes visuels et se copient au format national sans séparateurs. La sous-navbar est masquée pour l’annuaire sans empêcher les autres rubriques de s’ouvrir ; le panneau de fiche et la transition vers l’annuaire sont animés, dans le respect des préférences de mouvement réduit. Les migrations `Version20261027111508` et `Version20261027160000` ont été exécutées : les champs ville/code postal redondants ont été supprimés et le secteur du Ludobus est rattaché à Saint-Chamond pour le mailing. EasyAdmin et sa dépendance Composer sont retirés. La base de production est à 76 migrations exécutées.

Photos multiples sur la page « Amis et partenaires » et éventuel lien vers la crèche des Jardins du Grand Clos : mis de côté à la demande de l’utilisateur le 2 novembre, sans action prévue pour le moment.

La page « Amis et partenaires », partagée par lien depuis le 30 octobre, est publiée le 2 novembre sous `deploy-partenaires-public-20261102-01` (`9a97269c`) : liens dans les menus, indexation autorisée et sitemap actif. Le texte précise la cocréation du spectacle « Le chant des faunes ». L’accueil, la page, le sitemap et robots.txt répondent en HTTP 200 ; les liens, la directive d’indexation et le sitemap ont été vérifiés en production.

Le retour en haut des pages de la vitrine est corrigé et déployé sous `deploy-scroll-navigation-20261102-01` (`a5359fe7`). Le lint, le build et les transitions locales vers les deux pages passent ; les trois pages répondent en HTTP 200 et portent le réglage attendu en production. La recette navigateur complète en production a été limitée par Tiger Protect (HTTP 429).

Le moteur de mailing géographique s’appuie sur les communes d’adresse. Le secteur du Ludobus reste rattaché à Saint-Chamond comme point de référence, sans représenter ses 18 communes desservies : l’utilisateur choisit de conserver ce fonctionnement et de clore cette piste le 2 novembre. **Bilan mailing déployé le 2 novembre sous `deploy-bilan-mailing-20261102-01` (`37fb8cec`) :** statistiques d’envoi et listes distinctes des adresses et établissements associés, avec recherche et pagination côté serveur. Les adresses et leurs états sont historiques ; les établissements reflètent les liens actuels de l’annuaire et du portail, car les rattachements à la date d’envoi ne sont pas conservés. Les ouvertures et clics ne sont pas suivis. Les tests obsolètes ont été adaptés aux communes, à l’ordre des noms et aux évolutions d’interface. L’action de retrait d’un interlocuteur du suivi a été rétablie avec confirmation. La suite backend complète passe (567 tests, 2 930 assertions ; 2 dépréciations et 4 notices PHPUnit), ainsi que PHPStan, style et Twig. La route de bilan est présente en production et le backoffice répond ; la recette visuelle et interactive authentifiée reste à faire. Les champs supplémentaires des API géographiques restent à évaluer séparément. Le ministère de la Culture a confirmé la disponibilité du [jeu de données ouvert des lieux culturels](https://www.data.gouv.fr/datasets/base-des-lieux-culturels-ouverts) et l’existence d’une API. Le 2 novembre, l’API tabulaire publique de data.gouv.fr a répondu sans authentification : un filtre sur les bibliothèques et médiathèques de la Loire retourne 239 fiches ; les cinq premières comportent un téléphone mais pas de courriel, et 12 des 200 premières un courriel. Les filtres Loire par sous-domaine retournent aussi 9 théâtres, 4 lieux de musique et 1 centre culturel ; ces résultats ne garantissent pas une couverture exhaustive. Le catalogue API indique la licence ouverte `lov2`, alors qu’une page consultée affichait ODbL : clarifier la licence avant réutilisation. Le ministère publie les listes de domaines, sous-domaines, types et labels ; son API directe est distincte de l’API tabulaire et sa documentation demande de contacter le webmaster pour commencer. Examiner la couverture, les champs et le rapprochement avec les fiches existantes avant tout import.

Le dépôt GitHub public a été nettoyé le 22 octobre : l'historique des 8 branches publiées et des 166 tags a été réécrit pour retirer `jardin-sonore-backend/data/`, une ancienne photo source avec coordonnées GPS et une adresse nominative de maquette remplacée par `marie@example.test`. Un clone neuf du dépôt distant ne retrouve aucun de ces chemins ou texte dans ses références. `data/` est désormais ignoré par Git et exclu du déploiement backend ; les imports restent locaux et ne seront transférés en production que manuellement si nécessaire. Les anciens identifiants de commit cités dans les documents antérieurs à cette purge ne sont plus les identifiants publiés. Les anciens worktrees locaux conservent encore leur historique initial : ne pas pousser ni fusionner leurs branches sans les raccorder à l'historique nettoyé. Une purge Git ne retire pas les copies déjà faites ni nécessairement les vues GitHub en cache ; solliciter GitHub Support si leur suppression s'avère nécessaire.

Après validation des sept paires par l'utilisateur, le dédoublonnage a été appliqué en production le 22 octobre dans une transaction avec sauvegarde préalable : Cerf-Volant Chazelles, MJC Vienne, médiathèques Le Trente et Condrieu, médiathèques-ludothèques Perreux et Chassieu, centre social Chadrac. Les liens d'import, tags et coordonnées utiles ont été conservés ; les sept fiches retirées n'avaient ni dossier commercial, ni séance, ni accès portail, et les autres références ont été contrôlées. Les neuf liens Vienne/Pilat ont été déplacés vers leur source propre `rattrapage-vienne-pilat`. Le site Ribambulles « Maison de quartier du Soleil » a été créé par choix interactif explicite, après rejet du rapprochement à 83 % avec le site Crêt de Roch. Le contrôle final trouve **346 liens d'import vers 343 fiches distinctes** pour les 346 lignes des nouveaux JSON ; les sept anciens identifiants ont disparu. Les deux équipements médiathèque-ludothèque restent de type `mediatheque` malgré les liens JSON de type `ludotheque`.

Le lot MCP « catalogues, images et notes » a été déployé sur le backend le 22 octobre sous `deploy-mcp-catalogues-rdv-20261022-01` (`51cb527d`) : recherche, lecture, création et modification du répertoire, des instruments, des ressources média, des prolongements et des recommandations réutilisables du mailing ; rattachement et détachement explicites d'une fiche du catalogue à un brouillon ; photo JPEG/PNG/WebP transmise en Base64 (5 Mo maximum) ; note libre mise en forme dans le fil du dossier. Le champ d'URL d'image externe des prolongements est retiré et sa colonne devient `image_path` ; la migration `Version20261022120912` est exécutée en local et en production, désormais à 72/72 migrations et sans écart de schéma. Le backoffice répond par une redirection vers sa page de connexion. Les écritures des catalogues de séance restent fermées par défaut avec `MCP_CATALOG_WRITE_ENABLED=0` ; celles du catalogue mailing suivent `MCP_MAILING_WRITE_ENABLED`. La compatibilité du connecteur avec une image Base64 de plusieurs mégaoctets reste à essayer sur un vrai appel. Voir la [conception](docs/superpowers/specs/2026-10-22-mcp-catalogues-design.md) et le [plan](docs/superpowers/plans/2026-10-22-mcp-catalogues-notes-images.md). Les outils MCP RDV et tâches ont été confirmés « Demander » dans Vibes. Après une actualisation de Studio qui affichait les outils « Autorisé », l'utilisateur a vérifié que l'interface Vibes conserve bien les confirmations ; tous les verrous d'écriture MCP existants ont donc été remis à `1` en production le 22 octobre et leur cache vidé. Les rendez-vous et tâches ne sont pas désactivés. Les outils MCP de devis ont été déployés sous `deploy-mcp-commercial-quotes-20261022-01` (`8de16a7b`) : lecture, enregistrement d'un envoi avec remplacement facultatif et décisions signé/refusé. Après capture de leur réglage « Demander » dans Vibes, `MCP_QUOTE_WRITE_ENABLED=1` a été activé en production le 22 octobre ; le cache est vidé et la valeur effective `1` vérifiée. Aucun enregistrement réel n'a servi de test. Les recettes calendrier et brouillons semblent satisfaisantes selon le retour utilisateur du 22 octobre ; les reprendre si un problème concret apparaît.

Un aller-retour de fuseau horaire a été identifié sur les rendez-vous de dossier : les heures reçues de Google étaient stockées telles quelles dans une colonne Doctrine sans fuseau, puis relues comme UTC, ce qui produisait un faux changement à chaque ouverture. Le correctif déployé normalise les instants en UTC avant stockage ; les événements techniques de rendez-vous déjà accumulés sont masqués du fil, tandis que la carte du rendez-vous reste visible. Les actions de la carte sont regroupées à droite. Une première synchronisation des rendez-vous existants corrigera leur heure stockée ; les anciens événements restent en base. La stabilité après plusieurs ouvertures du même dossier reste à confirmer en usage réel.

Le suivi des devis passe par `RecordCommercialQuote` avec référence, nom de fichier, montant, date et remplacement facultatif. Le MCP sait lire les devis, enregistrer un envoi (avec remplacement facultatif), puis noter signé/refusé. Les outils d'écriture sont actifs derrière les confirmations du client et le verrou dédié `MCP_QUOTE_WRITE_ENABLED=1`. La suite MCP prioritaire est la lecture et la gestion des séances à partir des éléments existants du répertoire ; aucune action MCP ne doit publier une séance ou envoyer une notification sans cadrage séparé. Conserver séparées les recommandations du catalogue, celles d'une séance et celles d'un mailing.

### Historique du 20 octobre 2026

Le suivi commercial et l'harmonisation du backoffice ont été déployés sous `deploy-suivi-clients-backoffice-20261014-01` (`fb71830`), fusionnés dans `main`. La livraison comprenait l'index Dossiers unique, la fiche dossier réorganisée, les tâches et le fil, les mises à jour du rapport du matin, les contrôles d'index, ainsi que l'harmonisation des formulaires de catalogue. À cette date, la production comptait 63 migrations exécutées, aucune en attente, et le schéma Doctrine était synchronisé. La migration `Version20261013132221` avait été appliquée ; la reprise avait créé un dossier historique et son second passage n'en avait créé aucun. Les pages publiques répondaient en HTTP 200 et l'index backoffice renvoyait à l'authentification (HTTP 302). Les 416 tests backend et 2 274 assertions passaient (une notice PHPUnit). Le client n'avait pas été redéployé : il n'avait aucune différence depuis le tag du 12 octobre déjà présent en production.

Le suivi commercial V1 initial avait été livré sous `deploy-commercial-follow-up-20261009-01` (`3e27c4f`). Les trois crons commerciaux cPanel sont installés. Le secret BFF backend nécessaire au formulaire du site est configuré dans le `.env.local` de production, exclu du déploiement backend ; l'utilisateur a confirmé que l'envoi fonctionne. Le déploiement client précédent est `deploy-client-security-20261009-01` (`d3da04b`).

La refonte du suivi clients est cadrée dans la [conception dossier d'abord](docs/superpowers/specs/2026-10-13-suivi-clients-dossier-first-design.md) et le [plan d'exécution](docs/superpowers/plans/2026-10-13-suivi-clients-dossier-first.md). **Un dossier porte le suivi dès le premier contact**, qu'il soit créé manuellement ou depuis le site. La demande reçue est conservée comme source brute du premier élément du fil ; les tâches « À faire » restent distinctes du fil des faits. Une structure est nécessaire à la création manuelle, tandis qu'une entrée du site attend son rattachement explicite.

La refonte a été développée sur `feat/suivi-commercial-v1`, puis fusionnée en fast-forward dans `main` à `fb71830`. Le modèle dossier, la création depuis le site, les tâches, le fil, les états, l'index unique et l'interface revue sont maintenant en production. La migration indépendante de normalisation de l'annuaire reste en attente sur la base locale de développement ; elle n'était pas incluse dans cette livraison.

La refonte visuelle a été avancée avant la partie 5 à la demande de l'utilisateur. Les groupes de l'index Dossiers distinguent les urgences, les échéances à venir et les dossiers sans tâche. Le rapport personnel du matin renvoie aux dossiers sans l'ancienne rubrique de demandes. Sur la fiche, Contacts et Documents/Suivi sont repliables, avec un état initial adapté à la largeur d’écran ; les ajouts de tâches et d’échanges viennent avant les listes. Les formulaires concernés gardent le retour au-dessus du titre et regroupent la suppression dans l’en-tête. **La recette visuelle authentifiée sur téléphone et ordinateur reste à compléter**, en particulier pour confirmer le comportement des nouveaux volets repliables en conditions réelles.

Le 14 octobre, les libellés d'événements de devis et les décisions « signé/refusé » ont été corrigés et déployés (`deploy-commercial-quote-decisions-20261014-01`, commit `44e986c`). Le remplacement d'un devis ne bloque pas le refus de la version précédente.

Le 15 octobre, le mail quotidien de suivi a reçu une mise en forme plus lisible, les coins des tableaux et volets Contacts/Documents ont été corrigés, et le fil masque les événements automatiques de tâches sauf leur conclusion avec précision. Ces changements sont en production (`deploy-commercial-completion-highlight-20261015-01`, commit `10d023f`).

### Assistant Mistral pour le suivi client — architecture réorientée vers IMAP

Le connecteur MCP personnalisé a été validé dans Mistral Studio : les recherches puis lectures de messages réels fonctionnent depuis la conversation. La messagerie professionnelle reste chez cPanel, accessible par IMAP. Le serveur accepte les versions MCP `2025-03-26` et `2025-06-18` ; le lecteur utilise `FT_PEEK` pour éviter de marquer les messages comme lus. La recherche reste limitée à INBOX et aux termes littéraux ; l'essai « Badabins » n'a pas retrouvé le dossier « Les P'tits Babadins ». Les résultats de `search_mail` sont présentés par UID décroissant, et la description des outils oriente Mistral vers `search_projects` pour identifier une structure et vers une recherche par date seule pour les messages récents.

Le lot suivant est déployé sous `deploy-mcp-commercial-20261019-01` (`c96ee9d`) : six outils sont exposés, dont `get_project_context`, `record_mail_summary` et `create_project_reminder`. Les deux écritures gardent une référence au mail source, résistent aux répétitions et laissent les messages IMAP non lus. La migration `Version20261019102122` est appliquée en production ; 65 migrations sont exécutées, aucune en attente. La recette HTTP authentifiée a confirmé la liste des six outils, la lecture du dossier 1 et le refus d'écrire avec le réglage serveur par défaut. Après reconnexion du compte dans Mistral, les six outils sont visibles ; les deux écritures sont classées « Interactif » et réglées sur « Demander ». `MCP_WRITE_ENABLED=1` a été ajouté au `.env.local` de production, puis le cache Symfony a été vidé. Un appel authentifié de chacun des deux outils avec un identifiant de dossier invalide renvoie « Invalid project ID. » sans créer de donnée. Les 474 tests backend et 2 596 assertions passent ; PHPStan, CS et schéma Doctrine sont valides. La [conception IMAP](docs/superpowers/specs/2026-10-17-assistant-mcp-imap-suivi-dossiers-design.md), la [conception des écritures](docs/superpowers/specs/2026-10-19-assistant-mcp-commercial-followup-design.md) et l'[enquête initiale](docs/enquete-assistant-gmail-suivi-client-2026-10-15.md) gardent le détail. Ne prendre un abonnement Mistral payant qu'après vérification d'une limite effective sur le compte gratuit ou d'un besoin de capacité supérieur.

Le lot « réponses envoyées et brouillons » est déployé sous `deploy-mcp-imap-drafts-20261019-01` (`cc0d6d1`). Le MCP expose huit outils : `find_sent_replies` lit jusqu'à trois réponses rattachées par les en-têtes du message source, et `save_mail_draft` prépare une réponse texte dans le dossier IMAP Brouillons sans jamais l'envoyer. Le destinataire et l'objet viennent du message reçu vérifié ; un accusé en base et un identifiant de mail déterministe empêchent de créer silencieusement un second brouillon du même mail. Les dossiers cPanel sont `INBOX.Sent` et `INBOX.Drafts` ; l'utilisateur a vérifié qu'un brouillon créé dans Thunderbird sur téléphone apparaît dans Roundcube. La migration `Version20261019121753` est appliquée en production. L'utilisateur a confirmé « Demander » pour `Save Mail Draft` dans Mistral ; `MCP_DRAFT_WRITE_ENABLED=1` a ensuite été activé. Le premier essai réel n'a pas créé de message : Webklex avait recherché les chemins `INBOX.Drafts` et `INBOX.Sent` comme des noms de dossier à cause du séparateur IMAP `/` par défaut. Le correctif `deploy-mcp-imap-folder-path-fix-20261019-01` (`6070865`) sélectionne maintenant ces chemins explicitement. Le contrôle IMAP confirme que le message de l'essai est absent ; sa seule réservation `uncertain`, sans UID, a été supprimée après vérification pour permettre un nouvel essai sur le même mail. La création réelle dans Mistral, avec confirmation demandée à nouveau, reste à valider. Aucun envoi MCP n'est prévu. La [conception](docs/superpowers/specs/2026-10-19-assistant-mcp-brouillons-imap-design.md) et le [plan](docs/superpowers/plans/2026-10-19-assistant-mcp-brouillons-imap.md) détaillent les cas limites.

L'édition des brouillons MCP est déployée sous `deploy-mcp-imap-draft-edit-20261019-01` (`34d89a9`). Les outils `read_mail_draft` et `update_mail_draft` portent le connecteur à dix outils : lecture du brouillon et de sa révision, puis remplacement du texte complet après confirmation séparée. Le nouveau brouillon est vérifié avant la suppression ciblée de l'ancien par `UID EXPUNGE` ; le serveur IMAP annonce `UIDPLUS`. Un état intermédiaire en base permet de reprendre un remplacement incertain sans dupliquer silencieusement le brouillon. Seuls les brouillons créés par le MCP et rattachés à un mail source sont éditables ; aucun outil n'envoie de mail. La migration `Version20261019131714` a été appliquée seule sur les bases locales puis en production ; le déploiement a désactivé les migrations automatiques. Le schéma de production est valide, les dix outils sont visibles via l'API authentifiée et un appel invalide à l'édition est rejeté avant tout accès IMAP. `MCP_DRAFT_EDIT_ENABLED=1` est actif en production à la demande de l'utilisateur. Les 27 tests ciblés (142 assertions), PHPStan et le contrôle de style passent. Vérifier maintenant dans Mistral que `Update Mail Draft` est réglé sur « Demander », puis créer et éditer un brouillon réel dans Thunderbird/Roundcube sans l'envoyer.

Le premier test de brouillon a utilisé le digest automatique `Jardin Sonore — à faire le 19/10/2026`, envoyé par la boîte à elle-même. `replyContext()` refuse correctement une réponse à un message auto-envoyé ; l'erreur était présentée à tort comme un échec générique, et la lecture du brouillon passait inutilement par cette validation. Le correctif `deploy-mcp-imap-draft-self-mail-20261019-01` (`3fd940a`) fait retourner `not_found` à `read_mail_draft` quand aucun brouillon n'est lié, précise le refus de répondre à un mail de la boîte elle-même et indique dans la description de `save_mail_draft` de choisir un mail entrant. Le contrôle API de production confirme ces deux comportements ; aucun brouillon n'a été créé par le test auto-envoyé.

Le lot `deploy-mcp-contact-replyto-compose-20261019-01` (`ac399ff`) autorise les mails du formulaire envoyés par `contact@jardin-sonore.fr` quand ils portent un `Reply-To` externe unique et valide. Il ajoute aussi `create_mail_draft`, outil indépendant qui crée un brouillon texte pour un destinataire et un objet explicites, sans mail source ni envoi. Les entrées identiques retrouvent le brouillon via un identifiant déterministe. Après confirmation de l'utilisateur que Mistral demande une validation, `MCP_DRAFT_COMPOSE_ENABLED=1` a été activé en production et le cache Symfony vidé. Le contrôle authentifié du 19 octobre confirme les 11 outils et rejette un corps vide avant toute écriture ; aucun brouillon de contrôle n'a été créé. Les tests ciblés valident Reply-To, idempotence, validations et verrouillage ; PHPStan, style et lint du conteneur passent.

Les trois outils d'écriture de brouillon (`save_mail_draft`, `update_mail_draft`, `create_mail_draft`) sont actifs côté serveur et doivent rester réglés sur « Demander » dans Mistral. La création libre et les autres parcours réels restent à tester dans le client IMAP. Les pièces jointes ne sont pas encore prises en charge par le MCP : la proposition actuelle est de les ajouter manuellement dans Thunderbird/Roundcube ; voir la [conception des brouillons](docs/superpowers/specs/2026-10-19-assistant-mcp-brouillons-imap-design.md) avant de cadrer une éventuelle copie depuis un mail source.

La suite des écritures commerciales reste à tester dans Mistral : refus de confirmation, puis résumé et rappel acceptés séparément sur un dossier choisi. Vérifier les entrées du backoffice, l'absence de doublons et l'état non lu dans le client mail. Chercher aussi « Les P'tits Babadins » avec `search_projects` et ses mails à partir du contact exact. Le contenu complet des mails reste dans la boîte ; le client mail de l'utilisateur sert à relire et envoyer les brouillons. L'utilisateur créera un compte Google professionnel distinct pour Drive.

L'usage visé est une demande matinale dans le chat, avec confirmation avant chaque écriture. Les tâches planifiées restent une piste ultérieure pour un récapitulatif en lecture seule.

### Suites MCP décidées le 19 octobre 2026

1. **Mailings : créer et modifier des campagnes complètes en brouillon.** Déployé le 19 octobre 2026 sous `deploy-mcp-mailing-drafts-20261019-01` ; les outils MCP créent et modifient les campagnes, lisent les masques d'audience et appliquent le rendu du mailing. Voir le bilan de livraison ci-dessous.
2. **Rappels généraux d'entreprise : piste réactivée le 26 novembre, à cadrer en version légère.** Priorité à une petite liste facile à administrer dans le backoffice et via MCP, indépendante des dossiers, avec le minimum de champs et d’actions. Ils devront aussi apparaître dans le mail du matin (choix confirmé le 26 novembre). Aucun développement de rappels dans le lot recommandations mailing. Ils seront indépendants des dossiers commerciaux, qui gardent leurs propres actions. Prévoir des rappels ponctuels pour les tâches de l'entreprise et des rappels récurrents, par exemple « préparer le prochain mailing ». Mistral pourra les consulter et, après confirmation, les créer, modifier ou terminer. Un rappel de mailing sert à préparer une campagne ; il ne crée, programme ni envoie la campagne. La règle de récurrence (intervalle, date de prochaine occurrence et moment où elle est renouvelée) reste à préciser pendant la conception.
3. **Répertoire pédagogique via MCP.** Première étape vers les séances : permettre au MCP de créer, lire et modifier les éléments du répertoire, selon les mêmes règles métier que le backoffice. Garder les médias et les relations du répertoire dans ce périmètre ; le MCP ne doit pas déduire ou modifier des séances lors de ces opérations.
4. **Simplifier le formulaire et le compositeur des séances.** Première étape déployée le 21 octobre 2026 sous `deploy-session-composer-20261021-01` : suppression des notes internes des éléments avec migration exécutée en local et en production, correction de la mise en page des médias, retrait des options « à la une / afficher dans la séance », libellé lisible pour le type de média et regroupement des actions de rendu/PDF et de publication près du titre. Prochaine passe : retirer ce qui est encore inutile dans les formulaires, clarifier le rôle des blocs et corriger les comportements JavaScript qui gênent l’usage, avec un refactor minimal. Lorsqu'un élément du répertoire est ajouté à une séance, ne pas modifier sa fiche ; conserver les consignes « en plus » propres à la séance et limiter les créations à la volée aux médias de ses éléments propres.
5. **Séances via MCP, après la simplification du parcours.** Permettre leur lecture et leur création, puis l’édition si elle s’intègre simplement au modèle, en réutilisant les éléments du répertoire. Le MCP pourra gérer les listes d’éléments et les consignes propres à la séance ; il ne modifiera pas les fiches du répertoire lorsqu'il ajoute ou retire un élément. La création MCP restera en brouillon. Aucun outil ne publiera une séance ni n’enverra de notification sans cadrage et confirmation distincts.

L'intégration du calendrier dans les dossiers est déployée en production sous `deploy-commercial-calendar-20261016-01` (`b75705a`). Elle comprend la connexion OAuth chiffrée, la création/modification/annulation des séances, le rattachement manuel groupé, le rafraîchissement périodique et à l'ouverture du dossier, l'aperçu des deux semaines dans le mail commercial, le rappel de facture mensuel et la fiche dossier revue. Les cinq paramètres Google sont configurés dans le `.env.local` de cPanel ; la migration `Version20261016084010` est appliquée et le schéma est synchronisé. Le cron de rafraîchissement tourne toutes les 15 minutes. La connexion OAuth par l'utilisateur, puis la recette d'une séance réelle ou de test (création, déplacement, rattachement et aperçu du mail) restent à faire. La [spécification](docs/superpowers/specs/2026-10-15-calendrier-dossiers-commerciaux-design.md) et le [plan d'implémentation](docs/superpowers/plans/2026-10-15-calendrier-dossiers-commerciaux.md) gardent le détail ; aucune vue agenda globale n'est prévue.

La génération des devis depuis le classeur existant est reportée : ChatGPT et son connecteur conviennent actuellement pour préparer les devis. Le backoffice conserve leur suivi manuel. La recette visuelle authentifiée mobile et desktop reste ouverte.

La prochaine séquence après la simplification des séances est leur création et leur lecture via MCP, avec réutilisation du répertoire. Ensuite, la priorité front sera la page SEO de collaborations et de liens partenaires : présenter des personnes et structures, apporter du contexte éditorial utile et relier les sites partenaires. Obtenir les accords adaptés avant publication de noms, citations, logos ou photos et proposer aux partenaires un lien vers la page pertinente.

### Historique du 8 octobre — avant le suivi commercial

À cette date, prochaine étape : cadrer l'enregistrement des demandes dans le backoffice, depuis le formulaire du site et les autres canaux, en conservant Gmail pour les échanges. La page d'offre `/eveil-musical-creche` a été livrée le 7 octobre. La fiche « Crèches & EAJE » de l'accueil présente la séance, le portail, quatre repères pratiques et un bouton vers la page dédiée ; le lien redondant sous les trois cartes de formats a été retiré. La page comprend des sections dédiées, un témoignage court, des photos WebP sans métadonnées EXIF/XMP, le PDF « Séance médiévale — Le dragon », des métadonnées canoniques et Open Graph, une entrée sitemap, un fil d'Ariane et des données structurées. Les textes sont dans le dictionnaire français. Les liens directs vers les formats, le clavier, les ancres et les balises rendues ont été contrôlés localement ; lint et build Next ont réussi pendant la livraison. En production, l'accueil, la page d'offre, le sitemap et le PDF répondent en HTTP 200 ; le sitemap contient la nouvelle URL et `robots.txt` autorise son exploration. Le catalogue des composants et mouvements de la vitrine est dans [Identité visuelle de la vitrine](docs/identite-visuelle-vitrine.md).

Le cadrage métier à préserver : cible principale constituée des crèches/EAJE, relais petite enfance et services petite enfance des mairies ; cœur d'offre constitué des ateliers avec les enfants et les professionnel·les. Présenter la séance concrète, l'approche artistique et les objets utilisés ; garder la co-construction comme proposition facultative de transmission et de montée en compétence. Signaler le portail dès le haut de page, avant sa présentation détaillée plus bas ; reléguer les jardins sonores, les temps forts et les ateliers parents-enfants dans un encart secondaire. Mettre en avant le Forez, le Gier et le Pilat comme secteurs habituels, et le Lyonnais selon les projets. Le contenu reste destiné aux professionnel·les qui cherchent un intervenant en structure, pas aux familles qui cherchent des cours.

L'analyse initiale Search Console est faite à partir des exports du 5 octobre. Sur les trois derniers mois, le site a eu 30 clics et 395 impressions ; seule la page d'accueil apparaît dans l'export des pages. Les requêtes visibles pour « intervenant musical en crèche » (2 impressions, position moyenne 5,5) et « éveil musical crèche » (1 impression, position 20) ont un faible volume, mais correspondent à la cible prioritaire. Le tableau des requêtes n'inclut pas toutes les recherches, notamment les requêtes anonymisées.

Le rapport d'indexation du 5 octobre montrait une page non indexée : la capture Search Console identifie `http://jardin-sonore.fr/`, redirigée vers la version HTTPS canonique. Cette redirection est attendue ; le sitemap contient désormais aussi la page d'offre. Aucun autre problème d'indexation n'est signalé dans cet export antérieur à la publication de la page.

La fiche Google Business Profile a été revue avec l'utilisateur le 8 octobre. Elle ne présente aucun établissement physique à visiter et son adresse n'est pas publiée. Les zones affichées incluent Saint-Étienne, le Gier, le Pilat et Lyon ; les horaires indiquent lundi à vendredi, 8 h–18 h. Une photo d'atelier est définie comme couverture et un profil de réseau social a été ajouté. Le numéro de téléphone reste absent de la fiche, conformément au choix antérieur de ne pas l'exposer publiquement. La description visible sur la dernière capture cible bien les crèches et EAJE, mais contenait encore « Le Jardin Sonore, propose » ; la correction proposée n'a pas été confirmée par une nouvelle capture. Les projets, témoignages et liens de partenaires restent des leviers complémentaires à la page d'offre.

L'alerte npm `GHSA-vfj7-8cjw-p6xm` est à surveiller : elle concerne `braces` dans la chaîne de dépendances d'`eslint-config-next` ; aucun correctif n'était publié au 5 octobre. Ne pas appliquer le downgrade majeur proposé par `npm audit fix --force`.

Les actions administrateur d'invitation/réinitialisation, les lots notifications/newsletter et la page légale sont livrés. Le rendu du courriel de disponibilité est considéré bon dans Gmail et Outlook selon le souvenir de l'utilisateur ; la date et le compte utilisés n'ont pas été consignés. Le contrôle de provenance de l'IP du portail reste accepté en l'état par l'utilisateur, sans preuve que `x-forwarded-for` ne puisse pas être falsifié ; voir la réserve ci-dessous.

### Livraisons récentes

- 16 octobre : finition de l'index des dossiers et du fil commercial (`deploy-backend-20261016-07`), puis ajustement de l’index (`deploy-backend-20261016-08`) : état seulement dans sa colonne, badge « Nouveau » bleu limité à aujourd’hui et hier. Les lignes d'index sont compactes et montrent la date du dernier élément du fil ; les dossiers confirmés ont leur propre groupe, les dossiers sans tâche restent à confirmer, et les séances à venir sont regroupées par semaine civile. Les actions directes de tâche ont été retirées de l'index pour ouvrir le dossier avant modification. Le fil affiche le type après la date, l'interlocuteur avec une icône et les notes avec des retours à la ligne compacts ; la mise en forme des notes peut être effacée sur une sélection. Aucun changement de schéma ni migration. Suite : recette visuelle authentifiée sur ordinateur et mobile selon l’usage.
- 8 octobre : revue de la fiche Google Business Profile et crédit Freepik/Flaticon ajouté à la page de mentions légales ; aucun changement backend ni migration.
- 7 octobre : page d'offre crèche/petite enfance, mise à jour de la fiche de l'accueil et navigation vers les formats ; commit `af29dc7`, tag `deploy-eveil-musical-creche-20261007-01`. Lint et build client réussis ; accueil, page, sitemap et PDF vérifiés en HTTP 200 sur le site public. Aucun changement backend ni migration.
- 30 septembre : première disponibilité et préférences newsletter, tag `deploy-availability-newsletter-20260930-01` ; migrations `Version20260930130613` et `Version20260930140509` appliquées en production, schéma et trois crons contrôlés lors de cette livraison.
- 1er octobre : audiences et contrôle du consentement avant SMTP, tag `deploy-newsletter-audiences-consent-20261001-01` ; puis gestion des abonnés libres et inscription footer avec confirmation, tag `deploy-newsletter-footer-20261001-01`. Deux corrections de présentation de la confirmation ont suivi sous `deploy-newsletter-confirmation-layout-20261001-01` et `deploy-newsletter-confirmation-layout-20261001-02`. La migration `Version20261001101645` et les migrations antérieures sont confirmées appliquées en production au 5 octobre, avant la migration `Version20261005170000`.
- 2 octobre : page légale et contrôles SEO/accessibilité du front, commit `2b69a8b`, tag `deploy-legal-privacy-20261002-01`.
- 5 octobre : récapitulatif de fin de mailing, libellés distinctifs pour les médias homonymes et affichage des PDF/images liés dans le portail ; commit `a68c479`, tag `deploy-mailing-summary-media-20261005-01`. Les 311 tests backend et 1 706 assertions passent (une notice PHPUnit) ; lint PHP/PHPStan et lint/build client réussis. Migration appliquée en production et base à jour ; site public vérifié HTTP 200.

### Vérifications de livraison à garder en vue

- Les compléments de la page légale sont différés faute d'informations ; ne pas les relancer avant le 5 novembre 2026, puis au plus une fois par mois.
- Sécurité du portail : les actions administrateur sont protégées et testées. L'audit de la provenance de l'IP utilisée pour limiter les tentatives a été tenté le 5 octobre, mais Tiger Protect a renvoyé HTTP 429 ; le compte cPanel ne donne pas accès à la configuration Apache globale ni aux journaux d'accès. **Accepté en l'état pour le moment, sans preuve que `x-forwarded-for` ne puisse pas être falsifié.** Reprendre uniquement si l'hébergeur fournit la configuration du proxy ou si une recette de production non bloquée devient possible ; corriger si l'usurpation est alors constatée. Détails dans le [plan dédié](docs/superpowers/plans/2026-09-29-portal-admin-actions-security.md).
- Contrôler les journaux d'accès de l'hébergeur pour la confirmation newsletter si une vérification de production est reprise. Le jeton est placé dans le fragment du lien, puis envoyé dans le corps d'un POST à chemin constant afin de ne pas figurer dans l'URL de requête.

La [conception des disponibilités](docs/superpowers/specs/2026-09-30-session-availability-notifications-design.md), le [plan de recette notifications](docs/superpowers/plans/2026-09-30-session-availability-notifications.md) et le [plan de recette newsletter](docs/superpowers/plans/2026-10-01-newsletter-footer-and-backoffice.md) gardent les détails d'exécution. Ils ne redéfinissent pas le prochain chantier.

## P1 — Refonte du suivi clients par dossiers

| Étape | État local | Résultat attendu |
| --- | --- | --- |
| Modèle compatible avec les anciennes demandes, création manuelle et rattachement des dossiers provisoires. | Déployé ; migration appliquée et reprise historique exécutée sans doublon. | Un seul dossier suivi, avec structure liée dès la création manuelle. |
| Contact du site. | Déployé. | Un dossier provisoire et une tâche « Répondre » créés une seule fois, sans structure inventée ni doublon à la relance. |
| Tâches, fil et statuts. | Déployé. | Commandes distinctes, correction des notes et tâches terminées, changement manuel du statut avec garde sur les factures impayées. |
| Devis, factures et documents. | Suivi manuel déployé ; décisions signé/refusé livrées le 14 octobre ; automatisations et générateur restent à cadrer. | États et références courts, événements fiables dans le fil et accès aux documents sans masquer le dossier. |
| Page Dossiers unique. | Déployée ; lignes compactées, état et dernière activité visibles, groupes distincts pour les dossiers confirmés et ceux sans tâche, périodes à venir organisées par semaine. Recette indiquée comme faite par l’utilisateur le 24 novembre. | Continuer à recueillir les retours d'usage. |
| Détail du dossier et cohérence du backoffice. | Déployés ; recette indiquée comme faite par l’utilisateur le 24 novembre. | Faire évoluer les volets, liens de contact, actions et formulaires selon les retours d’usage. |

L'archivage des dossiers fermés reste à définir après usage réel ; il devra préserver l'historique et alléger les listes principales. Le [guide des deux parcours](docs/suivi-clients-parcours-concrets-2026-10-12.md) conserve les situations qui ont lancé cette refonte ; les décisions courantes sont dans la conception du 13 octobre.

**Livré le 19 octobre 2026 :** outils MCP de lecture, création et édition des campagnes de mailing, déployés sous le tag `deploy-mcp-mailing-drafts-20261019-01`. L'édition est limitée aux campagnes dont l'envoi n'a pas commencé ; les autres restent lisibles pour référence. Le MCP ne peut ni envoyer ni planifier. Pour les recommandations propres à chaque mailing, il peut lire, créer sans image, modifier et retirer les cartes ; après création, il doit rappeler à Emeric d'ajouter chaque image manquante dans le backoffice. Les cinq outils mailing ont été vérifiés sur le serveur, et `MCP_MAILING_WRITE_ENABLED=1` est actif en production. Détail : [conception des campagnes de mailing MCP](docs/superpowers/specs/2026-10-19-assistant-mcp-mailing-campaigns-design.md). La suite initialement prévue avec les rappels généraux a été réordonnée le 20 octobre, comme indiqué ci-dessous. Les recettes des brouillons IMAP et des écritures commerciales restent à vérifier dans les logiciels et le backoffice. Garder la recette visuelle des index Dossiers et du calendrier sur ordinateur et téléphone comme vérification courte : colonnes « État » et « Dernier élément du fil », groupes et semaines.

La recette dans Mistral Studio a ensuite révélé un échec de découverte des outils, malgré `initialize` et `tools/list` servis en HTTP 200. Le schéma de `list_mailing_audience_masks` encodait `properties` comme tableau JSON vide au lieu d'un objet. Un test du JSON réellement émis protège désormais les 16 définitions d'outils contre cette erreur. Après déploiement du correctif, relancer le débogueur Mistral et vérifier l'affichage des 16 outils avant de tester les brouillons de mailing.

Le 20 octobre, le formulaire « Nouvelle recommandation » du backoffice a été réparé et déployé sous `deploy-mailing-recommendation-form-20261020-01` (`0aac44c`) : les trois variantes de formulaire ne référencent plus un champ inexistant ; leur rendu passe en test fonctionnel. Le [rendez-vous de dossier avec heure indicative sur les tâches](docs/superpowers/specs/2026-10-20-rendez-vous-dossiers-et-heures-indicatives-design.md) et les actions groupées sur les séances en option sont déployés le 21 octobre sous `deploy-dossier-rdv-calendar-20261021-01` (`e70ddee`). Une tâche peut afficher une heure facultative ; un RDV est créé dans Google Calendar depuis le dossier, puis suivi dans le fil et le rapport du matin. Le statut des séances et le marqueur `[OPT]` se synchronisent dans les deux sens. Le dossier peut confirmer ou rejeter toutes ses séances en option ; les notes automatiques de calendrier ne surchargent plus le fil et les retours à la ligne des notes y sont corrigés. Les événements `RDV`, y compris les RDV liés renommés, sont exclus du rattachement et des compteurs de séances. Le MCP expose la lecture et les commandes de tâches et RDV, avec ses nouvelles écritures désactivées par défaut jusqu'à la validation des confirmations « Demander » dans Mistral. Les 69 migrations sont exécutées en production et en local, aucune en attente ; le schéma Doctrine est synchronisé. Les 529 tests backend (2 978 assertions), CS, PHPStan, Twig et la compilation des assets passent. La recette visuelle authentifiée sur ordinateur et téléphone, ainsi que la recette réelle Google Calendar et Mistral, restent à faire ; elles ne sont pas attestées par les tests ni par le contrôle HTTP du site. Les rappels généraux indépendants des dossiers et un calendrier de mailing restent différés jusqu'aux retours d'usage de ce parcours ; le [plan d'implémentation](docs/superpowers/plans/2026-10-20-rendez-vous-dossiers-et-heures-indicatives.md) garde le détail technique.

Le connecteur mail MCP a été étendu et déployé le 21 octobre sous `deploy-mcp-html-drafts-20261021-01` (`1ff9489`). `create_mail_draft`, `save_mail_draft` et `update_mail_draft` acceptent un `htmlBody` facultatif en plus du `body` texte obligatoire ; le MIME devient `multipart/alternative` lorsque du HTML est fourni. Le HTML est limité à une mise en forme sûre, sans scripts, styles ni images. `read_standalone_mail_draft` et `update_standalone_mail_draft` permettent de relire et remplacer un brouillon libre créé par le MCP à partir du `messageId` retourné, de sa révision et d'une nouvelle confirmation ; destinataire et objet sont conservés. Ces outils ne retrouvent pas un brouillon manuel ou un brouillon MCP dont l'identifiant a été perdu. Aucun outil n'envoie de mail. Les 542 tests backend (3 054 assertions), PHPStan, style et lint du conteneur passent. Le contrôle MCP authentifié en production répond en HTTP 200 avec 26 outils, les champs HTML et les deux nouveaux outils ; les trois verrous serveur de brouillon sont actifs. Après reconnexion, Mistral affiche encore 16 outils : utiliser **Connectors → My Connectors → Functions → Refresh tools** pour forcer l'actualisation de sa liste mise en cache, puis vérifier le nombre et le réglage « Demander » de `Update Standalone Mail Draft`. La recette IMAP réelle de création et d'édition sans envoi reste à faire. La [documentation des brouillons MCP](docs/superpowers/specs/2026-10-19-assistant-mcp-brouillons-imap-design.md) détaille les paramètres et les limites.

Le correctif de sauvegarde après suppression d'un bloc de mailing a été livré le 21 octobre sous `deploy-mailing-block-delete-fix-20261021-02` (`ba595ad`). Le formulaire restaure l'identifiant et le type connus quand les champs cachés ou requis arrivent vides ou nuls ; le cas est couvert par un test de formulaire.

L'ajout d'une photo à un bloc « texte et image » fonctionne désormais en production, confirmé par l'utilisateur après `deploy-mailing-image-upload-20261021-01` (`266b224`). Le fichier fourni mesure 1,58 Mo. La limite PHP CLI de l'hébergement était de 2 Mo, contre 8 Mo en local ; la limite web effective avant correction n'a pas été mesurée. La livraison fixe la limite web à 8 Mo, traduit les erreurs de validation et journalise les codes d'erreur d'upload lorsqu'une image manque.

Le mailing récent contient plusieurs blocs « paragraphe » consécutifs créés par le connecteur. Le correctif `deploy-mailing-intro-blocks-20261021-01` les présente comme un seul bloc par groupe à l'édition, avec leurs retours à la ligne conservés ; le texte des anciens mailings sans blocs reste aussi dans un seul bloc. Un paragraphe vide envoyé après une suppression est ignoré afin qu'il ne bloque plus l'enregistrement. Les changements sont couverts par des tests de formulaire ; l'utilisateur a confirmé le 21 octobre que le parcours fonctionne en production.

La boîte cPanel reste la source des échanges avec les clients ; le backoffice porte les dossiers, les échéances et les documents.

Le 21 octobre, les types MJC / maison pour tous, centre de loisirs / ALSH et lieu de parentalité ont été déployés dans l'annuaire et le ciblage des mailings sous `deploy-organization-types-20261021-01` (`270d851`). Le 22 octobre, les autres types d'annuaire, les fichiers d'import et la suppression du statut client/prospect ont été livrés sous `deploy-directory-import-status-20261022-01` ; le rapprochement tient ensuite compte du type sous `deploy-directory-type-matching-20261022-01`. Le résultat du dernier contrôle de production est dans le point de reprise ci-dessus.

## P2 — Calendrier, interventions, devis et facturation suivie

| Chantier | Bénéfice attendu | Dépendances / critère de départ |
| --- | --- | --- |
| Intégrer les séances de Google Calendar à chaque dossier, avec consultation et modification des dates depuis le dossier. | Retrouver le calendrier du projet au même endroit que ses contacts et son suivi. | Google Calendar reste la référence pour date/heure ; synchroniser dans les deux sens, rattacher explicitement les événements existants et garder la trace des annulations. Le fil devient repliable. |
| Montrer dans le mail ouvré un aperçu succinct des séances de cette semaine et de la suivante ; créer le rappel de préparation de facture après la dernière séance du mois du dossier. | Anticiper les interventions et ne pas oublier la facturation mensuelle. | Une seule action de préparation par dossier et par mois ; tenir compte des séances déplacées, ajoutées ou annulées et signaler une synchronisation incertaine. |
| Enregistrer la référence et l'état de la facture émise par la plateforme déjà choisie. | Retrouver le suivi de facturation depuis le dossier. | Définir les statuts utiles et leur mise à jour depuis la plateforme, manuelle d'abord si nécessaire. |
| Concevoir éventuellement les devis dans le backoffice à partir du XLS et suivre leur envoi. | Centraliser la préparation et le suivi documentaire si le besoin évolue. | Reporté : ChatGPT et son connecteur conviennent actuellement pour préparer les devis. Examiner le classeur et ses calculs avant de cadrer un générateur. |

### Facturation électronique — échéance distincte

Les **devis** peuvent être conçus et suivis dans le backoffice. L'**émission et la transmission des factures électroniques** relèvent de la plateforme agréée choisie ; le backoffice n'a pas vocation à la remplacer. Selon le [calendrier officiel du ministère de l'Économie](https://www.economie.gouv.fr/tout-savoir-sur-la-facturation-electronique-pour-les-entreprises), la réception est obligatoire pour toutes les entreprises depuis le 1er septembre 2026, et l'émission devient obligatoire pour les PME et micro-entreprises le 1er septembre 2027. Vérifier les obligations applicables à l'activité avant de spécifier une intégration.

## P2 — Espace structures

| Chantier | Bénéfice attendu | Dépendances / critère de départ |
| --- | --- | --- |
| Créer un accueil de portail avec accès aux rubriques et annonces datées. | Orienter les structures et leur transmettre une information utile à durée limitée. | Définir destinataires, dates de visibilité, droits d'édition et emplacement de gestion dans le backoffice. |
| Distinguer les responsables pour donner accès aux devis signés et aux documents exceptionnels. | Partager les documents sensibles avec les bonnes personnes. | Définir les rôles et vérifier les autorisations par structure et par document. |
| Améliorer la présentation des ressources du portail. | Faciliter la consultation des séances, comptines et documents. | Observer les usages et préserver les accès existants. |
| Présenter le portail sur une page publique dédiée. | Expliquer son intérêt aux prospects. | Après la présentation courte dans « En séance » ; exemples réels autorisés ou données fictives. |

## P3 — À explorer ou à améliorer progressivement

Ces idées restent visibles sans être décidées pour le prochain lot.

| Piste | Bénéfice attendu | Dépendances / critère de départ |
| --- | --- | --- |
| Page collaborations et projets, avec témoignages et liens entrants de partenaires. | Rassurer les prospects et améliorer la visibilité SEO grâce à des collaborations réelles et des liens pertinents. | La page « Amis et partenaires » est publiée ; demander aux partenaires des liens entrants pertinents et examiner les collaborations complémentaires séparément. |
| Actualités et sélection de newsletters publiables. | Proposer du contenu utile et durable sur la vitrine. | Sélection éditoriale explicite ; ne pas publier automatiquement les campagnes internes. |
| Contenu de la vitrine administrable et messages publics temporaires. | Mettre à jour les informations sans intervention technique. | Cadrer droits d'édition, dates de publication/expiration et séparation des messages publics et réservés aux structures. Prévoir en priorité la mise à jour autonome des photos de la vitrine, puis des liens vers les publications récentes sur les réseaux sociaux. |
| Recherches réactives du backoffice. | Accélérer la navigation dans les catalogues. | Optimiser et mesurer d'abord les requêtes concernées. |
| Historique et tri des recommandations d’e-mail. | Déployé le 26 novembre sous `deploy-mailing-recommendations-20261126-01`. | UUID binaires convertis à la lecture ; dates d’utilisation existantes retrouvées, date de création et tri décroissant affichés. |
| Performance et contrats du portail ; cohérence de l'édition du profil. | Réduire les appels inutiles, fiabiliser les données affichées et clarifier les succès partiels. | Mesurer les chargements, typer les séquences exposées par l'API et couvrir l'échec après enregistrement de l'avatar. |
| Découpage du contrôleur de séance et frontières Doctrine. | Rendre les évolutions plus simples et plus sûres. | Extraire par responsabilités, selon les [frontières documentées](jardin-sonore-backend/docs/architecture-boundaries.md), sans refonte générale. |
| Finitions du compositeur et du PDF A4. | Clos pour le moment à la demande de l’utilisateur le 26 novembre. | Aucune suite prévue ; reprendre uniquement sur un besoin nouveau. |
| Création et édition des séances et familles via le MCP. | Permettre à l'assistant de retrouver les familles et variantes, puis d'aider à préparer et modifier leurs séances. | Écritures derrière un verrou désactivé par défaut, séances en brouillon seulement, sans rattachement d'établissement. Le worker PDF enregistre uniquement le document de la génération en cours. Recette client : vérifier la demande de confirmation avant chaque écriture. |
| Édition structurée des paroles et indications des éléments libres de séance. | Associer explicitement chaque ligne de paroles à ses indications, comme dans le répertoire. | Évolution différée demandée le 18 novembre : conserver pour l’instant les deux champs multiligne. Leur appariement par numéro de ligne est déployé, sans migration ; une future édition en blocs séparés reste à concevoir. |
| Synchronisation éventuelle du PDF canonique vers Google Drive. | Retrouver le document courant dans l'espace de travail partagé. | Clarifier le besoin et les droits d'accès ; conserver le PDF local comme référence disponible. |
| Dédoublonnage de l’annuaire et fluidité du CRUD répertoire. | Repérer les fiches en double avec leurs indices, puis éviter les rapprochements automatiques fragiles à l’import. | Une commande de revue en lecture seule et un seuil d’import plus prudent sont en production ; continuer la revue manuelle avant de décider si un écran interne ou un accès MCP est utile. |
| Aperçus de fichiers dans les outils techniques et import initial de matière pédagogique depuis les séances. | Améliorer les corrections techniques et éviter les ressaisies. | Identifier les usages réels, puis définir le dédoublonnage des instruments, comptines et contenus liés. |
| Essai des formulaires Symfony sur DTO. | Réduire le code de formulaire si le gain est réel. | Attendre Symfony 8.2 stable, puis essayer un formulaire simple avant toute décision plus large. |

## Références de contexte

- [Bilan technique du 25 septembre 2026](docs/bilan-technique-2026-09-25.md) et [plan de suites associé](docs/superpowers/plans/2026-09-25-audit-remediation.md) : constats datés, dont certains points sont désormais livrés.
- [Plan d'action SEO](.codex/plan-action-seo.md) : hypothèses et pistes pour la vitrine et la visibilité locale.
- [Identité visuelle de la vitrine](docs/identite-visuelle-vitrine.md) : palette, typographie, composants et transitions réutilisables.
- [Historique technique backend](.codex/backend-roadmap.md) : décisions et anciens lots conservés pour mémoire.

## Correction identifiée — révisions des médias et instruments MCP

Le 25 novembre, l'audit après le correctif des prolongements identifie le même défaut d'hydratation dans `MediaResource` et `Instrument` : le constructeur appelle une méthode qui remplace la date persistée par l'heure courante. Corriger ces deux types et vérifier lectures stables puis modification avec révision. Les séances, prolongements et éléments du répertoire conservent désormais leur date à l'hydratation.
