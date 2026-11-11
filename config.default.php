<?php

/* ────────────────────────────────────────────────────────────────────────── */
/*                                 Configuration                              */
/* ────────────────────────────────────────────────────────────────────────── */
# The defaults and definitions for every setting. Don't edit this file to change
# settings: use the settings dialog, or override values in config.local.php.
# Only "value" is read from config.local.php / config.php; everything else
# (name, description, type, options, attributes) always comes from here.

$config = [

    /* ─────────────────────────────── General ──────────────────────────────── */
    "site_title" => [
        "name"        => "Site title",
        "description" => "Shown in the header and the browser tab.",
        "value"       => "Music Player",
        "type"        => "string",
    ],
    "env" => [
        "name"        => "Environment",
        "description" => "Demo disables uploads, deletes and settings changes.",
        "value"       => "dev",
        "type"        => "selection",
        "options"     => [
            "dev"  => "Development",
            "prod" => "Production",
            "demo" => "Demo",
        ],
    ],
    "no_song_text" => [
        "name"        => "No song text",
        "description" => "Shown in the player when nothing is selected.",
        "value"       => "No song selected",
        "type"        => "string",
    ],

    /* ─────────────────────────────── Library ──────────────────────────────── */
    "audio_path" => [
        "name"        => "Music folder",
        "description" => "Folder containing your audio files, relative to the app.",
        "value"       => "music",
        "type"        => "string",
    ],
    "allowed_types" => [
        "name"        => "File types",
        "description" => "Extensions that are listed and accepted for upload.",
        "value"       => ["mp3", "ogg", "wav"],
        "type"        => "array",
    ],
    "include_file_extension" => [
        "name"        => "Show file extensions",
        "description" => "Show .mp3 and friends in song names.",
        "value"       => true,
        "type"        => "bool",
    ],
    "default_sort" => [
        "name"        => "Sort songs by",
        "description" => "How the song list is ordered when the page loads.",
        "value"       => "none",
        "type"        => "selection",
        "options"     => [
            "none"     => "Folder order",
            "name"     => "Name",
            "duration" => "Duration",
            "size"     => "Size",
            "date"     => "Date added",
        ],
    ],
    "default_sort_order" => [
        "name"        => "Sort direction",
        "description" => "Ascending or descending.",
        "value"       => "asc",
        "type"        => "selection",
        "options"     => [
            "asc"  => "Ascending",
            "desc" => "Descending",
        ],
    ],
    "default_table_cols" => [
        "name"        => "Default table columns",
        "description" => "Columns shown in the song table.",
        "value"       => ["name", "duration", "queue"],
        "type"        => "multiselect",
        "options"     => ["#", "filename", "size", "date", "name", "duration", "queue", "delete"],
    ],

    /* ─────────────────────────────── Playback ─────────────────────────────── */
    "default_volume" => [
        "name"        => "Default volume",
        "description" => "Starting volume. The last volume you used is remembered per browser.",
        "value"       => 0.5,
        "type"        => "range",
        "attributes"  => ["min" => 0, "max" => 1, "step" => 0.05, "format" => "percent"],
    ],
    "autoplay_next" => [
        "name"        => "Play the next song automatically",
        "description" => "Default for the autoplay button in the playbar (each browser remembers its own choice). When off, playback stops at the end of each song.",
        "value"       => true,
        "type"        => "bool",
    ],
    "seek_step" => [
        "name"        => "Seek step",
        "description" => "How far the ← and → keys and media seek buttons jump.",
        "value"       => 5,
        "type"        => "number",
        "attributes"  => ["min" => 1, "max" => 60, "step" => 1, "unit" => "sec"],
    ],
    "start_shuffle" => [
        "name"        => "Start with shuffle on",
        "description" => "Turn shuffle on when the page loads.",
        "value"       => false,
        "type"        => "bool",
    ],
    "start_loop" => [
        "name"        => "Start with loop on",
        "description" => "Turn loop on when the page loads.",
        "value"       => false,
        "type"        => "bool",
    ],
    "remember_position" => [
        "name"        => "Remember where I was",
        "description" => "Restore the song, position, loop and shuffle after a refresh.",
        "value"       => true,
        "type"        => "bool",
    ],
    "auto_scroll" => [
        "name"        => "Scroll to the playing song",
        "description" => "Keep the current song in view in the list.",
        "value"       => true,
        "type"        => "bool",
    ],
    "auto_scroll_on_end" => [
        "name"        => "Scroll when a song ends",
        "description" => "Also scroll to the next song when it starts because the last one finished.",
        "value"       => false,
        "type"        => "bool",
    ],
    "use_legacy_player" => [
        "name"        => "Legacy player",
        "description" => "Use the browser's built-in audio controls.",
        "value"       => false,
        "type"        => "bool",
    ],

    /* ────────────────────────────── Appearance ────────────────────────────── */
    "player_theme" => [
        "name"        => "Theme",
        "description" => "Auto follows your device's light or dark appearance.",
        "value"       => "dark",
        "type"        => "selection",
        "options"     => [
            "auto"     => "Follow system",
            "light"    => "Light",
            "arctic"   => "Arctic",
            "dark"     => "Dark",
            "oled"     => "OLED black",
            "midnight" => "Midnight",
            "forest"   => "Forest",
            "sunset"   => "Sunset",
            "custom"   => "Custom colors",
        ],
    ],
    "accent_color" => [
        "name"        => "Accent color",
        "description" => "Colors the song title, play button, waveform and playing row.",
        "value"       => "green",
        "type"        => "selection",
        "options"     => [
            "green"  => "Green",
            "blue"   => "Blue",
            "purple" => "Purple",
            "pink"   => "Pink",
            "orange" => "Orange",
            "red"    => "Red",
            "teal"   => "Teal",
            "yellow" => "Yellow",
            "custom" => "Custom",
        ],
    ],
    "custom_accent" => [
        "name"        => "Custom accent",
        "description" => "Used when the accent color is Custom.",
        "value"       => "#1db954",
        "type"        => "color",
    ],
    "custom_base" => [
        "name"        => "Custom theme base",
        "description" => "Whether your custom colors build on the light or dark look.",
        "value"       => "dark",
        "type"        => "selection",
        "options"     => [
            "dark"  => "Dark",
            "light" => "Light",
        ],
    ],
    "custom_background" => [
        "name"        => "Background color",
        "description" => "Page background for the Custom theme.",
        "value"       => "#121212",
        "type"        => "color",
    ],
    "custom_surface" => [
        "name"        => "Surface color",
        "description" => "Cards, the player and dialogs for the Custom theme.",
        "value"       => "#1e1e1e",
        "type"        => "color",
    ],
    "custom_text" => [
        "name"        => "Text color",
        "description" => "Main text color for the Custom theme.",
        "value"       => "#f0f0f0",
        "type"        => "color",
    ],
    "background_image" => [
        "name"        => "Background image",
        "description" => "Optional image URL (https://… or a path like img/bg.jpg).",
        "value"       => "",
        "type"        => "string",
    ],
    "background_dim" => [
        "name"        => "Background dimming",
        "description" => "Darkens the background image so text stays readable.",
        "value"       => 0.6,
        "type"        => "range",
        "attributes"  => ["min" => 0, "max" => 1, "step" => 0.05, "format" => "percent"],
    ],
    "background_blur" => [
        "name"        => "Background blur",
        "description" => "Softens the background image.",
        "value"       => 0,
        "type"        => "range",
        "attributes"  => ["min" => 0, "max" => 20, "step" => 1, "format" => "px"],
    ],
    "player_position" => [
        "name"        => "Player position",
        "description" => "Where the player bar sits.",
        "value"       => "bottom",
        "type"        => "selection",
        "options"     => [
            "bottom" => "Bottom",
            "top"    => "Top",
        ],
    ],
    "show_waveform" => [
        "name"        => "Waveform",
        "description" => "The animated bars next to the song title.",
        "value"       => true,
        "type"        => "bool",
    ],
    "show_upload" => [
        "name"        => "Upload box",
        "description" => "Show the drag-and-drop upload area.",
        "value"       => true,
        "type"        => "bool",
    ],
    "show_quick_buttons" => [
        "name"        => "Play first / Random buttons",
        "description" => "Show the quick-start buttons above the song list.",
        "value"       => true,
        "type"        => "bool",
    ],
    "compact_rows" => [
        "name"        => "Compact song list",
        "description" => "Tighter rows so more songs fit on screen.",
        "value"       => false,
        "type"        => "bool",
    ],
    "fluid-container" => [
        "name"        => "Full-width layout",
        "description" => "Use the whole window width.",
        "value"       => false,
        "type"        => "bool",
    ],
];

?>
