FROM php:8.2-cli-alpine

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql intl opcache zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY router.php /usr/local/share/php-router.php
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint
# Supprime d'éventuels retours chariot Windows (CRLF) qui casseraient le script
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint && chmod +x /usr/local/bin/docker-entrypoint

# Serveur PHP intégré multi-processus (suffisant pour le développement)
ENV PHP_CLI_SERVER_WORKERS=4
EXPOSE 8000

ENTRYPOINT ["docker-entrypoint"]
# variables_order=EGPCS : les variables d'environnement du conteneur sont visibles par Symfony
CMD ["php", "-d", "variables_order=EGPCS", "-S", "0.0.0.0:8000", "-t", "public", "/usr/local/share/php-router.php"]
