# Jardin Sonore Client

Front Next.js du projet.

## Demarrage local

Depuis `jardin-sonore-client/`, lancer:

```bash
npm run dev
```

Ou, depuis la racine du depot:

```bash
make up
```

L'application ecoute sur `http://localhost:3000`.

Les sources sont sous `src/` et les assets statiques sous `public/`.

## Environnement local

Les valeurs de contact privees sont lues depuis `.env.local`, ignore par git:

```bash
CONTACT_EMAIL=contact@example.com
CONTACT_PHONE=+33600000000
ALTCHA_HMAC_SECRET=change-me
SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_SECURE=false
SMTP_USER=smtp-user
SMTP_PASSWORD=smtp-password
SMTP_FROM="Jardin Sonore <no-reply@example.com>"
```

## Commandes utiles

```bash
npm run lint
npm run build
```

La page principale se trouve dans `src/app/page.tsx`.
