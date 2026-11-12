# PHP 8.2 + Apache image used to run the shop on Render (or any Docker host)
FROM php:8.2-apache

# MySQL driver for PDO (cURL, needed for PayPal, is already built in), production php.ini,
# and AllowOverride so the project's .htaccess files can block config/, includes/ and database.sql
RUN docker-php-ext-install pdo_mysql \
 && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# The site lives at the domain root, not in an /ecommerce folder
ENV BASE_URL=/

# Render tells the container which port to listen on through $PORT
CMD sed -i "s/^Listen 80$/Listen ${PORT:-80}/" /etc/apache2/ports.conf \
 && sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT:-80}>/" /etc/apache2/sites-available/000-default.conf \
 && exec apache2-foreground
