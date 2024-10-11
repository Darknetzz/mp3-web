FROM debian
RUN apt-get -y update && apt-get -y install php8.2 php8.2-mbstring php8.2-curl apache2 libapache2-mod-php curl git
RUN sed -i 's/^file_uploads = .*/file_uploads = On/' /etc/php/8.2/apache2/php.ini && \
    sed -i 's/^upload_max_filesize = .*/upload_max_filesize = 50M/' /etc/php/8.2/apache2/php.ini && \
    sed -i 's/^post_max_size = .*/post_max_size = 50M/' /etc/php/8.2/apache2/php.ini || \
    { echo "file_uploads = On" >> /etc/php/8.2/apache2/php.ini; \
      echo "upload_max_filesize = 50M" >> /etc/php/8.2/apache2/php.ini; \
      echo "post_max_size = 50M" >> /etc/php/8.2/apache2/php.ini; }
RUN rm -rf /var/www/html && \
    git clone https://github.com/Darknetzz/mp3-web.git /var/www/html && \
    cd /var/www/html && \
    git pull && \
    curl -sS https://getcomposer.org/installer -o composer-setup.php && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    composer self-update && \
    composer install && \
    chown -R www-data:www-data /var/www/html && \
    a2enmod rewrite
EXPOSE 80 443
CMD ["apachectl", "-D", "FOREGROUND"]