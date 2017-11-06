#!/usr/bin/env /bin/bash

rm -rf vendor
rm -rf composer.lock
composer install

# Run for lumen
rm -rf vendor
rm -rf composer.lock
composer require --dev laravel/lumen-framework --no-interaction
vendor/bin/phpunit --coverage-clover=coverage.xml --coverage-text
composer remove --dev laravel/lumen-framework --no-interaction

# Run for laravel
rm -rf vendor
rm -rf composer.lock
composer require --dev laravel/framework --no-interaction
vendor/bin/phpunit --coverage-clover=coverage.xml --coverage-text
composer remove --dev laravel/framework --no-interaction
if [[ -f "codeception.yml" ]]; then
    rm codeception.yml
fi
