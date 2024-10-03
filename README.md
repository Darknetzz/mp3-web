# mp3-web
A super simple music player made in PHP/jQuery.

It can be useful if you have local music files you want to play, 
especially if you want to cast it to your Google speakers as Spotify does not allow this for local files.

## Default player
![image](https://github.com/user-attachments/assets/eb9c012c-45ec-4172-b79d-49ba0d41ad84)

## Legacy player
![image](https://github.com/user-attachments/assets/7a3589a4-34e5-4028-b525-9978008a71ba)


## Features
* Supports Windows media keys
* Download from playlist
* Delete from playlist
* Shuffle playlist
* Loop song
* Upload files directly from web UI

## How to use

### Dockerfile
You could build a docker container running `mp3-web` with the following dockerfile:
```dockerfile
FROM debian
RUN apt-get -y update && apt-get -y install apache2 libapache2-mod-php php git
RUN rm -rf /var/www/html
RUN git clone https://github.com/Darknetzz/mp3-web.git /var/www/html
EXPOSE 80 443
CMD ["apachectl", "-D", "FOREGROUND"]
```

To build a docker container with this image, simply run the following commands in the same folder as the dockerfile:
```bash
docker build -t mp3-web:main .
docker run -d -p 9096:80 mp3-web:main
```

### Manual
* Clone this repo or download the [latest release](https://github.com/Darknetzz/mp3-web/releases/latest) to your webserver with PHP.
* Run `composer install` from the directory
* Add some music in the subfolder `music` (or whatever you choose in `config.json`)
* Open the page in a browser and start listening!
