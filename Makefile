APP_NAME ?= jardin-sonore-client
IMAGE_NAME ?= $(APP_NAME):dev
CONTAINER_NAME ?= $(APP_NAME)
DOCKERFILE ?= docker/Dockerfile
PORT ?= 3000
NPM_ARGS ?= --version
SCRIPT ?= lint

.PHONY: help build up down restart logs shell ps clean lint app-build npm npm-run exec-npm exec-run

help:
	@printf "Commandes disponibles:\n"
	@printf "  make build    Build l'image Docker\n"
	@printf "  make up       Lance le conteneur sur http://localhost:%s\n" "$(PORT)"
	@printf "  make down     Stoppe et supprime le conteneur\n"
	@printf "  make restart  Redemarre le conteneur\n"
	@printf "  make logs     Affiche les logs du conteneur\n"
	@printf "  make shell    Ouvre un shell dans le conteneur\n"
	@printf "  make ps       Affiche l'etat du conteneur\n"
	@printf "  make clean    Supprime le conteneur et l'image\n"
	@printf "  make lint     Lance npm run lint dans Docker\n"
	@printf "  make app-build Lance npm run build dans Docker\n"
	@printf "  make npm NPM_ARGS=\"install\" Lance npm dans une image temporaire\n"
	@printf "  make npm-run SCRIPT=\"build\" Lance un script npm dans une image temporaire\n"
	@printf "  make exec-npm NPM_ARGS=\"install\" Lance npm dans le conteneur actif\n"
	@printf "  make exec-run SCRIPT=\"lint\" Lance un script npm dans le conteneur actif\n"

build:
	docker build -f $(DOCKERFILE) -t $(IMAGE_NAME) .

up: build
	docker rm -f $(CONTAINER_NAME) >/dev/null 2>&1 || true
	docker run --name $(CONTAINER_NAME) -p $(PORT):3000 $(IMAGE_NAME)

down:
	docker rm -f $(CONTAINER_NAME) >/dev/null 2>&1 || true

restart: down up

logs:
	docker logs -f $(CONTAINER_NAME)

shell:
	docker exec -it $(CONTAINER_NAME) sh

ps:
	docker ps -a --filter name=$(CONTAINER_NAME)

clean: down
	docker rmi -f $(IMAGE_NAME) >/dev/null 2>&1 || true

lint: build
	docker run --rm $(IMAGE_NAME) npm run lint

app-build: build
	docker run --rm $(IMAGE_NAME) npm run build

npm: build
	docker run --rm -it $(IMAGE_NAME) npm $(NPM_ARGS)

npm-run: build
	docker run --rm -it $(IMAGE_NAME) npm run $(SCRIPT)

exec-npm:
	docker exec -it $(CONTAINER_NAME) npm $(NPM_ARGS)

exec-run:
	docker exec -it $(CONTAINER_NAME) npm run $(SCRIPT)
