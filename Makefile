# docker compose command, defaults to v3
# for v2: set this as docker-compose using the environment variable
DCO:=docker compose
PHPUNIT=php -d memory_limit=4096M -d zend.enable_gc=0 -d xdebug.mode=coverage "vendor/bin/phpunit"
run-with-cleanup = $(1) && $(2) || (ret=$$?; $(2) && exit $$ret)

#
# Catch-all rules
#
.PHONY: all
all: $(composer)

# Installs and updates the composer dependencies.
.PHONY: composer
composer:
	composer install
	composer update

##------------
## Tests
##------------

.PHONY: test-php-unit
test-php-unit:             ## Run php unit tests
test-php-unit: vendor/bin/phpunit
	$(PHPUNIT) --configuration ./phpunit.xml --testsuite unit

.PHONY: test-php-integration
test-php-integration:             ## Run php integration tests
test-php-integration: run-ocis-with-keycloak
	composer install
	$(call run-with-cleanup, $(PHPUNIT) --configuration ./phpunit.xml --testsuite integration, $(MAKE) docker-clean)

.PHONY: test-php-integration-ci
test-php-integration-ci:            ## Run php integration tests in CI
test-php-integration-ci: vendor/bin/phpunit
	$(PHPUNIT) --configuration ./phpunit.xml --testsuite integration

.PHONY: test-php-style
test-php-style:            ## Run php-cs-fixer and check code-style
test-php-style: vendor/bin/php-cs-fixer
	vendor/bin/php-cs-fixer fix -v --diff --allow-risky yes --dry-run

.PHONY: test-php-style-fix
test-php-style-fix:        ## Run php-cs-fixer and fix code-style
test-php-style-fix: vendor/bin/php-cs-fixer
	vendor/bin/php-cs-fixer fix -v --diff --allow-risky yes

.PHONY: test-php-phan
test-php-phan:             ## Run phan
test-php-phan: vendor/bin/phan
	vendor/bin/phan --config-file .phan/config.php --require-config-exists

.PHONY: test-php-phpstan
test-php-phpstan:          ## Run phpstan
test-php-phpstan: vendor/bin/phpstan
	vendor/bin/phpstan analyse --memory-limit=4G --configuration=./phpstan.neon --no-progress src tests

.PHONY: run-ocis-with-keycloak
run-ocis-with-keycloak:
	cd tests/integration && $(DCO) up -d --wait

.PHONY: docker-clean
docker-clean:
	cd tests/integration && $(DCO) down -v --remove-orphans

.PHONY: clean
clean:
	rm -f composer.lock .php-cs-fixer.cache .phpunit.result.cache
	rm -Rf vendor
	cd tests/integration && $(DCO) down -v --remove-orphans --rmi local

#
# Dependency management
#--------------------------------------

composer.lock: composer.json
	@echo composer.lock is not up to date.

vendor: composer.lock
	composer install --no-dev

vendor/bin/phpunit: composer.lock
	composer install

vendor/bin/php-cs-fixer: composer.lock
	composer install

vendor/bin/phan: composer.lock
	composer install

vendor/bin/phpstan: composer.lock
	composer install
