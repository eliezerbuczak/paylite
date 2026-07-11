UID := $(shell id -u)
GID := $(shell id -g)

.PHONY: help install build up up-build down stop restart bash logs ps php composer test coverage cs cs-check stan md quality clean

help:
	@echo "Available commands:"
	@echo "  make install       - First-time / post-clone setup (composer install)"
	@echo "  make build         - (Re)build the app image"
	@echo "  make up            - Start all services (postgres, redis, rabbitmq, app)"
	@echo "  make up-build      - Force rebuild the app image and start"
	@echo "  make down          - Stop and remove containers"
	@echo "  make stop          - Stop containers without removing"
	@echo "  make restart       - Restart the app service"
	@echo "  make bash          - Open a shell inside the app container"
	@echo "  make logs          - Tail app logs (Ctrl+C to leave)"
	@echo "  make ps            - List running services"
	@echo "  make php cmd=\"...\" - Run php inside the container"
	@echo "  make composer cmd=\"...\" - Run composer inside the container"
	@echo "  make test          - Run PHPUnit"
	@echo "  make coverage      - Run PHPUnit with coverage report"
	@echo "  make cs            - Run php-cs-fixer (fix)"
	@echo "  make cs-check      - Run php-cs-fixer (dry-run)"
	@echo "  make stan          - Run PHPStan"
	@echo "  make md            - Run PHPMD"
	@echo "  make quality       - Run cs-check + phpstan + phpmd + tests"
	@echo "  make clean         - Stop and remove containers + volumes"

install:
	@test -f .env || cp .env.example .env
	docker compose build app
	docker compose run --rm --no-deps --entrypoint composer app install

build:
	docker compose build

up:
	docker compose up -d

up-build:
	docker compose up -d --build

down:
	docker compose down

stop:
	docker compose stop

restart:
	docker compose restart app

bash:
	docker compose exec app sh

logs:
	docker compose logs -f app

ps:
	docker compose ps

php:
	docker compose exec app php $(cmd)

composer:
	docker compose exec app composer $(cmd)

test:
	docker compose exec app composer test

coverage:
	docker compose exec app composer test:coverage

cs:
	docker compose exec app composer cs-fix

cs-check:
	docker compose exec app composer cs-check

stan:
	docker compose exec app composer analyse

md:
	docker compose exec app composer md

quality:
	docker compose exec app composer quality

clean:
	docker compose down -v
