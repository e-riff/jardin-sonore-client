# Plan De Menage Et Refacto Backend

- Statut global : En cours
- Derniere mise a jour : 2026-07-08
- Pilote : Codex + Emeric

## Principes Non Negociables

- `Domain` reste pur : aucune dependance Symfony, Doctrine, DBAL, Twig ou EasyAdmin.
- `Application` orchestre les cas d'usage, expose les contrats et porte les DTO utiles.
- `Infrastructure` implemente persistence, UI technique, mail, stockage et acces externes.
- Cote controllers Symfony metier :
  - mapping par attribut vers des DTO applicatifs autorise et encourage quand cela clarifie l'entree (`MapQueryString`, `MapRequestPayload`, etc.) ;
  - injection directe de modeles de domaine non retenue par defaut ;
  - `ValueResolver` cible acceptable pour quelques cas stables et frequents ;
  - entites Doctrine directes reservees a `EasyAdmin` et aux zones techniques explicitement assumees.
- Pas de pattern decoratif : toute abstraction doit gagner en lisibilite, testabilite ou decouplage.
- Pour les requetes :
  - `QueryBuilder` lisible d'abord si c'est le meilleur outil du cas ;
  - `expr()` / `andX()` / `orX()` quand la combinatoire devient metierement dense ;
  - SQL inline seulement si c'est objectivement plus clair ou mieux adapte.
- Les attributs Symfony sont bienvenus quand ils clarifient le branchement ; jamais dans `Domain`.
- Un mapper explicite est acceptable ; un object mapper generique et magique ne l'est pas.
- Pour les repositories Doctrine:
  - `ServiceEntityRepository` + `ManagerRegistry` par defaut ;
  - mapper explicite conserve quand le repository adapte un modele de domaine ;
  - repository d'entite infrastructure acceptable pour les besoins ORM techniques ;
  - pas d'injection directe de `EntityManagerInterface` seulement pour enchainer des `getRepository(...)`.

## Ordre Produit Valide

1. Stabilisation et clarification du backend actuel.
2. Resumes de seances.
3. Facturation.
4. Espace client et extensions ensuite.

## Lots De Travail

### Lot 1. Menage `.codex`

- Statut : Termine
- Objectif : retirer les artefacts clos qui brouillent la lecture de `.codex`.
- Perimetre :
  - dossier `refonte barre design/` ;
  - autres exports de travail non devenus document de reference.
- Criteres de fin :
  - plus d'exports HTML/PNG lies a la barre deja integree ;
  - chaque fichier restant a une utilite active identifiable.
- Checklist :
  - [x] supprimer les artefacts de design backoffice termines ;
  - [x] verifier les autres fichiers dormants ;
  - [x] noter dans l'historique ce qui a ete retire et pourquoi.
- Commit attendu : `chore(codex): clean obsolete backoffice design artifacts`

### Lot 2. Refonte Des Roadmaps

- Statut : Termine
- Objectif : transformer les roadmaps en outils de pilotage lisibles.
- Perimetre :
  - `.codex/backend-roadmap.md`
  - `.codex/newsletter-roadmap.md`
- Criteres de fin :
  - `backend-roadmap.md` devient la roadmap maitre ;
  - `newsletter-roadmap.md` reste focalisee sur le mailing ;
  - l'ordre `resumes de seances` puis `facturation` est explicite partout.
- Checklist :
  - [x] enlever l'historique trop bavard ;
  - [x] garder `Etat actuel / Decisions / Prochains lots / Plus tard` ;
  - [x] aligner les principes d'architecture ;
  - [x] supprimer les contradictions de priorisation.
- Commits attendus :
  - `docs(roadmap): rewrite backend master roadmap`
  - `docs(roadmap): refocus newsletter roadmap`

### Lot 3. Audit D'Architecture Et Patterns

- Statut : Termine
- Objectif : identifier ce qui clarifie vraiment le backend et ce qui ajoute juste de la sophistication.
- Perimetre initial :
  - mailing ;
  - annuaire ;
  - catalogue pedagogique si necessaire.
- Criteres de fin :
  - une matrice existe avec `conforme / acceptable / a refactorer maintenant / a laisser temporairement` ;
  - une matrice existe avec `patterns a introduire / a etendre / a eviter`.
- Checklist :
  - [x] lister les services `Application` couples a Doctrine ou DBAL ;
  - [x] lister les services `Infrastructure` portant trop de logique metier ;
  - [x] lister les services mal nommes (`Provider`, `Manager`, `Helper`, etc.) ;
  - [x] reperer ou un pattern clarifierait vraiment la structure ;
  - [x] reperer ou des attributs Symfony clarifieraient le branchement.
- Commit attendu : `docs(architecture): add backend refactor audit matrix`

## Matrice D'Audit Lot 3

### Classement Par Zone

| Zone | Statut | Constat court | Action prioritaire |
| --- | --- | --- | --- |
| Mailing application | Acceptable | Les cas d'usage s'appuient deja sur des ports utiles (`NewsletterAudienceResolverInterface`, `NewsletterMailSenderInterface`, `NewsletterRendererInterface`) ; la frontiere applicative est globalement saine. | Conserver les contrats actuels et nettoyer surtout les implementations de lecture/persistance cote infrastructure. |
| Mailing infrastructure | A refactorer maintenant | `DoctrineNewsletterAudienceResolver`, `DoctrineNewsletterAudienceOptionsProvider` et `MailingDeliveryRecipientStore` concentrent beaucoup de logique de lecture et de SQL inline. | Decouper les responsabilites, clarifier les noms et restructurer les requetes au cas par cas. |
| Annuaire import/matching | A refactorer maintenant | `Application/Directory/DirectoryEstablishmentMatcher` melange heuristique metier, chargement DBAL et chargement ORM. | Extraire la lecture de candidats vers un query service d'infrastructure et laisser le scoring cote application ou domaine de service. |
| Commandes geographie/adressage | Acceptable | Plusieurs commandes `Application/Command/*` utilisent DBAL directement. Pour du batch, c'est defendable, mais la lecture SQL est encore trop inline. | Garder DBAL pour les batchs, mais pousser les lectures techniques repetitives dans des services d'infrastructure dedies. |
| Catalogue pedagogique | Acceptable | `FindInstrumentCatalogItems` n'injecte plus `EntityManagerInterface` directement et s'appuie sur un repository Doctrine cible. | Garder ce flux cote application tant qu'il reste focalise et lisible ; extraire un query service dedie seulement si la lecture grossit encore. |
| Admin et controllers | Conforme | Les attributs Symfony sont deja bien utilises sur les routes, commandes et handlers Messenger. | Etendre seulement la ou cela retire une configuration diffuse reelle. |

### Hotspots Concrets Releves

- `jardin-sonore-backend/src/Infrastructure/Mailing/DoctrineNewsletterAudienceResolver.php`
  - Role pertinent, mais implementation trop dense.
  - A conserver comme adapter d'infrastructure.
  - A refactorer en helpers prives de filtres et de geographie plus lisibles.
- `jardin-sonore-backend/src/Infrastructure/Mailing/DoctrineNewsletterAudienceOptionsProvider.php`
  - Le mot `Provider` reste acceptable ici si le service continue a fournir des choix d'UI.
  - En revanche, il agrege plusieurs lectures de referentiels et d'autocomplete dans une seule classe.
  - A decouper si la taille continue de grossir.
- `jardin-sonore-backend/src/Infrastructure/Mailing/MailingDeliveryRecipientStore.php`
  - Vraie gateway technique de persistence batch.
  - Le nom `Store` est defendable, mais un nom plus explicite de type `...Repository` ou `...Gateway` pourra etre reconsidere selon le role final.
  - Priorite : eliminer les `LIMIT` inline concatenees et rendre les transitions d'etat plus explicites.
- `jardin-sonore-backend/src/Application/Directory/DirectoryEstablishmentMatcher.php`
  - Le scoring et les normalisations ont leur place hors infrastructure.
  - La recherche des candidats SQL n'a pas sa place dans le meme service.
  - Cible : separer `candidate lookup` et `match scoring`.
- `jardin-sonore-backend/src/Application/Command/LinkAddressMunicipalitiesCommand.php`
  - Cas batch acceptable en `Application`, mais trop de lecture DBAL inline.
  - A soutenir par un service d'infrastructure de recherche de communes/candidats.
- `jardin-sonore-backend/src/Application/Command/ComputeMunicipalityCentersCommand.php`
  - Cas batch tres technique, DBAL justifie.
  - Refacto faible priorite ; surtout clarifier l'encapsulation si d'autres commandes du meme type apparaissent.

### Services Application Couples A Doctrine Ou DBAL

- `DirectoryEstablishmentMatcher`
- `DirectoryEstablishmentUpserter`
- `DeleteMailingCampaign`
- `ImportDirectoryEstablishmentsCommand`
- `LinkAddressMunicipalitiesCommand`
- `CreateAdminUserCommand`
- `MailingInvalidRecipientController`
- `ComputeMunicipalityCentersCommand`
- `SyncMunicipalitiesFromGeoGouvCommand`

Lecture de l'audit :

- acceptable si le service orchestre un cas d'usage et ne fait qu'un acces simple ;
- a reduire quand `Application` commence a connaitre les tables, les jointures ou les entites Doctrine d'infrastructure ;
- priorite haute pour les services qui melangent selection technique et logique metier de decision.

### Patterns Et Outils A Introduire Ou Etendre

- `QueryService` ou `...QueryInterface`
  - Pour les lectures cibleses de catalogue, d'autocomplete, d'options et de candidats de matching.
  - Recommande pour sortir les lectures complexes de `Application`.
- `RepositoryInterface`
  - A conserver pour les agregats et objets metier deja geres ainsi.
  - Ne pas lui faire porter toutes les lectures de projection.
- repository Doctrine d'infrastructure
  - A preferer sous forme de `ServiceEntityRepository` injectable quand l'acces reste focalise sur une entite ORM ou un agregat adapte.
  - Acceptable en parallele d'un repository de domaine quand le besoin est purement technique et ne doit pas remonter dans `Domain`.
- `Mapper` explicite
  - Pertinent pour les frontieres domaine <-> infrastructure deja en place.
  - Pertinent aussi pour certains DTO de lecture si cela evite d'exposer des entites Doctrine en `Application`.
  - Regle validee :
    - `EasyAdmin` utilise directement les entites Doctrine et reste une exception assumee ;
    - les controllers et cas d'usage metier doivent preferer modeles de domaine, DTO applicatifs ou read models dedies ;
    - un mapper explicite `Entity -> Domain` ou `Entity -> DTO` est prefere a tout object mapper generique.
- attributs de mapping Symfony vers DTO applicatifs
  - Recommandes dans `Application/Controller` pour clarifier les entrees HTTP.
  - A combiner avec des DTO de commande, de filtre ou de formulaire, pas avec des entites Doctrine.
- `ValueResolver` cible
  - Acceptable pour resoudre un objet metier ou applicatif stable a partir d'un parametre de route.
  - A limiter aux cas frequents, pour ne pas masquer la lecture du flux.
- `Strategy`
  - Potentiellement utile plus tard pour certains matching rules ou certains modes de ciblage si la variabilite augmente.
  - Pas necessaire immediatement.
- `Facade` applicative legere
  - Acceptable seulement si plusieurs sous-services doivent etre orchestres derriere une intention unique.
  - Pas a introduire sans besoin concret.

### Patterns Et Outils A Eviter Pour L'Instant

- `Provider` comme mot fourre-tout
  - A garder uniquement quand le role de fourniture est reel et lisible.
- object mapper generique
  - Risque eleve de masquer les frontieres DDD.
  - Preferer des mappers explicites, locaux et assumés.
  - Le recours a un mapper automatique Doctrine ou transverse n'est pas retenu pour l'instant.
- abstraction prematuree des batch commands
  - Les commandes geographiques restent tres techniques ; ne pas les noyer dans une couche ceremonielle.
- migration massive vers des attributs Symfony
  - Les attributs existent deja aux bons endroits principaux.
  - N'ajouter que la ou ils simplifient reellement.

### Attributs Symfony : Etat Et Opportunites

- Deja bien utilises :
  - `#[Route]` sur les controllers ;
  - `#[AsCommand]` sur les commandes ;
  - `#[AsMessageHandler]` sur les handlers Messenger ;
  - `#[Autowire]` sur quelques injections ciblees utiles.
- Opportunites raisonnables :
  - etendre `#[Autowire]` localement quand cela remplace une configuration diffuse peu lisible ;
  - conserver les attributs dans `Application` et `Infrastructure` uniquement.
- A ne pas faire :
  - introduire des attributs Symfony ou Doctrine dans `Domain` ;
  - convertir en masse des services deja clairs juste pour homogeniser.

### Lot 4. Refacto Acces Donnees Mailing

- Statut : Termine
- Objectif : assainir la couche la plus chargee en SQL/DBAL et clarifier les contrats applicatifs.
- Perimetre prioritaire :
  - `DoctrineNewsletterAudienceResolver`
  - `DoctrineNewsletterAudienceOptionsProvider`
  - `MailingDeliveryRecipientStore`
- Regle de decision pour les requetes :
  1. `QueryBuilder` lisible si la requete reste simple a suivre ;
  2. `QueryBuilder` structure avec `expr()` / `andX()` / `orX()` / helpers prives si plusieurs branches se combinent ;
  3. SQL inline si c'est le meilleur choix concret.
- Criteres de fin :
  - contrats applicatifs explicites ;
  - logique de filtres plus lisible ;
  - SQL inline reduit ou mieux justifie ;
  - DBAL conserve seulement quand le cas l'impose.
- Checklist :
  - [x] isoler un premier service de persistance batch et clarifier ses constantes et transitions d'etat (`MailingDeliveryRecipientStore`) ;
  - [x] clarifier `DoctrineNewsletterAudienceResolver` avec des helpers prives, des alias explicites et davantage de `QueryBuilder` compose ;
  - [x] clarifier `DoctrineNewsletterAudienceOptionsProvider` sans casser son contrat actuel, en mutualisant les requetes et les helpers de labels/tri ;
  - [x] expliciter la delivery comme sous-concept applicatif avec une interface de store et une enum dediee de statuts ;
  - [x] isoler lecture, options, resolution et persistance sur l'ensemble du lot ;
  - [x] convertir les filtres complexes restants en helpers prives explicites ;
  - [x] documenter dans l'audit que le SQL inline reste acceptable pour certains cas batch ou geographiques ;
  - [x] verifier que `Domain` reste intact et pur.
- Commits attendus :
  - `refactor(mailing): clarify audience resolution boundaries`
  - `refactor(mailing): split mailing read services`
  - `refactor(mailing): simplify delivery recipient persistence`

### Lot 5. Refacto Annuaire Et Geographie

- Statut : En cours
- Objectif : reduire les requetes techniques dispersees dans `Application` quand elles devraient vivre en infrastructure dediee.
- Perimetre probable :
  - `DirectoryEstablishmentMatcher`
  - commandes de liaison, adressage et geographie
  - services de lecture associes
- Criteres de fin :
  - `Application` orchestre ;
  - l'acces bas niveau aux tables est pousse en infrastructure si besoin ;
  - les cas batch et geo restent pragmatiques.
- Checklist :
  - [x] extraire le lookup des candidats d'organisation hors `DirectoryEstablishmentMatcher` ;
  - [x] extraire les lookups de commune et de contacts partages hors `DirectoryEstablishmentUpserter` ;
  - [x] remplacer les `EntityManager->getRepository(...)` simples par des repositories Doctrine injectables quand un acces cible suffisait ;
  - [x] sortir la lecture et l'ecriture techniques de `SyncMunicipalitiesFromGeoGouvCommand` vers des services d'infrastructure dedies ;
  - [ ] deplacer les lectures DBAL trop techniques restantes hors `Application` ;
  - [ ] garder les commandes orientees cas d'usage, pas SQL ;
  - [ ] mutualiser les helpers geographiques seulement si la repetition est reelle.
- Commit attendu : `refactor(directory): move low-level reads behind infrastructure services`

### Lot 6. Nommage Et Patterns Structurels

- Statut : A faire
- Objectif : poser une nomenclature et des usages coherents pour les abstractions.
- Regles a appliquer :
  - `RepositoryInterface` : chargement et sauvegarde d'objets metier ;
  - `QueryInterface` ou `QueryService` : lectures ciblees, projections, stats, autocomplete, options ;
  - `ProviderInterface` : uniquement si le terme decrit reellement le role ;
  - `Mapper` : conversion explicite entre couches ou formats ;
  - `Factory`, `Strategy`, `Facade` : seulement si la variabilite ou la composition le justifie vraiment.
- Criteres de fin :
  - les noms refletent le vrai role ;
  - moins de services fourre-tout ;
  - aucune abstraction ajoutee sans benefice concret.
- Checklist :
  - [ ] renommer les services ambigus si necessaire ;
  - [ ] regrouper les lectures par responsabilite ;
  - [ ] supprimer ou eviter les abstractions inutiles ;
  - [ ] verifier si un mapper explicite local aide sur certains flux applicatifs.
- Commit attendu : `refactor(architecture): align naming and structural patterns`

### Lot 7. Attributs Symfony Utiles

- Statut : En cours
- Objectif : ameliorer la lisibilite du branchement sans coupler le domaine au framework.
- Perimetre :
  - controleurs ;
  - autowiring cible ;
  - listeners et subscribers ;
  - validation et mapping cote `Application` ou `Infrastructure`.
- Criteres de fin :
  - moins de configuration diffuse ;
  - aucun attribut Symfony dans `Domain`.
- Checklist :
  - [x] ne migrer que ce qui simplifie reellement ;
  - [x] convertir les parametres scalaires pertinents en `#[Autowire('%...%')]` ;
  - [x] migrer `SharedContactSubscriber` vers un attribut Doctrine adapte ;
  - [x] basculer les configs package backend en `App::config([...])` ;
  - [x] poser la convention `parameters.yaml.dist` versionne + `parameters.yaml` local non versionne ;
  - [x] eviter les conversions massives pour faire moderne ;
  - [x] verifier que l'intention est plus claire apres changement.
- Commit attendu : `refactor(symfony): use attributes where wiring becomes clearer`

### Lot 8. Preparation Du Module Resumes De Seances

- Statut : A faire
- Objectif : preparer le terrain fonctionnel et architectural du prochain module.
- Criteres de fin :
  - la roadmap maitre decrit clairement le prochain module ;
  - les conventions backend sont assez stables pour demarrer sans reposer les fondations.
- Checklist :
  - [ ] preciser les dependances eventuelles avec la facturation ;
  - [ ] lister les briques reutilisables deja pretes ;
  - [ ] identifier ce qu'il faut construire avant le premier use case.
- Commit attendu : `docs(product): prepare session summaries roadmap`

## Questions Ouvertes

- Quelle sera la granularite fonctionnelle exacte des resumes de seances ?
- La facturation dependra-t-elle directement des resumes ou seulement d'une partie de leurs donnees ?
- Faut-il creer un dossier `docs/architecture/` cote backend quand la matrice d'audit commencera a grossir ?

## Historique Court

- 2026-07-08 - Creation du fichier maitre de pilotage pour executer le menage et la refacto par lots.
- 2026-07-08 - Lot 1 termine : suppression des exports de design backoffice closes et verification des fichiers `.codex` restants.
- 2026-07-08 - Lot 2 termine : reecriture de la roadmap maitre backend et recentrage de la roadmap mailing.
- 2026-07-08 - Lot 3 termine : ajout de la matrice d'audit architecture/patterns avec classement des hotspots mailing, annuaire et catalogue.
- 2026-07-08 - Lot 4 demarre : premier refacto de `MailingDeliveryRecipientStore` pour clarifier les statuts, l'horodatage et remplacer le SQL inline le plus fragile.
- 2026-07-08 - Regle validee : `EasyAdmin` garde les entites Doctrine directes ; hors EasyAdmin, on prefere modeles de domaine, DTO ou read models avec mappers explicites plutot qu'un object mapper generique.
- 2026-07-08 - Lot 4 poursuit : `DoctrineNewsletterAudienceResolver` est restructure avec alias explicites, helpers prives et usage de `QueryBuilder` plus compose.
- 2026-07-08 - Lot 4 poursuit : `DoctrineNewsletterAudienceOptionsProvider` est nettoye sans changer son interface, avec helpers partages pour les communes, labels et tri des departements.
- 2026-07-08 - Lot 4 poursuit : la persistance des destinataires de delivery passe derriere `MailingDeliveryRecipientStoreInterface`, l'implementation Doctrine est renommee et une enum `MailingDeliveryRecipientStatus` remplace les chaines en dur.
- 2026-07-08 - Lot 4 est boucle : les services mailing prioritaires sont clarifies sans reinjecter de couplage framework dans `Domain`.
- 2026-07-08 - Lot 5 demarre : `DirectoryEstablishmentUpserter` delegue maintenant la recherche de commune et de contacts partages a des services d'infrastructure dedies.
- 2026-07-08 - Lot 7 avance en parallele : config backend migree vers `App::config([...])`, convention `parameters.yaml.dist` / `parameters.yaml` posee et injections scalaires ciblees migrees en `#[Autowire('%...%')]`.
- 2026-07-08 - Les repositories Doctrine sont normalises : les adapters de domaine passent en `ServiceEntityRepository` + `ManagerRegistry` avec mapper, et les acces ORM techniques simples passent par des repositories d'entite injectables.
- 2026-07-08 - `FindInstrumentCatalogItems` n'injecte plus `EntityManagerInterface` et `SyncMunicipalitiesFromGeoGouvCommand` delegue ses lectures/ecritures techniques a des ports de geographie dedies.
