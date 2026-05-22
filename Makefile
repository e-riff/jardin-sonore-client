APP_NAME ?= jardin-sonore-client
IMAGE_NAME ?= $(APP_NAME):dev
SERVICE ?= client
COMPOSE_FILE ?= docker-compose.yml
COMPOSE ?= docker compose -f $(COMPOSE_FILE)
PORT ?= 3000
NPM_ARGS ?= --version
SCRIPT ?= lint

.PHONY: help docker-build docker-up docker-down docker-restart logs shell docker-ps clean lint app-build npm npm-run exec-npm exec-run

help:
	@printf "Commandes disponibles:\n"
	@printf "  make docker-build    Build l'image Docker Compose\n"
	@printf "  make docker-up       Lance le client sur http://localhost:%s\n" "$(PORT)"
	@printf "  make docker-down     Stoppe les services Compose\n"
	@printf "  make docker-restart  Redemarre les services Compose\n"
	@printf "  make logs     Affiche les logs du client\n"
	@printf "  make shell    Ouvre un shell dans le conteneur client\n"
	@printf "  make docker-ps       Affiche l'etat des services Compose\n"
	@printf "  make clean    Supprime services, volumes et image locale\n"
	@printf "  make lint     Lance npm run lint dans un conteneur jetable\n"
	@printf "  make app-build Lance npm run build dans un conteneur jetable\n"
	@printf "  make npm NPM_ARGS=\"install\" Lance npm dans un conteneur jetable\n"
	@printf "  make npm-run SCRIPT=\"build\" Lance un script npm dans un conteneur jetable\n"
	@printf "  make exec-npm NPM_ARGS=\"install\" Lance npm dans le conteneur actif\n"
	@printf "  make exec-run SCRIPT=\"lint\" Lance un script npm dans le conteneur actif\n"
	@printf "  Astuce: PORT=3001 make docker-up pour changer le port publie\n"

docker-build:
	$(COMPOSE) build $(SERVICE)

docker-up:
	$(COMPOSE) up --build $(SERVICE)

docker-down:
	$(COMPOSE) down --remove-orphans

docker-restart: docker-down docker-up

logs:
	$(COMPOSE) logs -f $(SERVICE)

shell:
	$(COMPOSE) exec $(SERVICE) sh

docker-ps:
	$(COMPOSE) ps

clean: docker-down
	docker image rm -f $(IMAGE_NAME) >/dev/null 2>&1 || true
	docker volume rm -f $(APP_NAME)_node_modules $(APP_NAME)_next >/dev/null 2>&1 || true

lint:
	$(COMPOSE) run --rm $(SERVICE) sh -c "npm install && npm run lint"

app-build:
	$(COMPOSE) run --rm $(SERVICE) sh -c "npm install && npm run build"

npm:
	$(COMPOSE) run --rm $(SERVICE) npm $(NPM_ARGS)

npm-run:
	$(COMPOSE) run --rm $(SERVICE) sh -c "npm install && npm run $(SCRIPT)"

exec-npm:
	$(COMPOSE) exec $(SERVICE) npm $(NPM_ARGS)

exec-run:
	$(COMPOSE) exec $(SERVICE) npm run $(SCRIPT)
