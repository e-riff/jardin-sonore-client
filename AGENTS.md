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
- Importer les classes natives PHP avec `use` pour eviter les references pleinement qualifiees comme `\DateTimeImmutable`, `\Throwable` ou `\BackedEnum`.
- Ne pas importer les fonctions natives PHP avec `use function`, sauf convention contraire deja presente dans le projet.
- Preferer l'interpolation de variables dans les chaines quand elle reste lisible, par exemple `"Bonjour {$name}"`, plutot que la concatenation.
- Quand la concatenation reste plus claire ou necessaire, espacer le point: `"Bonjour " . $name`, pas `"Bonjour ".$name`.
- Quand c'est pertinent pour suivre une relation entre deux classes, ajouter une reference PHPDoc courte avec `@see`, par exemple entre une commande et son handler. Eviter les `@see` decoratifs qui n'aident pas la navigation.
- Utiliser autant que possible les attributs Symfony quand ils rendent le branchement plus explicite: autowiring/autoconfiguration ciblee, routes, listeners/subscribers, Monolog, validation, securite, mapping, etc.
- Eviter les attributs dans le domaine ou dans les zones ou ils ajouteraient du couplage inutile; demander confirmation si un arbitrage est necessaire.

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
