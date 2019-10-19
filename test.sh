#!/bin/bash
if [ ! -f phpunit.phar ]; then
  wget https://phar.phpunit.de/phpunit.phar
fi
export PDO_DRIVER_USERNAME=php-crud-api
export PDO_DRIVER_PASSWORD=php-crud-api
export PDO_DRIVER_DATABASE=php-crud-api
php phpunit.phar

