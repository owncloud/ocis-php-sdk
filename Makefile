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
	vendor/bin/phpunit --configuration ./phpunit.xml --testsuite unit

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
	vendor/bin/phpstan analyse --memory-limit=4G --configuration=./phpstan.neon --no-progress --level=5 appinfo lib

.PHONY: clean
clean:
	rm -f composer.lock .php-cs-fixer.cache .phpunit.result.cache
	rm -Rf vendor

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
