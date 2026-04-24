FROM php:8.2-apache

# Copy your website files to Apache directory
COPY . /var/www/html/

# Enable Apache mod_rewrite (optional but useful)
RUN a2enmod rewrite

# Expose port
EXPOSE 80
