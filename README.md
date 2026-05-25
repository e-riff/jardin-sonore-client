# Jardin Sonore

Structure du depot:

- `jardin-sonore-client/` contient l'application front Next.js.
- `docker-compose.yml`, `Makefile` et `docker/` restent a la racine pour piloter le projet.

Commandes utiles:

```bash
make up
make down
make lint
make app-build
make deploy-client
```

Le front tourne via Docker Compose sur `http://localhost:3000` par defaut. Pour changer le port: `PORT=3001 make up`.
