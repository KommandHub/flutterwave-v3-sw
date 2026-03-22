.SILENT:
.PHONY: help up up-quick down build wait-for-container prepare test test-coverage cs cs-fix analyse shell logs status restart clean clean-all info

## Color
COLOR_RESET=\033[0m
COLOR_INFO=\033[32m
COLOR_COMMENT=\033[33m

# Configuration Variable
CONTAINER_NAME ?= flutterwave-v3-shopware
PLUGIN_NAME ?= KommandhubFlutterwaveV3SW
PLUGIN_REL_PATH := custom/plugins/$(PLUGIN_NAME)

# Determine the absolute path to the plugin directory
PLUGIN_ABS_PATH := $(shell cd $(dir $(lastword $(MAKEFILE_LIST))) && pwd)
PROJECT_ROOT := $(shell cd $(PLUGIN_ABS_PATH)/../../.. && pwd)

# Detect if we're running inside Docker container
IS_DOCKER := $(shell test -f /.dockerenv && echo true || echo false)
DOCKER_RUN := $(if $(filter true,$(IS_DOCKER)),,docker exec $(CONTAINER_NAME))

PLUGIN_DIR ?= $(PLUGIN_REL_PATH)
CONTAINER_PLUGIN_DIR := /var/www/html/$(PLUGIN_DIR)

help:
	@echo "Available commands:"
	@echo "  make up           - Start containers and install tools"
	@echo "  make up-quick     - Start containers only"
	@echo "  make down         - Stop containers"
	@echo "  make build        - Rebuild containers"
	@echo "  make prepare      - Prepare test environment"
	@echo "  make test         - Run PHPUnit tests"
	@echo "  make test-coverage- Run tests with coverage"
	@echo "  make cs           - Run code style checks"
	@echo "  make cs-fix       - Fix code style issues"
	@echo "  make analyse      - Run PHPStan analysis"
	@echo "  make shell        - Open shell in container"
	@echo "  make clean        - Clean build artifacts"
	@echo "  make clean-all    - Clean everything"
	@echo ""
	@echo "Configuration:"
	@echo "  CONTAINER_NAME=$(CONTAINER_NAME)"
	@echo "  PLUGIN_NAME=$(PLUGIN_NAME)"
	@echo "  PLUGIN_ABS_PATH=$(PLUGIN_ABS_PATH)"
	@echo "  Running in container: $(IS_DOCKER)"

up: up-quick wait-for-container
	@echo "✅ Container $(CONTAINER_NAME) is ready!"

up-quick:
	@echo "Starting Docker container $(CONTAINER_NAME)..."
	docker-compose up -d --build

wait-for-container:
	@echo "Waiting for container $(CONTAINER_NAME) to be ready..."
	@for i in 1 2 3 4 5; do \
		if docker ps --filter name=$(CONTAINER_NAME) --filter status=running | grep -q $(CONTAINER_NAME); then \
			echo "Container $(CONTAINER_NAME) is running!"; \
			break; \
		fi; \
		echo "Waiting... (attempt $$i)"; \
		sleep 5; \
		if [ $$i -eq 5 ]; then \
			echo "❌ Container $(CONTAINER_NAME) failed to start"; \
			exit 1; \
		fi; \
	done

down:
	docker-compose down

build:
	docker-compose build

prepare:
	@echo "Preparing test environment..."
	rm -rf vendor/*
	composer require --dev shopware/dev-tools --no-interaction --optimize-autoloader
	cd $(PLUGIN_DIR) && rm -rf vendor/* && composer install --no-interaction --optimize-autoloader
	rsync -rq $(PLUGIN_DIR)/tests/Setup/config config/
	@echo "✅ Test environment is ready!"

test:
	@echo "Running PHPUnit tests..."
	$(DOCKER_RUN) bash -c "cd $(CONTAINER_PLUGIN_DIR) && PROJECT_ROOT=/var/www/html ./vendor/bin/phpunit \
		--testdox \
		--configuration=. \
		--colors=always \
		${FILTER}"

test-coverage:
	@echo "Running tests with coverage..."
	$(DOCKER_RUN) bash -c "cd $(CONTAINER_PLUGIN_DIR) && PROJECT_ROOT=/var/www/html ./vendor/bin/phpunit --testdox \
		--coverage-html build/coverage \
		--coverage-text \
		--configuration=. \
		--coverage-clover build/logs/clover.xml \
		--coverage-cobertura build/logs/cobertura.xml \
		--colors=always \
		${FILTER}"

cs:
	@echo "Running code style checks..."
	$(DOCKER_RUN) bash -c "cd $(CONTAINER_PLUGIN_DIR) && PROJECT_ROOT=/var/www/html ./vendor/bin/php-cs-fixer fix --dry-run --diff"

cs-fix:
	@echo "Fixing code style issues..."
	$(DOCKER_RUN) bash -c "cd $(CONTAINER_PLUGIN_DIR) && PROJECT_ROOT=/var/www/html ./vendor/bin/php-cs-fixer fix"

analyse:
	@echo "Running PHPStan analysis..."
	$(DOCKER_RUN) bash -c "cd $(CONTAINER_PLUGIN_DIR) && PROJECT_ROOT=/var/www/html ./vendor/bin/phpstan analyse src -c phpstan.dist.neon --memory-limit=1G"

shell:
	@if [ "$(IS_DOCKER)" = "true" ]; then \
		echo "Already in container. Starting bash..."; \
		bash; \
	else \
		echo "Opening shell in container $(CONTAINER_NAME)..."; \
		docker exec -it $(CONTAINER_NAME) bash; \
	fi

logs:
	docker-compose logs -f

status:
	docker-compose ps

restart: down up

clean:
	@echo "Cleaning build artifacts..."
	@if [ "$(IS_DOCKER)" = "true" ]; then \
		rm -rf build/ vendor/* composer.lock; \
	else \
		docker exec $(CONTAINER_NAME) bash -c "cd $(CONTAINER_PLUGIN_DIR) && rm -rf build/ vendor/* composer.lock"; \
	fi

clean-all: down
	@echo "Cleaning everything..."
	docker-compose down -v
	rm -rf build/ vendor/* composer.lock

info:
	@echo "Current Configuration:"
	@echo "  Container Name: $(CONTAINER_NAME)"
	@echo "  Plugin Directory: $(PLUGIN_DIR)"
	@echo "  Running in container: $(IS_DOCKER)"
	@echo ""
	@echo "Running containers:"
	@docker ps --filter name=$(CONTAINER_NAME) 2>/dev/null || echo "Docker not available"