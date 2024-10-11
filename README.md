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

## TBA
* Favorites
* Queue
* Support multiple playlists
* Browser refresh persistency
* Session sharing

## How to use

### Dockerfile
You could build a docker container running `mp3-web` using the [dockerfile](https://raw.githubusercontent.com/Darknetzz/mp3-web/refs/heads/main/Dockerfile).
```bash
# Get the Dockerfile
curl -O https://raw.githubusercontent.com/Darknetzz/mp3-web/refs/heads/main/Dockerfile

# Build the container
docker build -t mp3-web:main .

# Run the container
docker run -d -p 9096:80 mp3-web:main
```

### Manual
* Clone this repo or download the [latest release](https://github.com/Darknetzz/mp3-web/releases/latest) to your webserver with PHP.
* Run `composer install` from the directory (optional, but required to display durations)
* Add some music in the subfolder `music` (or whatever you choose in `config.json`)
* Open the page in a browser and start listening!

### Troubleshooting

#### The directory `PATH` is not writable.
Run the following commands, replacing `<PATH>` with the music directory:
```bash
chown -R www-data:www-data <PATH>
chmod -R 775 <PATH>
```

If your music folder is in a remote directory and the ownership can't be changed, you might have to replace `775` with `777`.
