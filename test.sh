#!/bin/bash
if [ ! -f composer.phar ]; then
  wget https://getcomposer.org/composer.phar
fi
php composer.phar install
./vendor/bin/phpunit