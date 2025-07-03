FROM php:8.1-apache

# Install MySQL extensions
RUN docker-php-ext-install mysqli pdo_mysql

# Copy Apache configuration
COPY apache2.conf /etc/apache2/apache2.conf

# Copy PHP configuration
COPY php.ini /usr/local/etc/php/

# Copy application files
COPY src/ /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# Enable Apache modules
RUN a2enmod rewrite

EXPOSE 80
