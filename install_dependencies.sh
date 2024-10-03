#!/bin/sh
cd "$(dirname "$0")";
git pull;
curl -sS https://getcomposer.org/installer -o composer-setup.php && php composer-setup.php --install-dir=/usr/local/bin --filename=composer;
composer self-update;
composer install;