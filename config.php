<?php

/* ────────────────────────────────────────────────────────────────────────── */
/*                                 Configuration                              */
/* ────────────────────────────────────────────────────────────────────────── */

$config = [
    "env" => [
        "name"        => "Environment",
        "description" => "The environment the app is running in",
        "value"       => "dev",
        "type"        => "selection",
        "options"     => [
            "dev"  => "Development",
            "prod" => "Production",
            "demo" => "Demo",
        ]
    ],
    "player_theme" => [
        "name"        => "Player Theme",
        "description" => "Theme for the audio player",
        "value"       => "dark",
        "type"        => "selection",
        "options"     => [
            "dark"    => "Dark",
            "light"   => "Light",
        ],
    ],
    "default_table_cols" => [
        "name"        => "Default Table Columns",
        "description" => "Default columns to display in the table",
        "value"       => ["name", "duration", "queue"],
        "type"        => "multiselect",
        "options"     => ["#", "filename", "size", "date", "name", "duration", "queue", "delete"],
    ],
    "site_title" => [
        "name"        => "Site Title",
        "description" => "The title of the site",
        "value"       => "Music Player",
        "type"        => "string",
    ],
    "allowed_types" => [
        "name"        => "Allowed Types",
        "description" => "Allowed file types for the music player",
        "value"       => ["mp3", "ogg", "wav"],
        "type"        => "array",
    ],
    "audio_path" => [
        "name"        => "Audio Path",
        "description" => "Path to the directory containing audio files",
        "value"       => "music",
        "type"        => "string",
    ],
    "use_legacy_player" => [
        "name"        => "Use Legacy Player",
        "description" => "Whether to use the legacy player or not",
        "value"       => false,
        "type"        => "bool",
    ],
    "include_file_extension" => [
        "name"        => "Include File Extension",
        "description" => "Whether to include the file extension in the display",
        "value"       => true,
        "type"        => "bool",
    ],
    "default_volume" => [
        "name"        => "Default Volume",
        "description" => "Default volume level for the player",
        "value"       => 0.5,
        "type"        => "range",
        "attributes"  => [
            "min"  => 0,
            "max"  => 1,
            "step" => 0.05,
        ],
    ],
    "no_song_text" => [
        "name"        => "No Song Text",
        "description" => "Text to display when no song is selected",
        "value"       => "No song selected",
        "type"        => "string",
    ],
    "auto_scroll" => [
        "name"        => "Auto Scroll",
        "description" => "Auto scroll to the currently playing song",
        "value"       => true,
        "type"        => "bool",
    ],
    "fluid-container" => [
        "name"        => "Fluid Container",
        "description" => "Use a fluid container for the layout",
        "value"       => false,
        "type"        => "bool",
    ],
    "player_opacity" => [
        "name"        => "Player Opacity",
        "description" => "Opacity level for the player",
        "value"       => 0.95,
        "type"        => "range",
        "attributes"  => [
            "min"  => 0,
            "max"  => 1,
            "step" => 0.05,
        ],
    ],
];

?>