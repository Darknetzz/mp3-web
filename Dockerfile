FROM debian
RUN apt-get -y update && apt-get -y install apache2 libapache2-mod-php php git
RUN rm -rf /var/www/html
RUN git clone https://github.com/Darknetzz/mp3-web.git /var/www/html
EXPOSE 80 443
CMD ["apachectl", "-D", "FOREGROUND"]