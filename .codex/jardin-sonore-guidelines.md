# Jardin Sonore - Guidelines Projet

## Contexte general

- Repo local: `/home/eriff/Developpement/jardinsonore`.
- Application actuelle: front Next.js dans `jardin-sonore-client/`.
- Pilotage local principal: Docker Compose depuis la racine du repo.
- Port local de reference: `http://localhost:3000`.
- Ne pas lancer un serveur dev parallele sur `3001` si le conteneur client est deja actif sur `3000`; preferer le conteneur existant ou `make docker-up`.

## Commandes usuelles

- Depuis la racine:
  - `make docker-up` lance le client Docker sur `localhost:3000`.
  - `make docker-down`:stoppe Compose.
  - `make docker-restart`: relance Compose.
  - `make logs`: suit les logs client.
  - `make shell`: ouvre un shell dans le conteneur client.
  - `make lint`: lance lint dans un conteneur jetable.
  - `make app-build`: lance le build Next dans un conteneur jetable.
- Depuis `jardin-sonore-client/`, `npm run lint` et `npm run build` sont valides pour verification rapide hors conteneur.
- Le port Docker est configurable seulement si necessaire avec `PORT=3001 make docker-up`, mais le defaut attendu reste `3000`.

## Architecture actuelle

- `jardin-sonore-client/src/app/`: App Router Next.js, layout, page principale et routes API.
- `jardin-sonore-client/src/components/`: composants UI reutilisables.
- `jardin-sonore-client/src/components/sections/`: sections composees de la page d'accueil.
- `jardin-sonore-client/src/i18n/`: dictionnaires, types et provider de traduction.
- `jardin-sonore-client/src/types/`: types de contenu partages.
- `jardin-sonore-client/public/`: assets publics servis par Next.
- `docker-compose.yml`, `docker/Dockerfile` et `Makefile`: orchestration projet a la racine.

## Portail structures

- Le portail a été reconstruit depuis la pause du 18 septembre : comptes, séances, comptines, profil et impersonation sont disponibles. L'ancienne consigne de portail masqué ne décrit plus l'état courant ; consulter [la roadmap centrale](../ROADMAP.md).
- Le backend reste l’autorité unique pour les comptes, accès aux organisations, séances et documents. Le client Next.js utilise un BFF serveur et un cookie HTTP-only.
- Les notifications et la newsletter sont décrites dans la [roadmap centrale](../ROADMAP.md) pour leur état courant et dans les plans datés pour leur historique d'exécution.

## Frontières front et backend

- `jardin-sonore-client/` contient le front Next.js et ses routes serveur ; `jardin-sonore-backend/` contient l'API Symfony, le backoffice, les données métier et les traitements asynchrones.
- `docker-compose.yml` à la racine orchestre les services locaux.
- Garder le backend comme autorité pour les comptes, accès, séances, abonnements et documents. Les routes Next de contact, captcha et révélation du téléphone restent adaptées à leurs besoins simples ; les routes BFF du portail et de la newsletter transmettent au backend sans exposer les secrets au navigateur.

## Decoupage et bonnes pratiques front

- Suivre les patterns existants avant d'ajouter une abstraction.
- Garder les sections de page dans `components/sections/`.
- Garder les composants generiques dans `components/`.
- Eviter les refactors larges non demandes.
- Pour les donnees textuelles et marketing, passer par le dictionnaire i18n plutot que mettre du texte en dur dans les composants.
- Pour les images publiques, utiliser `next/image` et des chemins depuis `public/`.
- Pour les interactions client, marquer le fichier avec `'use client';` uniquement quand necessaire.
- Verifier avec `npm run lint` et `npm run build` apres les changements significatifs.
- Respecter le style existant: TypeScript strict, imports absolus `@/...`, composants React fonctionnels, Tailwind en classes utilitaires.
- Eviter les cartes imbriquées, en particulier sur mobile : reserver les surfaces encadrées aux vrais groupes autonomes et preferer des séparateurs simples pour les listes et sous-sections.
- Distinguer clairement navigation et action dans le backoffice et le portail : réserver les liens à la navigation et présenter les commandes comme des boutons (texte, icône ou menu déroulant). Le bouton « Retour » suit aussi le style des boutons. Utiliser des icônes pertinentes lorsque cela clarifie rapidement le rôle d'une commande, avec un libellé accessible pour les boutons icône seuls.

## Repères d’interface du backoffice

- Dans tous les formulaires métier (`.internal-form`) et filtres (`.internal-search`), séparer visiblement chaque libellé de son champ, y compris les champs Symfony imbriqués et ceux placés dans un `fieldset`. L’anneau de focus ne doit jamais toucher ni recouvrir le libellé. Appliquer l’espacement via les règles CSS communes, sans correctif ponctuel sur une page.
- Pour garder des formulaires lisibles et alignés entre les écrans, utiliser le conteneur commun `.internal-card--form` pour centrer et limiter leur largeur ; utiliser `.internal-form--wide` uniquement lorsque le contenu justifie réellement toute la largeur. Les champs liés peuvent être regroupés en colonnes sur ordinateur, puis repasser sur une colonne sur mobile.
- Pour les cases à cocher des formulaires Symfony, utiliser `form_row` et le rendu commun `.internal-form__checkbox` afin d’aligner la case et son libellé sur une seule ligne ; ne pas assembler manuellement `form_widget` et `form_label` dans une rangée générique.
- Un champ d’autocomplétion Tom Select lié à une association simple reste en sélection unique. Pour saisir plusieurs relations, afficher une ligne distincte par relation plutôt que laisser le contrôle accepter des choix que le formulaire ne peut pas enregistrer.
- **Bouton principal** : l’action principale d’un formulaire ou d’une page. Garder une seule action principale visible par zone.
- **Bouton secondaire** : action courante qui complète le parcours, comme modifier ou rattacher. Les actions répétées d’une ligne restent compactes.
- **Action destructive** : annuler, supprimer ou détacher apparaît dans le détail de l’élément ; demander confirmation lorsque l’action détruit ou archive une donnée.
- **Fenêtre en surimpression (`dialog`)** : modifier un élément avec plusieurs champs ou afficher ses actions. Elle doit pouvoir se fermer avec Échap, un bouton de fermeture et un clic sur le fond.
- Dans les formulaires en dialogue, aligner les actions « Annuler » et « Enregistrer » côte à côte, y compris sur mobile, en gardant leurs libellés visibles.
- **Popover/menu** : choix court lié à un bouton, par exemple « Ajouter ». Fermer au clic extérieur et avec Échap.
- **Dépliant (`details/summary`)** : contenu secondaire que l’on consulte ponctuellement, comme l’historique, les filtres avancés ou un bloc de dossier. Garder un chevron cohérent, aligné à droite.
- **Icônes** : utiliser les icônes SVG du backoffice ; accompagner les icônes seules d’un nom accessible. Réserver les icônes décoratives aux repères visuels simples.
- Pour ouvrir une fiche liée depuis une action contextuelle (par exemple EasyAdmin depuis un dossier), garder un lien de navigation au rendu bouton icône compact (`.internal-icon-button` ou `.commercial-dossier__icon-action`), avec `title` et nom accessible. Éviter le lien texte vert souligné pour ces actions.
- **Listes et grilles** : une ligne ou carte peut ouvrir son détail si son apparence indique clairement qu’elle est cliquable. Prévoir un état de survol et de focus clavier, puis passer à une colonne sur mobile.

## PHP et Symfony backend

- Toujours ajouter `declare(strict_types=1);` dans les nouveaux fichiers PHP, sauf fichier de configuration ou convention projet contraire.
- Toujours typer explicitement les constantes PHP quand la version du projet le permet.
- Importer les classes natives PHP avec `use` pour eviter les references pleinement qualifiees comme `\DateTimeImmutable`, `\DateTimeInterface`, `\Throwable` ou `\BackedEnum`.
- Ne pas importer les fonctions natives PHP avec `use function`, sauf convention contraire deja presente dans le projet.
- Preferer l'interpolation de variables dans les chaines quand elle reste lisible, par exemple `"Bonjour {$name}"`, plutot que la concatenation.
- Quand la concatenation reste plus claire ou necessaire, espacer le point: `"Bonjour " . $name`, pas `"Bonjour ".$name`.
- Quand c'est pertinent pour suivre une relation entre deux classes, ajouter une reference PHPDoc courte avec `@see`, par exemple entre une commande et son handler. Eviter les `@see` decoratifs qui n'aident pas la navigation.
- Utiliser autant que possible les attributs Symfony quand ils rendent le branchement plus explicite: autowiring/autoconfiguration ciblee, routes, listeners/subscribers, Monolog, validation, securite, mapping, etc.
- Eviter les attributs dans le domaine ou dans les zones ou ils ajouteraient du couplage inutile; demander confirmation si un arbitrage est necessaire.
- Pour les controllers metier Symfony, preferer les attributs de mapping (`MapQueryString`, `MapRequestPayload`, etc.) vers des DTO applicatifs quand cela clarifie l'entree HTTP.
- Pour les listeners et subscribers Symfony/Doctrine applicatifs ou d'infrastructure, preferer les attributs (`AsEventListener`, `AsDoctrineListener`, etc.) quand ils remplacent utilement une declaration dans `services.php`.
- Garder les tags explicites dans `config/services.php` pour les listeners tiers qui ne sont pas autoconfigures par le framework ou par le bundle utilise.
- Pour les injections de parametres scalaires, de chemins ou de ressources cibleses, preferer `#[Autowire('%...%')]` dans les classes plutot qu'un cablage dedie dans `services.php` quand cela clarifie la dependance.
- Pour la configuration Symfony en PHP, preferer le format declaratif `namespace Symfony\Component\DependencyInjection\Loader\Configurator;` puis `return App::config([...]);`.
- Quand un package est configure en PHP, preferer `config/packages/*.php` avec `App::config([...])` plutot que les anciens configurateurs imperatifs, sauf contrainte concrete.
- Pour les repositories Doctrine:
  - quand un repository de domaine est necessaire, preferer un adapter d'infrastructure dedie avec mapper explicite ;
  - preferer `ServiceEntityRepository` + `ManagerRegistry` plutot qu'injecter `EntityManagerInterface` uniquement pour appeler `getRepository(...)` ;
  - pour les acces ORM purement techniques sans vrai modele de domaine derriere, un repository d'entite infrastructure injectable est acceptable ;
  - ne garder `EntityManagerInterface` injecte directement que s'il sert reellement a autre chose qu'a recuperer des repositories.
- Pour les parametres applicatifs:
  - versionner `config/parameters.yaml.dist` comme reference partagee ;
  - garder `config/parameters.yaml` local et non versionne ;
  - faire resoudre les valeurs depuis `.env`, `.env.local` ou les variables d'environnement du serveur plutot que mettre des donnees sensibles en dur dans `services.php`.

## Traductions et contenu

- Locale active actuelle: `fr`.
- `src/i18n/locales.ts` contient `locales = ["fr"]` et `defaultLocale = "fr"`.
- Le dictionnaire source est `src/i18n/dictionaries/fr.ts`.
- Le type `Dictionary` est derive du dictionnaire FR dans `src/i18n/types.ts`; ajouter les champs dans le dictionnaire met donc les types a jour.
- Les composants client lisent les traductions via `TranslationsProvider` / `useTranslations` quand ils en ont besoin.
- Lors d'un ajout de texte UI, preferer:
  - ajouter la cle dans `fr.ts`;
  - consommer la cle depuis le composant;
  - eviter les strings hardcodees sauf labels techniques internes ou aria tres locaux.

## Captcha et contact

- Le formulaire de contact utilise ALTCHA.
- Challenge captcha: `src/app/api/altcha/challenge/route.ts`.
- Creation/verification: `src/lib/altcha.ts`.
- Verification au submit: `src/app/api/contact/route.ts`.
- Widget front: `src/components/AltchaWidget.tsx`, integre dans `CtaContactPanel.tsx`.
- Parametres actuels:
  - algorithme: `PBKDF2/SHA-256`;
  - cost: `30_000`;
  - expiration: 10 minutes;
  - secret: `ALTCHA_HMAC_SECRET`, fallback dev `dev-altcha-secret-change-me`.
- Anti-replay actuel: Map en memoire `usedChallenges`; suffisant en dev/simple instance, a remplacer par un stockage partage si plusieurs instances backend sont deployeees.
- Le formulaire envoie ensuite l'email via Nodemailer avec `SMTP_HOST`, `SMTP_PORT`, `SMTP_SECURE`, `SMTP_USER`, `SMTP_PASSWORD`, `SMTP_FROM`, `CONTACT_EMAIL`.
- Le telephone est revele via `src/app/api/contact-phone/route.ts` et `CONTACT_PHONE`, pour eviter d'exposer directement la valeur dans le HTML initial.

## Docker et ports

- Compose publie le port `${PORT:-3000}:3000`.
- Le conteneur lance `npm install && npm run dev -- --hostname 0.0.0.0`.
- Quand le conteneur est deja actif sur `3000`, ne pas demarrer un autre serveur local Next sauf besoin explicite.
- Si `npm run dev` est necessaire hors Docker et que `3000` est pris, Next bascule souvent sur `3001`; penser a l'arreter ensuite.

## Checklist avant fin de tache

- Ne pas laisser un serveur dev supplementaire actif si l'utilisateur ne l'a pas demande.
- Verifier que `localhost:3000` reste le point d'entree local par defaut.
- Lancer au minimum `npm run lint` et, pour les changements de build/runtime, `npm run build`.
- Pour la suite PHPUnit backend complete, utiliser directement `docker compose exec -T php php -d memory_limit=1G ./bin/phpunit` depuis la racine : la limite PHP par defaut de 128 Mo fait regulierement echouer le demarrage du conteneur de test.
- Signaler clairement les commandes executees et les fichiers modifies.
- Proposer un message de commit format conventional commit pour les changements en cours

## Suite a reprendre

- Priorité urgente : permettre la correction de la structure rattachée à un dossier ; voir la roadmap pour le besoin et les points de vigilance.
- Lire [ROADMAP.md](../ROADMAP.md) pour l'état courant, les priorités et le prochain chantier. Ce fichier garde uniquement les conventions techniques ; les conceptions, plans et bilans datés sont des archives de décisions et de recettes. Ne pas y recopier un point de reprise qui vieillira.

## Deploiement backend

- Avant tout deploiement backend, verifier l'etat git et lancer les verifications adaptees au changement.
- Si une migration est necessaire, la lancer et verifier son resultat avant de preparer le deploiement.
- En fin de fonctionnalite, avant mise en ligne, regrouper si possible les migrations intermediaires du meme sujet en une migration finale lisible. Verifier son SQL, son `down()`, le diff de schema et son execution sur une base locale. Ne jamais reecrire l'historique deja applique en production.
- Toujours commit les changements deployes avant de deployer.
- Toujours creer un tag git sur le commit deploye avant de deployer, pour identifier clairement ce qui part en production.
- Toujours pousser la branche distante avant de deployer.
- Quand un tag de deploiement est cree, toujours pousser aussi le tag distant, pas seulement la branche.
- Ne deployer qu'apres commit, tag, push branche et push tag reussis, sauf demande explicite contraire de l'utilisateur.

## Hydratation et révisions de concurrence

- Une lecture ou hydratation doit conserver les dates persistées et les états techniques. Si le constructeur appelle une méthode de modification qui change `updatedAt`, restaurer la date fournie après tous ces appels.
- Pour les outils MCP avec révision, vérifier que deux lectures espacées du même objet inchangé donnent la même révision, puis qu'une modification réelle invalide l'ancienne révision. Couvrir aussi le passage par le mapper Doctrine.
- Un conflit de révision ne permet pas de conclure à une édition ou un auto-save dans un navigateur sans preuve.
- Dans toute fonction anonyme et toute closure ajoutée ou modifiée, typer explicitement les paramètres et le type de retour quand ils sont déterminables. Ne pas compter sur l'inférence du contexte pour omettre ces types. Vérifier aussi les callbacks des fonctions de tableau, du traitement asynchrone et des transactions.
