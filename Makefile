COMPOSE_FILE ?= docker-compose.yml
COMPOSE ?= docker compose -f $(COMPOSE_FILE)

CLIENT_SERVICE ?= client-front
BACKEND_SERVICE ?= nginx
PHP_SERVICE ?= php
HOST_UID ?= $(shell id -u)
HOST_GID ?= $(shell id -g)
COMPOSER_RUN ?= $(COMPOSE) run --rm --user $(HOST_UID):$(HOST_GID) -e COMPOSER_HOME=/tmp/composer $(PHP_SERVICE)

PORT ?= 3000
BACKEND_PORT ?= 8080
BACKEND_HTTPS_PORT ?= 8443
MAILPIT_UI_PORT ?= 8025

NPM_ARGS ?= --version
SCRIPT ?= lint
COMPOSER_ARGS ?=
CONSOLE_ARGS ?= --version
SYMFONY_ARGS ?= --version
BACKEND_SCRIPT ?= stan

.PHONY: help docker docker-build docker-up docker-down docker-restart docker-logs docker-ps docker-health clean \
	docker-client-up docker-client-down docker-client-restart docker-client-logs client-shell \
	docker-back-up docker-back-down docker-back-restart docker-back-logs backend-shell backend-health \
	lint app-build deploy-client deploy-backend setup-backend-local-host npm npm-run exec-npm exec-run \
	composer composer-install composer-update composer-run backend-console backend-migrate \
	backend-cs-check backend-cs-fix backend-stan symfony symfony-assets

help:
	@printf "Commandes disponibles:\n"
	@printf "  make docker                Alias de make docker-up\n"
	@printf "  make docker-build          Build toutes les images Compose\n"
	@printf "  make docker-up             Lance toute la stack: client-front, nginx, PHP, MySQL, Mailpit\n"
	@printf "  make docker-down           Stoppe toute la stack Compose\n"
	@printf "  make docker-restart        Redemarre toute la stack Compose\n"
	@printf "  make docker-logs           Affiche les logs de toute la stack\n"
	@printf "  make docker-ps             Affiche l'etat des services Compose\n"
	@printf "  make docker-health         Affiche l'etat de sante des services\n"
	@printf "  make clean                 Supprime services, volumes et images locales\n"
	@printf "  make docker-client-up      Lance seulement le client sur http://localhost:%s\n" "$(PORT)"
	@printf "  make docker-client-down    Stoppe seulement le client\n"
	@printf "  make docker-client-restart Redemarre seulement le client\n"
	@printf "  make docker-client-logs    Affiche les logs du client\n"
	@printf "  make client-shell          Ouvre un shell dans le conteneur client\n"
	@printf "  make docker-back-up        Lance le backend sur http://localhost:%s et https://admin.jardin-sonore.local:%s\n" "$(BACKEND_PORT)" "$(BACKEND_HTTPS_PORT)"
	@printf "  make docker-back-down      Stoppe nginx, PHP, MySQL et Mailpit\n"
	@printf "  make docker-back-restart   Redemarre nginx, PHP, MySQL et Mailpit\n"
	@printf "  make docker-back-logs      Affiche les logs nginx, PHP, MySQL et Mailpit\n"
	@printf "  make backend-shell         Ouvre un shell dans le conteneur PHP\n"
	@printf "  make backend-health        Affiche l'etat de sante backend\n"
	@printf "  make composer              Lance composer COMPOSER_ARGS=\"...\" dans PHP\n"
	@printf "  make composer-install      Lance composer install dans PHP\n"
	@printf "  make composer-update       Lance composer update COMPOSER_ARGS=\"...\" dans PHP\n"
	@printf "  make composer-run          Lance composer run BACKEND_SCRIPT=\"...\" dans PHP\n"
	@printf "  make backend-console       Lance php bin/console CONSOLE_ARGS=\"...\"\n"
	@printf "  make backend-migrate       Lance doctrine:migrations:migrate\n"
	@printf "  make backend-cs-check      Verifie le style PHP backend avec PHP-CS-Fixer\n"
	@printf "  make backend-cs-fix        Corrige le style PHP backend avec PHP-CS-Fixer\n"
	@printf "  make backend-stan          Lance PHPStan sur le backend\n"
	@printf "  make symfony               Lance symfony SYMFONY_ARGS=\"...\" si le CLI Symfony est installe dans PHP\n"
	@printf "  make symfony-assets        Lance asset-map:compile\n"
	@printf "  make lint                  Lance npm run lint dans un conteneur client jetable\n"
	@printf "  make app-build             Lance npm run build dans un conteneur client jetable\n"
	@printf "  make deploy-backend        Deploie le backend Symfony sur cPanel via SSH/rsync\n"
	@printf "  make setup-backend-local-host Ajoute admin.jardin-sonore.local et genere un certificat mkcert si disponible\n"
	@printf "  make npm NPM_ARGS=\"...\"   Lance npm dans un conteneur client jetable\n"
	@printf "  make npm-run SCRIPT=\"...\" Lance npm run SCRIPT dans un conteneur client jetable\n"
	@printf "  Front: localhost:%s | Backend: localhost:%s | Mailpit: localhost:%s\n" "$(PORT)" "$(BACKEND_PORT)" "$(MAILPIT_UI_PORT)"

docker: docker-up

docker-build:
	$(COMPOSE) build

docker-up:
	$(COMPOSE) up --build

docker-down:
	$(COMPOSE) down --remove-orphans

docker-restart: docker-down docker-up

docker-logs:
	$(COMPOSE) logs -f

docker-ps:
	$(COMPOSE) ps

docker-health:
	$(COMPOSE) ps --format "table {{.Name}}\t{{.Service}}\t{{.Status}}"

docker-client-up:
	$(COMPOSE) up --build $(CLIENT_SERVICE)

docker-client-down:
	$(COMPOSE) stop $(CLIENT_SERVICE)

docker-client-restart: docker-client-down docker-client-up

docker-client-logs:
	$(COMPOSE) logs -f $(CLIENT_SERVICE)

client-shell:
	$(COMPOSE) exec $(CLIENT_SERVICE) sh

docker-back-up:
	$(COMPOSE) up --build $(BACKEND_SERVICE)

docker-back-down:
	$(COMPOSE) stop $(BACKEND_SERVICE) $(PHP_SERVICE) mysql mailpit

docker-back-restart: docker-back-down docker-back-up

docker-back-logs:
	$(COMPOSE) logs -f $(BACKEND_SERVICE) $(PHP_SERVICE) mysql mailpit

backend-shell:
	$(COMPOSE) exec $(PHP_SERVICE) sh

backend-health:
	$(COMPOSE) ps $(BACKEND_SERVICE) $(PHP_SERVICE) mysql mailpit

clean: docker-down
	docker image rm -f jardin-sonore-client:dev jardin-sonore-backend-php:dev >/dev/null 2>&1 || true
	docker volume rm -f jardin-sonore-client_node_modules jardin-sonore-client_next jardin-sonore-mysql_data >/dev/null 2>&1 || true

lint:
	$(COMPOSE) run --rm $(CLIENT_SERVICE) sh -c "npm install && npm run lint"

app-build:
	$(COMPOSE) run --rm $(CLIENT_SERVICE) sh -c "npm install && npm run build"

deploy-client:
	./scripts/deploy-client.sh

deploy-backend:
	./scripts/deploy-backend.sh

setup-backend-local-host:
	./scripts/setup-backend-local-host.sh

npm:
	$(COMPOSE) run --rm $(CLIENT_SERVICE) npm $(NPM_ARGS)

npm-run:
	$(COMPOSE) run --rm $(CLIENT_SERVICE) sh -c "npm install && npm run $(SCRIPT)"

exec-npm:
	$(COMPOSE) exec $(CLIENT_SERVICE) npm $(NPM_ARGS)

exec-run:
	$(COMPOSE) exec $(CLIENT_SERVICE) npm run $(SCRIPT)

composer:
	$(COMPOSER_RUN) composer $(if $(COMPOSER_ARGS),$(COMPOSER_ARGS),--version)

composer-install:
	$(COMPOSER_RUN) composer install $(COMPOSER_ARGS)

composer-update:
	$(COMPOSER_RUN) composer update $(COMPOSER_ARGS)

composer-run:
	$(COMPOSER_RUN) composer run $(BACKEND_SCRIPT)

backend-console:
	$(COMPOSE) exec $(PHP_SERVICE) php bin/console $(CONSOLE_ARGS)

backend-migrate:
	$(COMPOSE) exec $(PHP_SERVICE) php bin/console doctrine:migrations:migrate

backend-cs-check:
	$(COMPOSER_RUN) composer run cs-check

backend-cs-fix:
	$(COMPOSER_RUN) composer run cs-fix

backend-stan:
	$(COMPOSER_RUN) composer run stan

symfony:
	$(COMPOSE) exec $(PHP_SERVICE) symfony $(SYMFONY_ARGS)

symfony-assets:
	$(COMPOSE) exec $(PHP_SERVICE) php bin/console asset-map:compile
