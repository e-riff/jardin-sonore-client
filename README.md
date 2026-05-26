# Jardin Sonore

Site vitrine Jardin Sonore, construit avec Next.js et pilote en local via Docker Compose.

Le point d'entree local attendu est:

```bash
http://localhost:3000
```

## Demarrage rapide

Depuis la racine du projet:

```bash
make docker-up
```

Puis ouvrir `http://localhost:3000`.

Pour arreter:

```bash
make docker-down
```

Pour relancer proprement:

```bash
make docker-restart
```

Le port par defaut est `3000`. Pour tester ponctuellement sur un autre port:

```bash
PORT=3001 make docker-up
```

## Structure du projet

- `jardin-sonore-client/`: application front Next.js.
- `jardin-sonore-client/src/app/`: App Router, page principale et routes API.
- `jardin-sonore-client/src/components/`: composants reutilisables.
- `jardin-sonore-client/src/components/sections/`: sections de la page d'accueil.
- `jardin-sonore-client/src/i18n/`: dictionnaire et types de traduction.
- `jardin-sonore-client/public/`: images et assets publics.
- `docker-compose.yml`, `docker/`, `Makefile`: orchestration locale.
- `.codex/jardin-sonore-guidelines.md`: notes projet et conventions pour les assistants/devs.

## Commandes utiles

```bash
make help
make docker-up
make docker-down
make docker-restart
make logs
make shell
make lint
make app-build
make deploy-client
```

Commandes npm possibles depuis `jardin-sonore-client/`:

```bash
npm run lint
npm run build
```

## Captcha du formulaire

Le formulaire de contact n'utilise pas Google reCAPTCHA. Il utilise ALTCHA, une solution de captcha basee sur une preuve de travail cote navigateur.

Fonctionnement actuel:

- le widget est affiche par `src/components/AltchaWidget.tsx`;
- le challenge est cree par `src/app/api/altcha/challenge/route.ts`;
- la logique de creation/verification est dans `src/lib/altcha.ts`;
- le formulaire envoie le payload `altcha` a `src/app/api/contact/route.ts`;
- si la verification echoue, l'API renvoie une erreur `403`.

Variables importantes:

```bash
ALTCHA_HMAC_SECRET=une-cle-secrete-longue
```

En production, ne jamais utiliser la valeur de fallback de dev. Si l'app tourne sur plusieurs instances, l'anti-replay en memoire devra etre remplace par un stockage partage.

## Contact et email

Le formulaire envoie les demandes via Nodemailer. Les variables attendues sont:

```bash
CONTACT_EMAIL=
CONTACT_PHONE=
SMTP_HOST=
SMTP_PORT=
SMTP_SECURE=
SMTP_USER=
SMTP_PASSWORD=
SMTP_FROM=
```

Le numero de telephone n'est pas rendu directement dans le HTML initial. Il est revele via `src/app/api/contact-phone/route.ts` avec la variable `CONTACT_PHONE`.

## Traductions et contenu

La locale active est `fr`.

- Le contenu principal est dans `jardin-sonore-client/src/i18n/dictionaries/fr.ts`.
- Le type `Dictionary` est derive automatiquement du dictionnaire francais.
- Pour ajouter un texte visible, privilegier une entree dans le dictionnaire plutot qu'une chaine en dur dans un composant.
- Garder les textes marketing, labels de formulaire et messages d'erreur au meme endroit facilite les futures traductions.

## Bonnes pratiques

- Garder `localhost:3000` comme port local standard.
- Eviter de lancer un `npm run dev` local si le conteneur Docker est deja actif.
- Utiliser `next/image` pour les images du site.
- Garder les sections de page dans `components/sections/`.
- Garder les composants generiques dans `components/`.
- Verifier les changements avec `make lint` et `make app-build` avant de livrer une modification importante.
- Ne pas commiter de secrets `.env.local`.

## Deploiement

Le script de deploiement utilise la configuration `.env.deploy.local`, avec un exemple dans `.env.deploy.example`.

```bash
make deploy-client
```

Avant de deployer, verifier:

- les variables SMTP;
- `ALTCHA_HMAC_SECRET`;
- `CONTACT_EMAIL`;
- `CONTACT_PHONE`;
- le build avec `make app-build`.
