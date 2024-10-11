# mp3-web
A cool local music player made in PHP/jQuery.

It can be useful if you have local music files you want to play, 
especially if you want to cast it to your Google speakers as Spotify does not allow this for local files.

## Default (modern) player
![image](https://github.com/user-attachments/assets/c09b9566-930b-4e0e-9d4e-759124f956b6)

## Legacy player
![image](https://github.com/user-attachments/assets/7a3589a4-34e5-4028-b525-9978008a71ba)


## Features
* Supports Windows media keys
* Download from playlist
* Delete from playlist
* Shuffle playlist
* Loop song
* Upload files directly from web UI
* Change settings from GUI
* Queue

## TBA
* Favorites
* Support multiple playlists
* Browser refresh persistency
* Session sharing

## Getting started

### Dockerfile (recommended)
You could build a docker container running `mp3-web` using the [dockerfile](https://raw.githubusercontent.com/Darknetzz/mp3-web/refs/heads/main/Dockerfile).
```bash
# Get the Dockerfile
curl -O https://raw.githubusercontent.com/Darknetzz/mp3-web/refs/heads/main/Dockerfile

# Build the container
docker build -t mp3-web:main .

# Run the container (replace 9096 with any port you want)
docker run -d -p 9096:80 mp3-web:main
```

### Manual
* Clone this repo or download the [latest release](https://github.com/Darknetzz/mp3-web/releases/latest) to your webserver with PHP.
* Run `composer install` from the directory (optional, but required to display durations)
* Add some music in the subfolder `music` (or whatever you choose in `config.php`)
* Open the page in a browser and start listening!

### Troubleshooting

#### Unable to upload/delete music
Usually the main reason for this is permissions. You might see error messages similar to this:
* The directory `<PATH>` is not writable.
Run the following commands, replacing `<PATH>` with the mp3-web directory:
```bash
# Example: chown -R www-data:www-data /var/www/html/mp3-web
chown -R www-data:www-data <PATH>

# Example: chmod -R 775 /var/www/html/mp3-web
chmod -R 775 <PATH>
```

If your music folder is in a remote directory and the ownership can't be changed, you might have to replace `775` with `777`.
