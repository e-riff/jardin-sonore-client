# Newsletter et démarchage — cadrage de la réflexion

Date : 26 novembre 2026.

Statut initial : synthèse de discussion pour avis critique. Les décisions approuvées figurent ensuite en sections 18/19. Le premier lot est désormais implémenté et vérifié en local ; voir le [suivi d'exécution](superpowers/plans/2026-11-26-newsletter-outreach-lot-1.md) et la [roadmap](../ROADMAP.md) pour l'état courant.

## 1. Le problème à résoudre

Les campagnes actuelles mélangent deux intentions : partager des réflexions, idées d'activités et recommandations ; proposer des interventions et développer l'activité commerciale de Jardin Sonore.

Cela affaiblit les deux : la newsletter pourrait être plus riche éditorialement, tandis que les propositions commerciales pourraient être plus claires et directes.

L'objectif est de distinguer **newsletter** et **démarchage**, dans le contenu, la sélection des destinataires, les préférences de réception et le suivi des envois.

Nous ne cherchons pas à créer un CRM complexe : pas de suivi automatique des réponses, de scoring, de séquences de relance ou de nouvelles « familles » de publics.

## 2. La newsletter : une publication mensuelle

### Intention et lectorat

La newsletter apporte une valeur propre autour de l'éveil musical et de la petite enfance :

- réflexions et observations de terrain ;
- actualités pertinentes, accompagnées d'un regard personnel ;
- idées d'activités liées notamment aux saisons ;
- recommandations commentées.

Elle doit être accessible aux professionnels comme aux parents et autres personnes qui suivent Jardin Sonore.

Elle peut présenter les services de Jardin Sonore et inviter à prendre contact. Son intérêt ne dépend cependant pas d'un projet d'achat ou d'intervention.

### Modèle éditorial

Un nouveau template propose quatre parties, dans cet ordre :

| Partie | Contenu attendu |
|---|---|
| **Édito** | Une réflexion, une actualité commentée ou une observation de terrain |
| **Idée du mois** | Une proposition concrète à essayer, avec des indications adaptées au sujet |
| **Jardin Sonore** | Des nouvelles ou une proposition, avec un petit appel à l'action, si possible lié au sujet du numéro |
| **Recommandations** | Une sélection courte et commentée de livres, écoutes, ressources, instruments, etc. |

**Les quatre parties sont facultatives et activables séparément.** Une partie désactivée ne doit laisser ni titre ni espace vide dans les versions HTML et texte.

Le template sert de structure de départ. Il ne doit pas imposer de remplir artificiellement toutes les rubriques chaque mois.

Un fil conducteur entre les parties est souhaitable, sans devenir une obligation technique.

### Articles sur le site

Des réflexions et idées d'activités pourront aussi devenir des articles publics autonomes.

Il s'agit d'une publication distincte :

- un sujet peut nourrir un article et une newsletter ;
- une newsletter ne devient pas automatiquement un article ;
- les recommandations et les nouvelles de Jardin Sonore ne doivent pas nécessairement être reprises dans l'article ;
- la mise en ligne doit rester une décision explicite.

Cela pourrait constituer progressivement un fonds éditorial utile aux visiteurs et à la découverte du site. Le fonctionnement précis des articles reste à concevoir ; il n'est pas nécessaire de le développer dans le même lot que la séparation des mailings. Un bénéfice SEO est envisagé, sans être garanti par la seule publication.

## 3. Le démarchage : des propositions ciblées

### Intention

Une campagne de démarchage présente une proposition adaptée à un public et invite à une prochaine étape claire.

« Plus offensif » signifie ici :

- expliquer pourquoi la proposition concerne le destinataire ;
- présenter concrètement l'activité et son intérêt ;
- donner confiance dans l'approche de Jardin Sonore ;
- faciliter la réponse ou la prise de contact.

Le ton reste humain et incarné.

### Template distinct

Une base possible serait :

1. Une accroche adaptée au public.
2. Une présentation de l'activité ou de la proposition.
3. Quelques éléments sur Jardin Sonore et son approche.
4. Un appel à l'action clair.
5. Éventuellement une petite recommandation complémentaire.

**Cette structure reste à affiner.** Nous n'avons pas encore choisi entre un rendu proche d'un message personnel et une présentation plus visuelle. Le modèle doit pouvoir servir à des campagnes collectives régulières, sans imposer une personnalisation individuelle.

### Ciblage

Chaque démarchage conserve son audience propre, choisie selon la proposition : géographie, types de structures, autres critères déjà disponibles et éventuelles sélections explicites.

Les masques existants restent la base. Pas de nouvelle nomenclature de « familles ».

Une absence de réponse ne déclenche aucune action automatique. Le calendrier des campagnes sera travaillé séparément, après le fonctionnement général.

## 4. Deux types de campagnes, distincts des templates

Le logiciel doit distinguer :

- **le type de campagne** : newsletter ou démarchage ;
- **le template** : la structure de contenu proposée à la rédaction.

Le type commande les règles de sélection, de préférences, d'exclusion et de suivi. Le template commande la présentation et la composition initiale.

Pour commencer, il y aurait un template principal pour chaque type. Conserver cette distinction permettra ensuite de changer la présentation sans changer les règles métier.

## 5. L'audience de la newsletter

### Une diffusion régionale, complétée par les inscriptions

La newsletter ne serait pas réservée aux seuls abonnés spontanés, actuellement trop peu nombreux.

Son audience réunirait :

1. **Le cœur de cible sélectionné dans l'annuaire**, à partir d'un masque réservé à la newsletter.
2. **Les abonnements explicites confirmés**, sans restriction géographique.

Le cœur de cible comprend principalement les crèches et les RPE/RAM. D'autres profils proches, comme des librairies spécialisées en petite enfance, pourraient être ajoutés.

Le périmètre envisagé est :

- Loire : 42 ;
- Rhône : 69 ;
- certains secteurs de Haute-Loire, Ardèche et Isère : 43/07/38 ;
- éventuellement le nord de la Drôme : 26.

**Les limites précises restent à choisir.** L'idée retenue est une diffusion éditoriale plus large que le territoire des interventions régulières, sans constituer immédiatement une audience nationale de prospection.

Les territoires d'intervention évoqués sont notamment la vallée du Gier, le Forez, le Pilat jusqu'à Chavanay et une partie des monts du Lyonnais. Des déplacements plus éloignés existent, notamment vers Villefranche ; le nord de Roanne est moins facile pour les interventions régulières.

Un assemblage de zones peut mieux traduire ces territoires qu'un simple rayon. Les outils géographiques existants doivent rester la base de sélection.

### Un masque réservé et protégé

Nous souhaitons un masque :

- clairement identifié comme celui de la newsletter ;
- protégé contre la suppression ;
- modifiable dans ses critères géographiques et ses profils ;
- appliqué par défaut aux nouvelles newsletters.

Les abonnements explicites s'ajoutent indépendamment de ce masque. Ils ne doivent pas être exclus parce qu'ils sont hors zone ou sans fiche dans l'annuaire.

Le récapitulatif avant envoi doit permettre de comprendre l'audience obtenue.

### Une nuance importante

Le masque est une **règle de sélection**, pas une liste d'abonnements.

Une crèche sélectionnée géographiquement ne doit pas être présentée dans le logiciel comme ayant demandé à recevoir la newsletter si ce n'est pas le cas.

Nous devons conserver cette distinction, même si les deux sources aboutissent à une seule audience d'envoi.

Formule fonctionnelle, sous réserve des conditions d'utilisation des adresses :

> Audience newsletter = adresses éligibles du masque réservé + inscriptions confirmées − exclusions applicables, sans doublons.

## 6. Une base commune d'adresses

L'orientation proposée est de conserver **une base d'adresses commune**, plutôt que deux fichiers indépendants à maintenir.

Il faut distinguer quatre notions :

| Notion | Rôle |
|---|---|
| **Adresse électronique** | Le point de réception, utilisé pour dédupliquer les envois |
| **Rattachements** | Les personnes, établissements ou comptes associés à cette adresse |
| **Abonnements et préférences** | Les demandes explicites et les refus de réception |
| **Audience d'une campagne** | Le résultat de la sélection pour un envoi donné |

Une adresse peut être générique, nominative professionnelle, personnelle utilisée dans un cadre professionnel, sans rattachement à l'annuaire ou partagée entre plusieurs fiches.

Elle doit recevoir **au maximum un exemplaire d'une campagne**, même si elle est retenue par plusieurs sources.

## 7. Abonnement explicite, éligibilité et refus : trois notions différentes

Le point central est de ne plus faire porter toutes les significations par un seul indicateur « opt-in newsletter ».

### Abonnement explicite à la newsletter

Une inscription confirmée signifie :

> Cette adresse demande à recevoir la newsletter, indépendamment des masques de ciblage.

Ainsi :

- une crèche éloignée peut s'inscrire ;
- il n'est pas nécessaire de détecter sa localisation ;
- une adresse déjà présente dans l'annuaire peut aussi s'inscrire ;
- cela ne crée pas de doublon ;
- l'adresse continue à recevoir la newsletter si elle sort du masque géographique ;
- cette inscription ne vaut pas accord pour recevoir des démarchages.

Une réinscription confirmée à la newsletter peut réactiver cette publication, mais **ne doit pas lever un refus des démarchages**.

### Adresses sélectionnables dans l'annuaire

Pour une adresse professionnelle, le fait de pouvoir être sélectionnée dans une campagne n'est pas une preuve d'abonnement volontaire.

Le logiciel doit pouvoir conserver séparément la provenance de l'adresse, son contexte professionnel et ses rattachements, une éventuelle inscription explicite et les oppositions exprimées.

**L'absence de refus ne constitue pas, à elle seule, une justification suffisante pour utiliser n'importe quelle adresse.** Les conditions de collecte et d'utilisation restent à examiner.

### Préférences de refus

L'orientation proposée est de distinguer :

- refus de la newsletter ;
- refus des démarchages.

Un refus doit primer sur le masque, les ajouts explicites à une audience, les rattachements à plusieurs établissements et les autres chemins de sélection.

Une adresse bloquée pour une raison technique doit être distinguée d'un refus de réception.

## 8. Désabonnement et gestion des préférences

Le fonctionnement proposé est le suivant :

| Action | Conséquence |
|---|---|
| **Arrêter la newsletter** | Exclut cette adresse des newsletters, y compris si elle appartient au masque réservé |
| **Arrêter les propositions commerciales** | Exclut cette adresse des démarchages |
| **Tout arrêter** | Exclut cette adresse des deux types de campagnes |
| **Se réinscrire à la newsletter** | Réactive uniquement la newsletter après confirmation, sous réserve d'un blocage technique |

Les mails pourraient proposer une action simple et clairement nommée pour arrêter les envois, et un accès à une page permettant de gérer séparément les deux préférences.

**Le libellé exact et l'effet du lien principal restent à valider.** Il ne faut pas qu'un lien intitulé simplement « Se désabonner de la newsletter » ait un effet différent de ce qu'il annonce.

L'arrêt des mailings ne doit pas être confondu avec l'arrêt des messages nécessaires au fonctionnement du portail ou de la relation de service. Cette séparation devra être vérifiée dans l'audit.

## 9. Cadre juridique à prendre en compte

La prospection électronique à destination de professionnels peut, sous conditions, reposer sur l'intérêt légitime, notamment lorsque la sollicitation concerne leur activité professionnelle. Les personnes doivent être informées et pouvoir s'opposer simplement et gratuitement. **L'envoi aux professionnels n'est donc pas une autorisation générale sans conditions.**

Source consultée pendant la discussion : [CNIL — Prospection par courrier électronique, SMS-MMS et automate d'appel](https://www.cnil.fr/fr/la-prospection-commerciale-par-courrier-electronique-sms-mms-et-automate-dappel).

Deux conséquences pour notre réflexion :

- Une adresse en Gmail ou Orange n'est pas automatiquement privée ou professionnelle : le contexte de collecte et d'utilisation compte.
- Une newsletter présentant les services de Jardin Sonore et contenant un appel à l'action ne doit pas être considérée automatiquement comme extérieure aux règles de prospection du seul fait de son caractère éditorial.

Le logiciel doit représenter fidèlement ce que nous savons des adresses et des choix exprimés. **Il ne doit pas transformer toutes les adresses existantes en abonnements volontaires.**

La qualification des différentes sources du fichier mérite une vérification spécifique avant d'élargir la diffusion. Ce cadrage ne tranche pas à lui seul la licéité de chaque adresse ou de chaque source.

## 10. Exclusion glissante entre démarchages

Le principe retenu est :

> Un démarchage peut exclure les établissements déjà démarchés au cours d'une période récente.

Cette règle :

- appartient à la campagne, pas au masque réutilisable ;
- ne compte que les démarchages précédents ;
- n'est pas déclenchée par une newsletter ;
- ne bloque pas l'envoi mensuel de la newsletter ;
- s'applique aussi aux établissements ajoutés explicitement ;
- doit être visible dans le récapitulatif avant diffusion.

**Six semaines est une valeur de départ évoquée, pas un délai définitivement validé.** Le réglage doit pouvoir être adapté, voire désactivé explicitement si nécessaire.

L'exclusion doit fonctionner au niveau de l'établissement, pour éviter de contourner la règle en écrivant à un autre interlocuteur de la même structure.

### Points techniques encore ouverts

Il faut définir précisément :

- quel état compte comme « démarché » : en attente, envoyé, échec, etc. ;
- quelle date sert de référence ;
- comment deux campagnes préparées simultanément interagissent ;
- comment traiter une adresse liée à plusieurs établissements, dont certains sont exclus et d'autres éligibles ;
- quelles vérifications sont refaites au moment de l'envoi effectif.

Il faudra éviter de présenter un envoi comme « reçu » : un statut d'envoi ne garantit pas la lecture ni même la réception finale en boîte de réception.

## 11. Journal des démarchages

### Par établissement

Voir les campagnes de démarchage associées, les dates, les adresses concernées et les états d'envoi.

### Par type de structure

Voir les campagnes ayant touché, par exemple, des centres sociaux ou des crèches, avec la date, le périmètre géographique, le nombre d'établissements concernés et l'accès au détail.

Il faut éviter une synthèse trompeuse comme « Centres sociaux démarchés le 12 novembre » si seuls quelques centres d'un secteur ont été concernés.

La vue doit rendre compte de la **couverture réelle de chaque campagne**, pas seulement de la dernière date trouvée pour un type.

Aucun suivi des réponses n'est demandé. Nous ne prévoyons donc pas de statuts « a répondu » ou « sollicité sans réponse ».

## 12. Ce que le code possède déjà

La première lecture du code a identifié les éléments suivants :

| Élément existant | Observation |
|---|---|
| `EmailContactEntity` | Centralise les adresses et porte actuellement des informations d'autorisation, de désabonnement et d'abonnement libre |
| Liens de coordonnées | Permettent de rattacher une adresse à des fiches de l'annuaire |
| `FreeNewsletterSubscriptionManager` | Gère notamment la demande d'inscription, sa confirmation et l'ajout depuis le backoffice |
| `PortalNewsletterSubscriptionManager` | Gère une préférence de réception depuis le portail |
| `DoctrineNewsletterAudienceResolver` | Agrège plusieurs sources de destinataires et déduplique par adresse |
| `DoctrineNewsletterRecipientEligibility` | Vérifie actuellement des conditions communes fondées notamment sur `opt_in_newsletter`, l'activité de l'adresse et son désabonnement |
| `SendMailingCampaign` | Résout l'audience puis initialise la file des destinataires |
| Modèle de campagne | Possède déjà un template, du contenu, un filtre d'audience et une référence au masque appliqué |

Le moteur actuel peut réunir :

- des adresses de l'annuaire correspondant aux filtres ;
- des établissements ajoutés explicitement ;
- des utilisateurs du portail associés aux structures ;
- des abonnements libres confirmés, lorsque cette option est activée.

**Ces différentes sources utilisent actuellement des conditions de réception communes.** Ajouter simplement une étiquette et un template ne suffira donc pas à séparer proprement les comportements.

Quelques précisions observées :

- `EmailContactEntity` initialise actuellement `optInNewsletter` à `true`, mais certains parcours de création le surchargent à `false`.
- L'inscription publique possède un mécanisme de confirmation ; le parcours du portail modifie la préférence sur l'adresse partagée.
- Les requêtes actuelles de sélection exigent notamment une adresse active, `opt_in_newsletter = 1` et l'absence de désabonnement.
- La déduplication du résolveur s'appuie sur l'adresse normalisée.

Cette lecture constitue un premier état des lieux, pas encore un audit complet de tous les chemins d'écriture et d'envoi.

## 13. Limite actuelle de l'historique

La roadmap précise que :

- les adresses destinataires et leurs états sont conservés historiquement ;
- les établissements affichés dans les bilans sont retrouvés à partir des rattachements actuels ;
- les rattachements au moment de l'envoi ne sont pas conservés.

Il serait donc inexact de dire que le journal historique par établissement est déjà disponible tel quel.

Pour les futurs envois, il faudra conserver les associations nécessaires au moment approprié. Si nous voulons des regroupements historiquement fidèles par type ou territoire, il faudra également décider quelles caractéristiques figer.

Pour les campagnes anciennes, toute reconstruction à partir de l'annuaire actuel devra être présentée comme telle, sans inventer une précision historique absente des données.

## 14. Conséquences techniques probables

Sans figer immédiatement le schéma, les besoins identifiés sont :

1. Un type explicite de campagne.
2. Deux templates de départ.
3. Quatre rubriques facultatives pour la newsletter.
4. Un masque réservé, protégé et modifiable pour son cœur de cible.
5. Un abonnement explicite indépendant des critères géographiques.
6. Des oppositions distinctes pour newsletter et démarchage.
7. Une gestion séparée des blocages techniques.
8. Une résolution d'audience adaptée au type de campagne.
9. Un récapitulatif expliquant les sources, doublons et exclusions.
10. Un historique fiable des établissements associés aux futurs démarchages.
11. Une exclusion glissante fondée sur cet historique.
12. Des vues de consultation par établissement et par type.

**Nous n'avons pas encore décidé si ces informations doivent être portées par des colonnes supplémentaires ou par des objets/tables dédiés.** Ce choix doit suivre l'audit, notamment selon les besoins de traçabilité et l'existant.

## 15. Transition depuis les données actuelles

C'est un sujet à part entière.

Les principes proposés sont :

- préserver tous les désabonnements existants ;
- préserver les blocages techniques ;
- conserver les inscriptions explicites déjà enregistrées et leur provenance ;
- ne pas transformer les valeurs actuelles de `opt_in_newsletter` en preuves de consentement ;
- traiter prudemment les anciens désabonnements dont la portée exacte est inconnue : proposition de les appliquer aux deux types ;
- ne pas classifier automatiquement tous les anciens envois comme newsletters ou démarchages sans examen ;
- ne pas faire compter aveuglément les anciennes campagnes mixtes dans l'exclusion glissante.

Une politique de reprise des données devra être validée avant toute migration.

## 16. Audit à mener avant de choisir le schéma

L'audit doit suivre une adresse de bout en bout :

1. **Création et import** : sources, valeurs par défaut, normalisation.
2. **Rattachements** : personnes, établissements, portail, adresses partagées.
3. **Inscriptions** : formulaire public, confirmation, backoffice, portail, réinscription.
4. **Préférences** : modification, désabonnement, éventuelle réactivation indirecte.
5. **Sélection** : masques, ajouts explicites, abonnements hors zone.
6. **Envoi** : constitution de la file, vérifications avant départ, reprises et échecs.
7. **Historique** : adresses et établissements conservés, informations recalculées.
8. **Interfaces** : backoffice, portail, formulaire public et outils MCP concernés.
9. **Tests existants** : comportements déjà garantis et scénarios à compléter.

## 17. Questions utiles à soumettre à l'autre IA

- La coexistence entre **cœur de cible sélectionné** et **abonnements volontaires** est-elle suffisamment claire pour les destinataires et pour l'administration ?
- Le périmètre régional envisagé sert-il bien la stratégie de Jardin Sonore ?
- Deux préférences de refus et un abonnement explicite suffisent-ils à représenter les situations réelles ?
- Quel comportement de désabonnement principal serait le plus compréhensible ?
- Comment traiter les adresses personnelles utilisées professionnellement et les sources historiques ?
- Quels anciens envois peuvent raisonnablement compter dans l'exclusion glissante ?
- Quelles données faut-il conserver pour un historique fiable sans alourdir inutilement le modèle ?

## Direction retenue et prochaine étape

Une base commune d'adresses, deux intentions de campagne, une newsletter diffusée au cœur de cible régional et aux abonnés de partout, et des démarchages ciblés au cas par cas.

Le chantier suivant est l'audit complet de l'existant, avant la spécification du schéma et l'implémentation. Les choix ouverts dans ce document ne doivent pas être traités comme des décisions approuvées.

## 18. Complément après audit ciblé — 26 novembre 2026

### Données observées en production

Lecture seule via la connexion PhpStorm `[PROD]`. Aucun enregistrement ni schéma modifié. Les valeurs suivantes décrivent l'état observé, pas des garanties permanentes.

| Mesure | Valeur |
|---|---:|
| Adresses enregistrées | 2 638 |
| Adresses actives, indicateur newsletter actif, sans désabonnement | 2 561 |
| Adresses comportant une date de désabonnement | 12 |
| Abonnements libres confirmés enregistrés par le mécanisme dédié | 0 |
| Demandes d'inscription enregistrées | 1, non consommée |
| Lignes historiques de destinataires | 972 |
| Campagnes présentes dans cet historique | 6 |
| Adresses distinctes dans cet historique | 406 |
| Adresses associées à plusieurs établissements actifs | 210 |
| Maximum d'établissements actifs associés à une adresse | 22 |

Une demande non consommée n'est pas une demande à approuver manuellement : elle peut être en attente de confirmation ou expirée. Son statut détaillé n'a pas été investigué à la demande de l'utilisateur.

Le masque enregistré « Forez et Gier » porte sur 139 communes et les types `creche`, `mairie`, `rpe`, `mam`, `centre`, `garderie`. Il ne correspond donc pas automatiquement au futur cœur éditorial à retenir.

Le nombre nul d'abonnements libres ne permet pas de conclure à l'absence de demandes exprimées autrement, ni à l'absence de préférences activées dans le portail.

### Parcours examinés et implications

- Le résolveur rassemble annuaire, utilisateurs du portail et abonnés libres, puis déduplique par adresse. Aujourd'hui, il perd les associations détaillées avec les établissements dans son objet destinataire : ces associations doivent être transportées avant la déduplication, puis réunies pour chaque adresse.
- Une sélection explicite d'établissement complète les filtres ordinaires ; sa provenance doit également être conservée.
- `SendMailingCampaign` initialise la file, puis marque la campagne en attente. L'ajout des associations historiques doit être atomique avec les lignes destinataires et la mise à jour de la campagne : pas d'historique partiel en cas d'erreur.
- `ExtendMailingCampaignAudience` permet d'ajouter une audience après un premier envoi. Les nouvelles lignes doivent bénéficier du même historique. Une adresse déjà présente n'est pas renvoyée ; ne pas lui attribuer rétroactivement de nouveaux établissements comme s'ils avaient été visés lors de son ancien envoi.
- Le worker vérifie l'éligibilité de l'adresse avant chaque envoi et annule le destinataire devenu inéligible. Ce contrôle doit être préservé.
- La commande de distribution traite des vagues limitées puis délègue à Messenger. La méthode de sélection/marquage des lignes ne suffit pas à démontrer une protection contre tous les traitements concurrents. Le lot d'exclusion devra examiner la coordination réelle des workers et des campagnes.
- Le portail active aujourd'hui l'indicateur partagé sans créer d'abonnement libre. Un test existant exige explicitement cette absence d'abonnement libre. Le futur abonnement indépendant de la géographie constitue donc une évolution réelle de comportement, à traiter dans le lot préférences.
- L'ajout manuel depuis le backoffice possède également un cas particulier pour une adresse déjà rattachée à l'annuaire : il peut activer l'indicateur sans créer d'abonnement libre. Cette différence doit être revue dans le même lot.
- Le rendu ne connaît actuellement qu'une clé de template HTML `default` ; le template texte est également unique. Il faut traiter HTML et texte ensemble, et pas seulement ajouter un choix dans le formulaire.
- Les contenus disposent déjà de blocs et d'un catalogue de recommandations. Les nouvelles rubriques doivent réutiliser ces capacités au lieu de créer un second moteur de composition.

### Phasage retenu dans la discussion

Trois approches ont été examinées :

| Approche | Arbitrage |
|---|---|
| Tout livrer ensemble | Comportement final immédiatement cohérent, mais délai et risque de transition plus élevés |
| Types, templates, historique, exclusion et journal dans un premier lot | Donne rapidement le suivi commercial, mais concentre déjà les difficultés de sélection et de concurrence |
| Première livraison éditoriale avec collecte historique, puis exclusion/journal, puis préférences | Approche retenue comme direction par l'utilisateur ; prépare des données fiables et limite les changements simultanés |

L'audit ciblé précède le choix définitif du schéma. L'architecture complète des préférences n'est pas un prérequis à la première livraison si l'on conserve temporairement l'opposition globale actuelle et le fonctionnement actuel des audiences.

## 19. Proposition technique du premier lot — à valider avant implémentation

Cette section constitue une proposition de conception ciblée, distincte du cadrage général. Les choix suivants sont proposés par Codex ; leurs détails n'ont pas encore été approuvés individuellement.

### Périmètre

- Deux types de nouvelles campagnes : `newsletter` et `outreach`.
- Deux modèles de départ, avec quatre rubriques facultatives pour la newsletter et une structure de proposition pour le démarchage.
- Capture des établissements réellement sélectionnés pour chaque nouveau destinataire mis en file, y compris lors d'un élargissement d'audience.
- Conservation du fonctionnement actuel du ciblage et du désabonnement global pendant ce premier lot.
- Compatibilité de création, lecture et édition depuis le backoffice et les outils MCP. Une création doit porter un type explicite ; les anciens clients doivent recevoir une validation compréhensible si leur entrée n'est plus suffisante, sans classification implicite hasardeuse.

Hors périmètre : exclusion glissante, journal et regroupements historiques complets, refus distincts, masque réservé, modification des inscriptions portail/backoffice, articles publics, extension géographique et calendrier des campagnes.

### Type et compatibilité des anciennes campagnes

Proposition : un champ nullable `campaign_type` sur `mailing_campaign`. Une valeur absente désigne uniquement une campagne historique non classée ; les nouvelles campagnes doivent être typées.

Les anciens contenus continuent à utiliser leur rendu `default`. Aucun ancien envoi n'est requalifié automatiquement. Le type d'une campagne et la structure de son contenu sont verrouillés dès le démarrage de sa diffusion, afin de ne pas changer le sens de son historique.

Le passage entre deux modèles sur un brouillon ne doit jamais effacer silencieusement du contenu. Les règles exactes de conversion sont à préciser avant de proposer cette action dans l'interface ; elle n'est pas nécessaire au premier lot.

### Rubriques

Proposition : réutiliser les blocs existants dans un contenu structuré versionné, stocké dans un champ JSON nullable dédié aux nouvelles rubriques. Chaque rubrique possède une clé stable, un état d'activation et son contenu ; les recommandations réutilisent les associations au catalogue existant.

L'absence de ce contenu structuré maintient le comportement historique. Les champs persistés restent limités aux besoins éditoriaux, sans stocker deux versions concurrentes éditables du même texte. Si un texte de compatibilité est nécessaire pour les API, il est dérivé.

Une rubrique désactivée conserve son contenu en brouillon pour permettre sa réactivation, mais est absente du HTML, du texte et du pré-en-tête calculé. Refuser un envoi sans contenu visible utile. L'aperçu, l'envoi de test et l'envoi réel utilisent le même rendu.

### Associations historiques

Proposition : une table `mailing_delivery_recipient_organization` rattachée à la ligne `mailing_delivery_recipient`, avec :

- identifiant du destinataire historique ;
- UUID stable de l'établissement ;
- nom et type de l'établissement au moment de la sélection ;
- code INSEE de sa commune de référence, nullable si absent ou ambigu.

Une contrainte unique porte sur le couple destinataire/UUID établissement ; un index sur l'UUID établissement prépare les recherches futures. Ne pas imposer de clé étrangère vers la fiche vivante de l'annuaire qui ferait disparaître l'histoire lors de son retrait. Le cycle de suppression d'une campagne et de ses destinataires doit nettoyer les associations correspondantes conformément au comportement existant.

La commune de référence doit reprendre une règle déterministe cohérente avec les adresses existantes ; une pluralité d'adresses ne doit pas produire arbitrairement une commune fausse. L'exclusion future dépend de l'UUID, pas du nom, du type ou de la commune.

Ces informations sont figées à la constitution de la file. L'état et la date d'envoi réel restent portés par la ligne destinataire existante : une ligne annulée ou en échec n'est pas assimilée à un envoi réussi. Il faut donc distinguer explicitement « sélectionné » et « envoyé » dans les usages futurs.

Les associations réunissent les établissements effectivement retenus par les différents chemins du ciblage pour cette adresse. Ne pas collecter tous les établissements liés à l'adresse après coup. Un abonné libre sélectionné seulement par son abonnement peut n'avoir aucune association d'établissement. Les destinataires issus de personnes autonomes ne doivent pas être perdus faute d'établissement.

### Migration envisagée

Une migration additive est envisageable pour le champ de type, le contenu structuré et la table d'associations avec ses index. Aucun changement des champs d'abonnement et aucun remplissage massif de l'historique ne sont nécessaires dans ce lot.

Le nombre final de migrations dépendra du schéma approuvé et des conventions du projet. Générer avec les outils Doctrine, relire le SQL et le `down()`, vérifier l'absence de modifications parasites. L'exécution d'une migration fera l'objet d'une confirmation distincte selon les consignes du projet.

### Vérifications attendues lors de l'implémentation

- Adresse sélectionnée par plusieurs sources : un destinataire, union des établissements réellement ciblés.
- Adresse partagée entre trois établissements dont un seul ciblé : une seule association historique.
- Utilisateur du portail ciblé par une structure : association conservée même sans adresse générique sur la structure.
- Abonné libre hors zone : présent sans rattachement artificiel.
- Désabonnement entre préparation et envoi : destinataire annulé et aucun faux succès historique.
- Élargissement : historique pour les nouvelles adresses uniquement, sans renvoi ni réécriture de l'histoire des anciennes.
- Échec de création de file : aucune persistance partielle.
- Modification ultérieure de l'annuaire : associations historiques inchangées.
- Ancienne campagne : contenu et rendu préservés.
- Rubriques facultatives : masquage cohérent dans le HTML, le texte et le pré-en-tête.
- Backoffice et MCP : mêmes validations, type et données relus sans perte.

Les suites d'intégration existantes sur l'audience, les inscriptions et le portail constituent la base de non-régression. Les scénarios ci-dessus devront être ajoutés au moment de l'implémentation, puis complétés par les contrôles habituels de style, analyse statique, Twig et compilation des assets si nécessaire.

### Précisions après relecture de conception

- Un ancien brouillon jamais diffusé doit être classé avant son premier envoi ; son contenu et son rendu `default` sont conservés pour éviter une conversion forcée. Une campagne déjà diffusée reste historiquement non classée, y compris si elle est élargie. Toute nouvelle ligne de destinataire capture néanmoins ses associations disponibles.
- Le démarchage n'inclut pas automatiquement les abonnés libres du seul fait de leur inscription à la newsletter. Le sélecteur d'abonnés libres n'est pas proposé comme source de démarchage ; la même règle s'applique aux entrées MCP.
- Validation minimale proposée : au moins un bloc textuel non vide dans une rubrique active, ou une recommandation active effectivement rendue. Un titre et une bannière seuls ne suffisent pas. Les recommandations masquées par la désactivation de leur rubrique ne sont pas enregistrées comme utilisées.
- Le CTA newsletter appartient à la rubrique Jardin Sonore et disparaît avec elle ; son libellé et sa destination restent facultatifs mais doivent être renseignés ensemble.
- Pour le démarchage, proposition de champs : accroche et proposition dans le corps principal, présentation de l'approche facultative, CTA facultatif, recommandations facultatives. Le corps principal est obligatoire ; l'absence de bouton n'empêche pas une invitation textuelle à répondre. Cette structure fonctionnelle reste à valider avec l'utilisateur avant construction du formulaire.
- La forme exacte du JSON versionné et les classes de transport sont laissées à l'implémentation, en suivant les conventions du projet. Une commune ambiguë est conservée à `null` dans ce premier lot.

Validation de l'existant exécutée pendant cet audit : `docker compose exec -T php php -d memory_limit=1G ./bin/phpunit tests/Integration/Infrastructure/Mailing tests/Integration/Infrastructure/Portal/PortalNewsletterSubscriptionManagerTest.php tests/Integration/Application/Mailing/NewsletterAudienceCampaignFlowTest.php` — **50 tests, 236 assertions, sans échec**. Cette vérification valide les parcours actuels couverts, pas les fonctionnalités futures décrites ici.
