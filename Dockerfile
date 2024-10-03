FROM debian
RUN apt-get -y update && apt-get -y install apache2 libapache2-mod-php curl php php-curl git
RUN rm -rf /var/www/html
RUN git clone https://github.com/Darknetzz/mp3-web.git /var/www/html
RUN cd /var/www/html && git pull && curl -sS https://getcomposer.org/installer -o composer-setup.php && php composer-setup.php --install-dir=/usr/local/bin --filename=composer && composer self-update && composer install
EXPOSE 80 443
CMD ["apachectl", "-D", "FOREGROUND"]