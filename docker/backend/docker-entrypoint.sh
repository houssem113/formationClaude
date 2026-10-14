#!/bin/sh
# Prépare le backend au démarrage du conteneur ; chaque étape est sans effet si elle est déjà faite.
set -e

# Secrets locaux (jamais versionnés) : générés au premier lancement
if [ ! -f .env.local ]; then
    echo "Génération de .env.local (APP_SECRET, JWT_PASSPHRASE)"
    {
        echo "APP_SECRET=$(php -r 'echo bin2hex(random_bytes(32));')"
        echo "JWT_PASSPHRASE=$(php -r 'echo bin2hex(random_bytes(32));')"
    } > .env.local
fi
if [ ! -f .env.test.local ]; then
    grep '^JWT_PASSPHRASE=' .env.local > .env.test.local
fi

composer install --no-interaction --prefer-dist --no-progress

php bin/console lexik:jwt:generate-keypair --skip-if-exists
php bin/console doctrine:database:create --if-not-exists --no-interaction
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

exec "$@"
