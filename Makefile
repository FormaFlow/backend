# Variables
PHP_BIN = php
ARTISAN = $(PHP_BIN) artisan
VENDOR_BIN = ./vendor/bin

# Default target
all: lint test

# Testing
test:
	$(VENDOR_BIN)/phpunit -c phpunit.xml

# Linting and Static Analysis
lint: cs analyze format-check

cs:
	$(VENDOR_BIN)/phpcs --standard=phpcs.xml

cs-fix:
	$(VENDOR_BIN)/phpcbf --standard=phpcs.xml

analyze:
	$(VENDOR_BIN)/psalm --config=psalm.xml

format:
	npx prettier --write "**/*.php"

format-check:
	npx prettier --check "**/*.php"

# Laravel shortcuts
migrate:
	$(ARTISAN) migrate

fresh:
	$(ARTISAN) migrate:fresh

key:
	$(ARTISAN) key:generate

serve:
	$(ARTISAN) migrate
	$(ARTISAN) serve

deploy:
	ssh root@89.169.37.68 "cd /var/www/formaflow/backend && git fetch origin && git reset --hard origin/master && php artisan migrate --force"

.PHONY: test lint cs cs-fix analyze format format-check migrate fresh key serve deploy
