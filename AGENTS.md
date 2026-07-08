# AGENTS.md

## Source projet

- Lire et respecter `.codex/jardin-sonore-guidelines.md` avant toute modification importante.
- Garder ce fichier comme synthese courte; les consignes detaillees restent dans `.codex/`.

## Contexte

- Repo local: `/home/eriff/Developpement/jardinsonore`.
- Front Next.js dans `jardin-sonore-client/`.
- Orchestration locale principale via Docker Compose depuis la racine.
- Point d'entree local par defaut: `http://localhost:3000`.

## Commandes usuelles

- Depuis la racine: `make docker-up`, `make docker-down`, `make docker-restart`, `make logs`, `make shell`, `make lint`, `make app-build`.
- Depuis `jardin-sonore-client/`: `npm run lint` et `npm run build` sont valides pour verification rapide hors conteneur.
- Ne pas lancer de serveur dev parallele sur `3001` si le conteneur client tourne deja sur `3000`.

## Architecture

- `jardin-sonore-client/src/app/`: App Router Next.js, layout, page principale, routes API.
- `jardin-sonore-client/src/components/`: composants UI reutilisables.
- `jardin-sonore-client/src/components/sections/`: sections composees de la page d'accueil.
- `jardin-sonore-client/src/i18n/`: dictionnaires, types, provider de traduction.
- `jardin-sonore-client/public/`: assets publics servis par Next.
- Un futur backend doit rester dans ce repo, separe clairement du front, par exemple `jardin-sonore-api/` ou `backend/`.

## Front et contenu

- Suivre les patterns existants avant d'ajouter une abstraction.
- Eviter les refactors larges non demandes.
- Mettre les sections de page dans `components/sections/` et les composants generiques dans `components/`.
- Pour les textes UI/marketing, passer par `src/i18n/dictionaries/fr.ts` plutot que hardcoder les strings.
- Locale actuelle: `fr`; `Dictionary` est derive du dictionnaire FR.
- Utiliser `next/image` pour les images publiques et des chemins issus de `public/`.
- Ajouter `'use client';` seulement quand necessaire.
- Respecter TypeScript strict, imports absolus `@/...`, composants React fonctionnels et Tailwind utilitaire.

## PHP et Symfony

- Toujours ajouter `declare(strict_types=1);` dans les nouveaux fichiers PHP, sauf fichier de configuration ou convention projet contraire.
- Toujours typer explicitement les constantes quand la version PHP du projet le permet.
- Importer les classes natives PHP avec `use` pour eviter les references pleinement qualifiees comme `\DateTimeImmutable`, `\Throwable` ou `\BackedEnum`.
- Ne pas importer les fonctions natives PHP avec `use function`, sauf convention contraire deja presente dans le projet.
- Preferer l'interpolation de variables dans les chaines quand elle reste lisible, par exemple `"Bonjour {$name}"`, plutot que la concatenation.
- Quand la concatenation reste plus claire ou necessaire, espacer le point: `"Bonjour " . $name`, pas `"Bonjour ".$name`.
- Quand c'est pertinent pour suivre une relation entre deux classes, ajouter une reference PHPDoc courte avec `@see`, par exemple entre une commande et son handler. Eviter les `@see` decoratifs qui n'aident pas la navigation.
- Utiliser autant que possible les attributs Symfony quand ils rendent le branchement plus explicite: autowiring/autoconfiguration ciblee, routes, listeners/subscribers, Monolog, validation, securite, mapping, etc.
- Eviter les attributs dans le domaine ou dans les zones ou ils ajouteraient du couplage inutile; demander confirmation si un arbitrage est necessaire.
- Pour les controllers metier Symfony, preferer les attributs de mapping (`MapQueryString`, `MapRequestPayload`, etc.) vers des DTO applicatifs quand cela clarifie l'entree HTTP.
- Pour les listeners et subscribers Symfony/Doctrine applicatifs ou d'infrastructure, preferer les attributs (`AsEventListener`, `AsDoctrineListener`, etc.) quand ils remplacent utilement une declaration diffuse dans les services.
- Garder les tags explicites dans `config/services.php` pour les listeners tiers qui ne sont pas autoconfigures par le framework ou le bundle en place.
- Pour les injections de parametres scalaires ou de chemins, preferer `#[Autowire('%...%')]` dans les classes plutot qu'un cablage dedie dans `services.php` quand cela rend l'origine de la valeur plus lisible.
- Pour la configuration Symfony en PHP, preferer le format declaratif le plus recent `namespace Symfony\Component\DependencyInjection\Loader\Configurator;` puis `return App::config([...]);`.
- Quand un package est configure en PHP, preferer `config/packages/*.php` avec `App::config([...])` plutot que les configurateurs imperatifs anciens, sauf contrainte concrete.
- Pour les repositories Doctrine:
  - quand un vrai repository de domaine existe, garder un adapter d'infrastructure dedie avec mapper explicite ;
  - preferer le pattern `ServiceEntityRepository` + `ManagerRegistry` plutot qu'injecter `EntityManagerInterface` uniquement pour faire des `getRepository(...)` ;
  - pour les acces ORM purement techniques sans realite metier de domaine, un repository d'entite infrastructure injectable est acceptable ;
  - ne garder `EntityManagerInterface` injecte directement que lorsqu'il sert reellement a autre chose qu'a recuperer un repository.
- Pour les parametres applicatifs:
  - versionner `config/parameters.yaml.dist` comme reference ;
  - garder `config/parameters.yaml` local et non versionne ;
  - faire resoudre les valeurs par les `.env` / `.env.local` ou les surcharges locales plutot que remettre des secrets en dur dans `services.php`.
- Pour les requetes Doctrine `QueryBuilder`, preferer une construction lisible et composee: utiliser autant que possible `expr()`, `andX()`/`orX()` ou des methodes/helpers prives dedies plutot que de longues chaines SQL/DQL inline, surtout des que plusieurs conditions se combinent.

## Contact et captcha

- Le formulaire de contact utilise ALTCHA.
- Challenge: `src/app/api/altcha/challenge/route.ts`.
- Creation/verification: `src/lib/altcha.ts`.
- Submit contact: `src/app/api/contact/route.ts`.
- Widget front: `src/components/AltchaWidget.tsx`, integre dans `CtaContactPanel.tsx`.
- La Map en memoire `usedChallenges` est acceptable en dev/simple instance, mais devra devenir un stockage partage si plusieurs instances backend sont deployees.
- Le telephone est revele via `src/app/api/contact-phone/route.ts` et `CONTACT_PHONE`.

## Verification

- Pour les changements significatifs: lancer au minimum `npm run lint`; lancer `npm run build` pour les changements de build/runtime.
- Pour une migration Doctrine, verifier avant commit que le diff de schema est propre: il doit correspondre uniquement au besoin de la tache et ne plus rester aucun changement parasite ou hors scope a generer.
- Ne pas laisser un serveur dev supplementaire actif si l'utilisateur ne l'a pas demande.
- Signaler les commandes executees et les fichiers modifies.
- Proposer un message de commit conventional commit en fin de tache quand il y a des changements.

## Deploiement

- Avant tout deploiement, verifier l'etat git et lancer les verifications adaptees au changement.
- Si une migration est necessaire, la lancer et verifier son resultat avant de preparer le deploiement.
- Toujours commit les changements deployes avant de deployer.
- Toujours creer un tag git sur le commit deploye avant de deployer, pour identifier clairement ce qui part en production.
- Toujours pousser la branche distante avant de deployer.
- Quand un tag de deploiement est cree, toujours pousser aussi le tag distant, pas seulement la branche.
- Ne deployer qu'apres commit et tag reussis, sauf demande explicite contraire de l'utilisateur.
