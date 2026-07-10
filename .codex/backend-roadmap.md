# Backend Roadmap - Jardin Sonore

Ce fichier est la roadmap maitre du backend. Il doit rester centre sur l'etat present, les decisions vivantes et les prochains lots. Le pilotage d'execution detaille vit dans `.codex/backend-refacto-plan.md`.

## Etat Actuel

- Backend Symfony operationnel dans `jardin-sonore-backend/`.
- Architecture `Domain / Application / Infrastructure` deja en place, mais encore inegalement appliquee.
- Backoffice metier interne disponible a la racine backend.
- EasyAdmin est conserve comme backoffice technique sous `/backoffice`.
- Annuaire, geographie, import ODS et mailing interne sont fonctionnels.
- La documentation de fonctionnement durable vit surtout dans :
  - `jardin-sonore-backend/README.md`
  - `jardin-sonore-backend/docs/directory-import.md`
  - `jardin-sonore-backend/docs/mailing.md`

## Decisions Validees

- Le backend reste separe du front Next.js present dans `jardin-sonore-client/`.
- `Domain` reste pur : aucun couplage Doctrine, Symfony, DBAL, Twig ou EasyAdmin.
- `Application` orchestre les cas d'usage, les contrats et les DTO utiles.
- `Infrastructure` porte Doctrine, DBAL, Mailer, Twig, stockage, import et admin technique.
- EasyAdmin sert au depannage, a la consultation et aux corrections techniques, pas a l'UX metier principale.
- Les abstractions doivent rester pragmatiques :
  - repository pour charger/sauvegarder des objets metier ;
  - query service ou provider pour des lectures ciblees seulement si le role est clair ;
  - mapper explicite accepte ;
  - aucun pattern decoratif.
- Pour les requetes de donnees :
  - `QueryBuilder` lisible d'abord si pertinent ;
  - `expr()` / `andX()` / `orX()` quand la combinatoire l'impose ;
  - SQL inline seulement si c'est le meilleur choix concret.
- Les attributs Symfony sont souhaitables lorsqu'ils rendent le branchement plus explicite, jamais dans `Domain`.
- Cote HTTP metier, les attributs de mapping Symfony vers DTO applicatifs sont preferes aux injections diffuses ou aux entites Doctrine directes.
- Cote listeners applicatifs et d'infrastructure, les attributs Symfony/Doctrine sont preferes quand ils remplacent clairement une declaration dans `services.php`; les listeners tiers sans autoconfiguration restent declares explicitement.
- La configuration Symfony en PHP suit le format declaratif `App::config([...])`.
- Les repositories Doctrine suivent par defaut le pattern `ServiceEntityRepository` + `ManagerRegistry`; les adapters de domaine gardent leurs mappers explicites et les repositories d'entites ORM restent acceptables pour les besoins purement techniques.
- Les parametres applicatifs suivent la convention :
  - `config/parameters.yaml.dist` versionne ;
  - `config/parameters.yaml` local et non versionne ;
  - resolution depuis `.env` / `.env.local` / environnement serveur ;
  - injection ciblee via `#[Autowire('%...%')]` quand cela rend l'origine de la valeur explicite.

## Priorites Produit

1. Stabiliser et clarifier le backend existant.
2. Refaire le systeme de ciblage d'audience mailing et introduire les masques reutilisables.
3. Concevoir puis lancer le module `resumes de seances`.
4. Traiter la `facturation` apres les resumes de seances.
5. Garder `espace client`, donnees publiques front et extensions comme lots ulterieurs.

## Prochains Lots

### Lot 5. Refacto Annuaire Et Geographie

- Statut : Quasi termine
- Extraction en cours des lectures bas niveau hors `Application`, en commencant par l'import annuaire.
- Le lot a aussi servi a normaliser les acces ORM simples via repositories Doctrine injectables plutot que `EntityManager->getRepository(...)`.
- `SyncMunicipalitiesFromGeoGouvCommand` delegue maintenant sa lecture/ecriture technique a des services d'infrastructure, et `FindInstrumentCatalogItems` n'injecte plus l'`EntityManager` directement.
- Les readers geographiques purement techniques injectent maintenant `Connection`, et les derniers lookups simples passent par des repositories dedies.
- Reste seulement un petit reliquat legitime : writers ORM, CRUD admin et subscribers techniques.

### Lot 6. Nommage Et Patterns Structurels

- Statut : Terminee sur le perimetre utile
- Les noms ont ete stabilises la ou le gain etait net : `Query`, `Lookup`, `Queue`, `Resolver`, `Storage`.
- Les derniers nettoyages utiles ont ete faits sur :
  - hash admin derriere un contrat applicatif ;
  - `SharedContactLinkResolver` et les CRUD admin de contacts ;
  - etat du composant `MailingAudience`.
- Decision : ne plus poursuivre de renommages cosmetiques. Le gain devient trop marginal.

### Lot 7. Conventions Symfony Et Configuration

- Statut : Partiellement termine
- Config packages migrees au format `App::config([...])`.
- Parametres scalarises via `parameters.yaml.dist` + `parameters.yaml` local et `#[Autowire('%...%')]`.
- A poursuivre seulement la ou un attribut ou une convention retire une configuration diffuse reelle.

### Lot 8. Refonte Du Systeme De Ciblage D'Audience

- Statut : Demarre, V1 socle des masques en cours puis finition UX mailing
- Priorite : Avant `resumes de seances`
- Objectif : remplacer le ciblage geographique trop limite par un systeme de `masques d'audience` reutilisables, visuels et plus precis.
- Intentions produit :
  - permettre de selectionner plusieurs zones geographiques pertinentes ;
  - visualiser clairement les communes reellement retenues ;
  - memoriser des `masques d'audience` reutilisables sur plusieurs campagnes ;
  - appliquer un masque a une campagne sous forme de copie figee.
- V1 retenue :
  - bibliotheque globale de `masques d'audience` ;
  - geographie composee de `polygones`, `multi-cercles` et selection manuelle de `communes` ;
  - stockage de la `definition geographique source` et de la `liste materialisee des communes retenues` ;
  - affichage sur carte des formes source et des `polygones de communes` deja stockes en base ;
  - dedoublonnage des communes par `insee_code` et dedoublonnage final des destinataires par email.
- Decisions produit :
  - un masque est `global et reutilisable` ;
  - une campagne prend un `snapshot fige` du masque applique ;
  - le polygone libre est accepte des la v1 ;
  - la resolution finale d'envoi repose sur les `communes retenues`, pas sur un recalcul geospatial a chaque envoi ;
  - les regions/departements/communes manuels peuvent rester, mais doivent converger vers une liste dedoublonnee de communes retenues.
- Avancement V1 deja pose :
  - bibliotheque backend initiale de `masques d'audience` creee ;
  - application d'un masque a une campagne sous forme de copie figee branchee ;
  - materialisation initiale des communes retenues branchee a partir des criteres geographiques actuels ;
  - stockage des metadonnees du masque applique sur la campagne ;
  - le dessin libre, les polygones et les multi-cercles restent a brancher sur ce socle.
- Finition UX validee avant passage au prochain module :
  - refacto visuel du module mailing en reprenant le design `.codex/design audience/` comme direction ;
  - extraction d'un petit socle de blocs reutilisables pour le back metier ;
  - tableaux et listes rendus compacts et responsives, y compris sur mobile pour le backoffice metier ;
  - refonte de l'index des recommandations vers une presentation plus dense et plus scannable.
- Criteres de fin :
  - un utilisateur peut creer, nommer, previsualiser et reutiliser un masque d'audience ;
  - un masque peut combiner plusieurs formes geographiques ;
  - la carte montre les communes retenues via leurs polygones ;
  - une campagne peut appliquer un masque sans dependre ensuite de ses evolutions futures ;
  - le calcul d'audience et l'aperçu des destinataires restent coherents avec la liste de communes retenues.

### Lot 9. Extension D'Audience D'Une Campagne Deja Partie

- Statut : Valide apres le lot 8 UX
- Intentions produit :
  - permettre d'ajouter de nouveaux destinataires a une campagne deja `sent`, `stopped` ou `failed` ;
  - conserver la meme campagne et le meme contenu, sans duplication automatique ;
  - garantir le dedoublonnage des destinataires deja lies a cette campagne ;
  - presenter clairement le delta entre `deja lies` et `nouveaux destinataires`.
- Decisions produit :
  - l'extension passe par un ecran separe du ciblage normal ;
  - cet ecran part d'une selection vide et sert uniquement a ajouter ;
  - aucune suppression ni modification retroactive du ciblage historique ;
  - la source de verite du dedoublonnage est `mailing_delivery_recipient` ;
  - l'extension n'est pas disponible pendant `delivery_queued` ou `delivery_sending` ;
  - ce flux ne sert pas a relancer les `failed`, qui restent un sujet distinct.

### Lot 10. Preparation Du Prochain Module Metier

- Statut : Prochain vrai sujet recommande
- Preparer le cadrage des resumes de seances.
- Identifier les briques reutilisables deja en place :
  - layout interne ;
  - conventions de cas d'usage ;
  - conventions de lecture/ecriture ;
  - approches de mapping ;
  - patterns valides apres refacto.
- Positionner la facturation en dependance aval plutot qu'en prochain module.

## Plus Tard

- Facturation, devis et documents associes.
- Espace client connecte.
- Donnees publiques backend exposees au front.
- Evolution eventuelle des providers externes mail ou de synchronisation.

## References Actives

- Plan d'execution : `.codex/backend-refacto-plan.md`
- Roadmap mailing : integree a cette roadmap maitre tant qu'aucun fichier dedie n'est recree
- Documentation backend :
  - `jardin-sonore-backend/README.md`
  - `jardin-sonore-backend/docs/directory-import.md`
  - `jardin-sonore-backend/docs/mailing.md`

## Historique Court

- 2026-06-09 : decision de repartir sur un backend Symfony dedie dans `jardin-sonore-backend/`.
- 2026-06-22 : EasyAdmin est confirme comme backoffice technique ; l'interface metier interne occupe la racine backend.
- 2026-07-08 : cette roadmap devient la roadmap maitre ; le detail d'execution passe dans `.codex/backend-refacto-plan.md` et l'ordre produit est recale sur `resumes de seances` avant `facturation`.
- 2026-07-08 : le lot 4 mailing est considere termine ; le lot 5 annuaire/geographie demarre et les conventions Symfony/config sont consolidees pendant le refacto.
- 2026-07-08 : les repositories Doctrine sont normalises vers `ServiceEntityRepository` + `ManagerRegistry`, avec mappers explicites conserves pour les adapters de domaine.
- 2026-07-08 : le lot 5 avance encore avec `FindInstrumentCatalogItems` sans `EntityManager` direct et la commande de sync communes branchee sur des readers/writers d'infrastructure.
- 2026-07-08 : point ouvert pour le lot 6 : reduire les doublons entre `*DoctrineRepository` et `*EntityRepository` quand un seul repository suffit.
- 2026-07-08 : la phase de refacto fine est consideree comme suffisamment mure pour s'arreter ; les derniers gains ont porte sur les readers geographiques DBAL, la resolution admin des contacts partages et la simplification de l'etat `MailingAudience`.
- 2026-07-08 : une priorite produit supplementaire est ajoutee avant `resumes de seances` : refondre le ciblage d'audience mailing avec masques reutilisables, polygones, multi-cercles et communes materialisees.
