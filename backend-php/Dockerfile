# PHP-FPM/Apache image — replaces the Python side's gunicorn/WSGI concept
# entirely (PHP under mod_php needs no separate application server).
FROM php:8.3-apache

RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite

COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY . /var/www/html

# vendor/ is intentionally NOT installed here — Composer is scoped to the
# one-off scripts/import_students.php (PhpSpreadsheet), run locally on the
# host, not inside the API container. See composer.json.

EXPOSE 80
