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
COMPOSER_ARGS ?=

.PHONY: help docker docker-build docker-up docker-down docker-restart clean \
	print-urls lint app-build deploy-client deploy-backend setup-backend-local-host npm exec-npm \
	composer composer-install composer-update backend-migrate \
	backend-lint backend-cs-check backend-cs-fix backend-stan symfony-assets

help:
	@printf "Commandes disponibles:\n"
	@printf "  make docker                Alias de make docker-up\n"
	@printf "  make docker-build          Build toutes les images Compose\n"
	@printf "  make docker-up             Lance toute la stack en arriere-plan et affiche les URLs\n"
	@printf "  make docker-down           Stoppe toute la stack Compose\n"
	@printf "  make docker-restart        Redemarre toute la stack Compose\n"
	@printf "  make clean                 Supprime services, volumes et images locales\n"
	@printf "  make composer              Lance composer COMPOSER_ARGS=\"...\" dans PHP\n"
	@printf "  make composer-install      Lance composer install dans PHP\n"
	@printf "  make composer-update       Lance composer update COMPOSER_ARGS=\"...\" dans PHP\n"
	@printf "  make backend-migrate       Lance doctrine:migrations:migrate\n"
	@printf "  make backend-lint          Lance PHP-CS-Fixer en check puis PHPStan\n"
	@printf "  make backend-cs-check      Verifie le style PHP backend avec PHP-CS-Fixer\n"
	@printf "  make backend-cs-fix        Corrige le style PHP backend avec PHP-CS-Fixer\n"
	@printf "  make backend-stan          Lance PHPStan sur le backend\n"
	@printf "  make symfony-assets        Lance asset-map:compile\n"
	@printf "  make lint                  Lance npm run lint dans un conteneur client jetable\n"
	@printf "  make app-build             Lance npm run build dans un conteneur client jetable\n"
	@printf "  make deploy-backend        Deploie le backend Symfony sur cPanel via SSH/rsync\n"
	@printf "  make setup-backend-local-host Ajoute admin.jardin-sonore.local et genere un certificat mkcert si disponible\n"
	@printf "  make npm NPM_ARGS=\"...\"   Lance npm dans un conteneur client jetable\n"
	@printf "  make print-urls            Affiche les URLs locales utiles\n"
	@printf "  Front: localhost:%s | Backend: localhost:%s | Mailpit: localhost:%s\n" "$(PORT)" "$(BACKEND_PORT)" "$(MAILPIT_UI_PORT)"

docker: docker-up

docker-build:
	$(COMPOSE) build

docker-up:
	$(COMPOSE) up --build --wait
	$(MAKE) print-urls

docker-down:
	$(COMPOSE) down --remove-orphans || true

docker-restart: docker-down docker-up

print-urls:
	@printf "\nServices disponibles:\n"
	@printf "  Front      http://localhost:%s\n" "$(PORT)"
	@printf "  Backend    http://localhost:%s\n" "$(BACKEND_PORT)"
	@printf "  Admin SSL  https://admin.jardin-sonore.local:%s\n" "$(BACKEND_HTTPS_PORT)"
	@printf "  Mailpit    http://localhost:%s\n\n" "$(MAILPIT_UI_PORT)"

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

exec-npm:
	$(COMPOSE) exec $(CLIENT_SERVICE) npm $(NPM_ARGS)

composer:
	$(COMPOSER_RUN) composer $(if $(COMPOSER_ARGS),$(COMPOSER_ARGS),--version)

composer-install:
	$(COMPOSER_RUN) composer install $(COMPOSER_ARGS)

composer-update:
	$(COMPOSER_RUN) composer update $(COMPOSER_ARGS)

backend-migrate:
	$(COMPOSE) exec $(PHP_SERVICE) php bin/console doctrine:migrations:migrate

backend-lint: backend-cs-check backend-stan

backend-cs-check:
	$(COMPOSER_RUN) composer run cs-check

backend-cs-fix:
	$(COMPOSER_RUN) composer run cs-fix

backend-stan:
	$(COMPOSER_RUN) composer run stan

symfony-assets:
	$(COMPOSE) exec $(PHP_SERVICE) php bin/console asset-map:compile
