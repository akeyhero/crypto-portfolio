FROM php:7.3.24-apache-stretch

COPY index.php firebase-config.php /var/www/html/
COPY tag /var/www/html/tag
COPY js /var/www/html/js
