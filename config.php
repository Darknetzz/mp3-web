<?php

/* ────────────────────────────────────────────────────────────────────────── */
/*                                 Configuration                              */
/* ────────────────────────────────────────────────────────────────────────── */


# The title of the site
$config["site_title"]            = "Music Player"; 

# Allowed file types for the music player
$config["allowed_types"]          = ["mp3"]; 

# Path to the directory containing audio files
$config["audio_path"]             = "music"; 

# Whether to use the legacy player or not
$config["use_legacy_player"]      = false; 

# Whether to include the file extension in the display
$config["include_file_extension"] = true; 

# Default volume level for the player
$config["default_volume"]         = 0.5; 

# Text to display when no song is selected
$config["no_song_text"]           = "No song selected"; 

?>