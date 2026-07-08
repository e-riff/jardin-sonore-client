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
2. Concevoir puis lancer le module `resumes de seances`.
3. Traiter la `facturation` apres les resumes de seances.
4. Garder `espace client`, donnees publiques front et extensions comme lots ulterieurs.

## Prochains Lots

### Lot 5. Refacto Annuaire Et Geographie

- Statut : En cours
- Extraction en cours des lectures bas niveau hors `Application`, en commencant par l'import annuaire.
- Le lot a aussi servi a normaliser les acces ORM simples via repositories Doctrine injectables plutot que `EntityManager->getRepository(...)`.
- `SyncMunicipalitiesFromGeoGouvCommand` delegue maintenant sa lecture/ecriture technique a des services d'infrastructure, et `FindInstrumentCatalogItems` n'injecte plus l'`EntityManager` directement.
- Prochaine etape : finir le decoupage des derniers hotspots geographiques restants, surtout ce qui peut encore etre mutualise utilement sans ceremonie en trop.

### Lot 6. Nommage Et Patterns Structurels

- Stabiliser les noms `Repository / Query / Provider / Mapper` selon le role reel de chaque service.
- Continuer a sortir les lectures techniques de `Application` uniquement la ou cela clarifie vraiment.

### Lot 7. Conventions Symfony Et Configuration

- Statut : Partiellement termine
- Config packages migrees au format `App::config([...])`.
- Parametres scalarises via `parameters.yaml.dist` + `parameters.yaml` local et `#[Autowire('%...%')]`.
- A poursuivre seulement la ou un attribut ou une convention retire une configuration diffuse reelle.

### Lot 8. Preparation Du Prochain Module Metier

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
- Roadmap mailing : `.codex/newsletter-roadmap.md`
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
