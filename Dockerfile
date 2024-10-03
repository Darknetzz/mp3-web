FROM debian
RUN apt-get -y update && apt-get -y install php php-mbstring php-curl apache2 libapache2-mod-php curl git
RUN rm -rf /var/www/html && \
    git clone https://github.com/Darknetzz/mp3-web.git /var/www/html && \
    cd /var/www/html && \
    curl -sS https://getcomposer.org/installer -o composer-setup.php && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    composer self-update && \    
    composer install

EXPOSE 80 443
CMD ["apachectl", "-D", "FOREGROUND"]