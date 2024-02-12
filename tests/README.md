##  Requirements
To run tests, install mentioned packages: 
- php-curl
- php-dom
- php-phpdbg
- php-mbstring
- php-ast

## Run a single integration test
`vendor/phpunit/phpunit/phpunit --configuration ./phpunit.xml --testsuite integration --filter testGetResources`