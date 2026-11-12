![mp3-web](assets/branding/banner.svg)

# mp3-web

A lightweight, self-hosted music player for local audio files, written in PHP with a jQuery/Bootstrap frontend.

It's handy when you have a folder of local music you want to play from any browser, and especially when you want to
cast it to Google/Nest speakers (Spotify won't cast local files).

See [CHANGELOG.md](CHANGELOG.md) for what changed in each release.

## Screenshots

Taken with a generated demo library.

| | |
| --- | --- |
| ![Song list with the playbar](assets/screenshots/library.png) | ![Grid view](assets/screenshots/grid.png) |
| Song list, playlists sidebar and playbar | Grid view |
| ![Queue panel](assets/screenshots/queue.png) | ![Listen together](assets/screenshots/session.png) |
| Queue panel | Listening session |
| ![Settings](assets/screenshots/settings.png) | ![Light theme with a playlist](assets/screenshots/playlist-light.png) |
| Settings | A playlist in the Light theme with a blue accent |

## Features

- [x] Plays `mp3`, `ogg` and `wav` files (also `flac`, `m4a`, `aac`, `opus`, `webm` if you allow them) from **one or more music folders**, each with or without its subfolders. Pick folders in Settings → Library with a built-in folder browser; folders can be anywhere on the server, not only under the web root
- [x] **Cast** to Google Cast / Nest speakers from the playbar (playbar controls, queue and auto-next keep working on the speaker); AirPlay in Safari
- [x] **Cover art** from each song's embedded ID3v2 picture: a thumbnail per row, a larger cover in the playbar, and artwork in the OS media overlay and on Cast speakers (a neutral placeholder when a song has none; see below)
- [x] **Click to select, double-click to play**: a single click selects a song, a double-click (or Enter, or the play button on its cover) plays it
- [x] **Song menu** on right-click, long-press (touch), the context-menu key or <kbd>Shift</kbd>+<kbd>F10</kbd>, and the ⋯ button: play, play next, add to queue, add to a playlist, favorite, download, copy the song name, remove from the shown playlist and delete (with a confirmation). Items you can't use (demo mode, or a listening session you don't control) are hidden
- [x] **Slim playbar**: an opaque bar about 80 px tall, docked to the bottom (or top) of the window: the song (cover, title, favourite, cast status) on the left, the transport controls with the progress bar in the middle, and volume, queue and cast on the right. The page is padded by the bar's height so it never covers the song list. *Play first* and *Random* sit in the page header
- [x] **Phone layout**: on narrow screens the playbar is a compact bar (cover, title, play/pause, next) over the progress slider; the chevron expands the full controls, and the choice is remembered. The song table fits the screen: names wrap to at most two lines and are trimmed with "…" (the full name is the tooltip), and the Queue column is hidden (it is in the ⋯ and long-press menus)
- [x] **List or grid view**: the song table, or cards with cover, title, artist and album (toggle above the list; remembered per browser)
- [x] **Library sidebar**: All songs, Favorites and your playlists in a left sidebar, or above the songs (`playlist_nav`; small screens always show them above)
- [x] Live waveform in the playbar that follows the music and freezes when paused
- [x] Media keys and OS media overlay (title, play/pause, next/previous, seek) via the Media Session API
- [x] Keyboard shortcuts (see below)
- [x] Remembers the current song, position, loop and shuffle across page refreshes
- [x] **Listen together**: shared sessions where every device plays the same song in sync (see below)
- [x] **Playlists** and **Favorites**, shared on the server, with drag-to-reorder and M3U import/export (see below)
- [x] Queue ("Add to queue" and "Play next"), shuffle and loop
- [x] **Previous** works like other players: after 3 seconds it restarts the song; otherwise it goes back to the song actually played before (a play history, also with shuffle on), and Next then retraces the songs you stepped back from
- [x] **Queue panel**: a side panel with what's playing now, your queue and what plays next (see below)
- [x] **Song info card**: click the song in the playbar (or pick *Song info* in a song's menu) to slide in a card with its cover, tags, audio details (bitrate, sample rate, channels), file size, date added and the playlists it is in, plus release details, an artist bio and similar artists looked up online (see [Online song info](#online-song-info))
- [x] **Edit song info**: fix a song's title, artist, album, year, genre, track, comment and cover, kept in the app or written into the file's tags (see [Editing song info](#editing-song-info))
- [x] **Lyrics** for the current song from [LRCLIB](https://lrclib.net), synced line by line when available (see below)
- [x] Download and delete songs
- [x] Upload files (multiple at once) directly from the web UI
- [x] Track durations (via [`wapmorgan/mp3info`](https://github.com/wapmorgan/Mp3Info))
- [x] Change settings from the GUI (persisted to the config file)
- [x] Seven themes (plus Follow system and custom colors), accent colors, background image, and layout options (see [Settings](#settings))
- [x] Demo mode (`env` = `demo`) that rejects every API request, so nothing on the server can be changed (see [API](#api))
- [x] CSRF protection on state-changing API requests

### Keyboard shortcuts

| Key | Action |
| --- | --- |
| <kbd>Space</kbd> | Play / pause |
| <kbd>←</kbd> / <kbd>→</kbd> | Seek back / forward by `seek_step` seconds (default 5) |
| <kbd>Shift</kbd>+<kbd>←</kbd> / <kbd>Shift</kbd>+<kbd>→</kbd> | Previous / next song |
| <kbd>↑</kbd> / <kbd>↓</kbd> | Volume up / down (moves the selection while the song list has focus) |
| <kbd>M</kbd> | Mute / unmute |
| <kbd>L</kbd> | Toggle loop |
| <kbd>S</kbd> | Toggle shuffle |
| <kbd>A</kbd> | Toggle autoplay (play the next song when one ends) |
| <kbd>Q</kbd> | Show / hide the queue panel |
| <kbd>Y</kbd> | Show / hide the lyrics panel |
| <kbd>I</kbd> | Show / hide the song info card for the current song |

**In the song list.** Clicking a song selects it and gives the list keyboard focus. While the list has focus:

| Key | Action |
| --- | --- |
| <kbd>↑</kbd> / <kbd>↓</kbd> | Move the selection up / down |
| <kbd>Enter</kbd> | Play the selected song |
| <kbd>☰ Menu</kbd> / <kbd>Shift</kbd>+<kbd>F10</kbd> | Open the song menu for the selected song (<kbd>↑</kbd> / <kbd>↓</kbd> to move, <kbd>Enter</kbd> to choose, <kbd>→</kbd> / <kbd>←</kbd> into and out of "Add to playlist", <kbd>Esc</kbd> to close) |
| <kbd>Esc</kbd> | Leave the list (<kbd>↑</kbd> / <kbd>↓</kbd> control the volume again) |

Clicking anywhere outside the list also gives <kbd>↑</kbd> / <kbd>↓</kbd> back to the volume. Double-click a song to
play it. On touch screens, double-tap it, or tap the play button shown on the cover of the selected song. The queue,
favorite, ⋯, download and delete buttons work with a single click, as before.

### Queue panel

The queue button in the playbar (<kbd>Q</kbd>; on phones it's in the expanded controls) opens a side panel. It
stays open or closed across reloads. It has three sections:

1. **Now playing**: the current song.
2. **Next in queue**: songs you added with *Play next* or *Add to queue* (from the ⋯ menu, right-click, the
   Queue column or a playlist's *Add to queue*). They play before anything else.
3. **Next from: _All songs_ / _playlist name_**: the rest of the library or playlist you started from, in the order
   it will play (up to 100 songs). With shuffle on, songs are picked at random, so none are listed.

In the panel:

- Drag a queued song (anywhere on its row) to reorder it, or focus it and press <kbd>Alt</kbd>+<kbd>↑</kbd> / <kbd>↓</kbd>. Any song in the queue panel can also be dragged onto a playlist in the sidebar.
  Dragging works with a mouse and on touch screens.
- × (or <kbd>Delete</kbd>) removes a song from the queue; *Clear* empties it.
- Click a song (or press <kbd>Enter</kbd>) to play it now. A queued song leaves the queue when played.
- Right-click a song for the usual song menu.

On wide screens the page makes room for the panel. On narrower screens it opens over the song list, and on phones
it fills the screen above the playbar. In a listening session, queue changes are shared like any other playback
change. If you can't control playback, the panel is read-only.

### Lyrics

The microphone button in the playbar (<kbd>Y</kbd>) opens a lyrics panel in the queue panel's spot (only one of the two
is open at a time). Lyrics come from [LRCLIB](https://lrclib.net), a free, open lyrics database that needs no API key.

- **Synced lyrics** highlight the current line and keep it in view; click a line to jump there. Scrolling the lyrics
  yourself pauses the auto-scroll for a few seconds.
- **Plain lyrics** are shown as text when no synced version exists.
- The panel header shows which LRCLIB track was matched, so a wrong match is easy to spot.

The server (`lyrics.php`) identifies each song from its ID3 tags, falling back to the file name: it drops the extension,
a leading track number and underscores, splits "Artist - Title", and strips "(prod. …)", "[…]" and "feat. …". It first
asks LRCLIB for an exact artist/title/duration match, then searches. A search result is used only if its duration is
within 3 seconds of the song's, and its title matches exactly, or loosely when one of its artists appears in the file
name or tags. Every answer, including "not found", is cached in `cache/lyrics/`. A "not found" is looked up again after
30 days. Network errors and rate limiting are not cached.

Song titles are sent to lrclib.net when lyrics are looked up.

### Online song info

When you open the song info card, the server (`metadata.php`) looks the song up in open databases. None of them needs an API key:

| Source | What it adds |
| --- | --- |
| [MusicBrainz](https://musicbrainz.org) | Release (album, EP or single), release date, label, track number, ISRC, genres |
| [Cover Art Archive](https://coverartarchive.org) | A cover for songs that have none embedded. It also shows in the playbar and the song list |
| [Wikidata](https://www.wikidata.org) / [Wikipedia](https://www.wikipedia.org) | A short artist bio and picture (English Wikipedia first, then Norwegian, Swedish, Danish, German, then any language) |
| [ListenBrainz](https://listenbrainz.org) | Similar artists. Artists already in your library come first; click one to search your library for them |

Tags missing from the file (album, year, genre, track) are filled in on the card and marked *MusicBrainz*. The file
itself is never changed. The song is identified the same way as for lyrics (ID3 tags, then the file name), and a
MusicBrainz recording is used only if its title and artist match and its length is within 5 seconds of the song's.
Among that recording's releases, an official album or single is preferred over compilations, and the earliest one wins.

The first lookup of a song takes a few seconds, because MusicBrainz allows one request per second. Every answer,
including "not found", is cached in `cache/online/`. A "not found" is looked up again after 30 days. Network errors and
rate limiting are not cached. Turn it off with *Online song info* in Settings → Song info. When it is on, artist and
title names are sent to these services.

**AcoustID (optional):** to identify badly tagged songs by their sound, install `fpcalc` (Chromaprint;
`apt install libchromaprint-tools`, already in the Docker image) and paste a free [AcoustID](https://acoustid.org/new-application)
API key into *AcoustID API key* in Settings → Song info.

**Fetch metadata:** Settings → Song info → *Fetch metadata* looks up the whole library at once, instead of one song
whenever its card is opened. It runs on the server (`tools/fetch-metadata.php`, started in the background), so you can
close the page; the dialog shows its progress, a log and a Stop button whenever you come back. *Songs not looked up yet*
skips songs that are already cached; *Every song* looks them all up again. New songs take about 5 seconds each. Below
it, *Songs without info* lists the songs that weren't found, each with an Edit button. The job can also run from a
terminal or cron, as the web server's user:

```bash
sudo -u www-data php tools/fetch-metadata.php --mode=missing
```

### Editing song info

*Edit info* (the pencil in the song info card, or in a song's ⋯ menu) opens an editor for the title, artist, album,
year, genre, track, comment and cover. Empty fields keep the file's own value, which is shown in grey.

- **Save** keeps the changes in the app only, in `metadata/overrides.json` (with covers in `metadata/covers/`). The
  music file isn't touched, so this works on read-only folders, and *Reset* undoes it. Edited values show everywhere
  (song list, playbar, media controls, lyrics lookup) and are marked *edited* in the song info card.
- **Save to file** writes the fields (and the cover) into the MP3's ID3v2.3 tags with
  [getID3](https://github.com/JamesHeinrich/getID3), keeping its other tags, so every player sees them. Only for MP3
  files the web server can write to.
- **Fill from MusicBrainz** copies what MusicBrainz knows into the fields the song is missing.
- **MusicBrainz recording**: for a song that isn't found or matched wrongly, paste the right song's link from
  musicbrainz.org. Its release info, cover and similar artists are then used for the card.

### Cover art

- Covers are read from the ID3v2 `APIC` frame of each file by `cover.php?file=<name>&size=sm|lg`
  (96 px thumbnails and 512 px covers, resized with GD when available). Nothing is read while the page renders, and
  thumbnails are lazy-loaded, so large libraries stay fast.
- Extracted covers are cached in `cache/covers/` (git-ignored; created automatically if the web server can write to
  the app folder). Responses carry `ETag`/`Last-Modified` headers and are versioned by the file's modification time,
  so a retagged file shows its new cover.
- A cover set in the song editor wins over the file's own, which wins over one found online (Cover Art Archive).
- Songs without a cover show a music-note placeholder in the current accent colour. The OS media
  overlay and Cast speakers show `assets/branding/logo.png` instead.

Shortcuts are ignored while typing in a text field.

### Casting

The Cast button sits on the right of the playbar, next to the queue button.

- **Google Cast** works in Chrome and Edge. Chrome only enables it on a **secure origin**: serve the app over HTTPS
  (e.g. behind a reverse proxy), or open it on `localhost`. Over plain HTTP the button is disabled with a tooltip saying so.
- The speaker fetches the audio itself, so the page must be opened via an address the speaker can reach
  (your server's LAN IP or hostname, not `localhost`).
- While casting, play/pause, seek, volume, previous/next, queue, shuffle and loop all control the speaker. Disconnecting
  continues locally (paused) from where the speaker left off.
- In Safari the button opens the AirPlay picker instead.

### Accounts and sign-in

Everyone signs in with their own account (turn this off with the *Require sign-in* setting or `AUTH_REQUIRED=false`).

- **First run**: a fresh install opens a short setup screen: create the admin account, pick the music folders, set
  the site title, theme and whether sign-in is required, and optionally start looking up song info online. With
  `ADMIN_USERNAME` and `ADMIN_PASSWORD` in the environment the admin is created on first start and the setup screen
  is skipped (see [Environment](#environment-env)). On an existing install, the first account takes over the
  playlists, Favorites and sidebar order that were there before.
- **Admins** manage users (Settings → Users: add, set a new password, change role, delete), the music folders and
  every setting marked *All users*, and can upload, delete and edit songs and run *Fetch metadata*. **Users** play
  music and keep their own playlists, Favorites and look.
- **Your own settings**: playback, appearance, player bar, layout and background settings are saved per account;
  site title, music folders, file types, online lookups and sign-in are shared (marked *All users*).
- **Account** (Settings → Account, or the menu under your name): display name, password (changing it signs out your
  other devices), *Show others what I'm playing*, and sign out. *Keep me signed in* on the sign-in page lasts 30 days.
- **Activity**: the radio button in the header lists what other users are playing right now (only those who share
  it). *Play* starts the same song; *Listen along* joins their listening session if they are in one, or plays the
  song from where they are.
- After 5 failed sign-ins from one address, sign-in is blocked for 5 minutes.
- Accounts are stored in a SQLite database, `data/mp3web.sqlite` (or `$DATA_DIR`), which must be writable by the
  web server. Song and cover links carry a signed token, so Cast devices and exported `.m3u` playlists work without
  a session.

### Listening sessions

Click the people button in the header to **create** or **join** a session.

- Everyone in a session hears the same song at the same position. Playback is synced by polling the server
  every ~1.5s, and devices that drift more than a second re-seek.
- **Join** with the 8-character code, with an invite link (`…/?join=<code>`, copy it from the session card), by
  scanning the **QR code** on the session card with a phone, or by picking a **public** session from the list in the
  Join card.
- **The host controls playback** (play/pause, seek, skip, queue, loop, shuffle). Listeners' controls are dimmed.
  The host can switch on *Let everyone control playback* to make the session a shared jukebox, or tick it in the
  Create form so the session starts that way.
- Set **your name** at the top of the dialog (with an account this is your display name; without one it is remembered
  in this browser). It can be changed at any time, even mid-session. The session card lists every member by name, with the host marked and idle members shown as *away*.
- **Session defaults** (under your name) set how sessions you create start: let everyone control playback, make it
  public, a default name, and whether it starts with what you're playing or empty. They prefill the Create form. They
  are kept in the PHP session and mirrored to `localStorage`, so they belong to this browser.
- Volume is per device and is never synced. Casting works inside a session too.
- If the host leaves or goes quiet for a minute, the longest-joined listener becomes host. Sessions with no activity
  for 24 hours are deleted.
- After a page refresh you stay in the session. If the browser blocks autoplay, click *Tap to start listening*
  under the song title.
- Session state is stored as JSON files in `session/`, which must be writable by the web server.

### Playlists

The library sidebar (or the pills above the song list, with `playlist_nav` = `top` and on small screens) switches
between **All songs**, **Favorites** and your playlists.

- **Create** a playlist with *New playlist*, or **import** an `.m3u` / `.m3u8` file. Songs are matched to your library
  by file name, and any that aren't found are listed.
- **Add songs** from a song's ⋯ menu (*Add to playlist*), which also has *Play next*, *Add to queue* and
  *Add to Favorites*. The heart next to a song, or next to the title in the player, toggles Favorites.
- **Play** or **Shuffle** a playlist from its header, or add all of it to the queue. Next, previous and shuffle stay
  inside the playlist you started from, even if you switch the list to another view.
- **Reorder** by dragging songs (when the list isn't sorted or searched), or with *Move up* / *Move down* in the ⋯ menu.
- **Rename**, **export** (`.m3u` with links to the songs, playable in e.g. VLC) or **delete** from the playlist's ⋯ menu.
- Playlists and Favorites are **your own**. To share a playlist, pick *Sharing* in its ⋯ menu: *Everyone can listen*
  (others see it, marked with the share icon and your name, read-only) or *Everyone can edit*. Only the owner can
  change sharing or delete it. Favorites can't be shared.
- Playlists are stored as JSON files in `playlists/` (Favorites in `playlists/favorites/<user id>.json`). If a song
  file is removed, it stays in the playlist (shown as "not in library") and comes back if the file returns.

## Requirements

- PHP 8.x with a web server (Apache, nginx + PHP-FPM, etc.)
- PHP extensions: `mbstring` and `pdo_sqlite` (required), `curl` (lyrics), `gd` (optional: resizes cover art; without it covers are sent at full size)
- [Composer](https://getcomposer.org/) (dependencies: `vlucas/phpdotenv`, `wapmorgan/mp3info`, `james-heinrich/getid3`)
- Write access for the web server user to:
  - the first music folder (uploads), any folder you delete songs from, and the app folder, where deleted songs are moved to `deleted/`
- Read access for the web server user to every music folder
  - the active config file (settings dialog)
  - `session/`, `playlists/` and `data/` (accounts)
  - `cache/` (optional: covers, tags and lyrics are cached there)
- Outbound HTTPS to `lrclib.net` for lyrics (optional)

## Getting started

### Docker (recommended)

The [Dockerfile](Dockerfile) builds a `php:8.3-apache` image, clones the `main` branch of this repo (so the image
always contains the latest `main`, not your local checkout) and runs `composer install`. It also installs the `gd`,
`intl`, `zip`, `pdo_mysql` and `mysqli` extensions; only `gd` is used by the app.

```bash
# Get the Dockerfile
curl -O https://raw.githubusercontent.com/Darknetzz/mp3-web/refs/heads/main/Dockerfile

# Build the image
docker build -t mp3-web:main .

# Run the container (replace 9096 with any port you want)
docker run -d -p 9096:80 mp3-web:main
```

To use your own music, mount it over the music folder. Mount `data/` and `playlists/` too, so accounts and playlists
survive a new container:

```bash
docker run -d -p 9096:80 \
  -v /path/to/your/music:/var/www/html/music \
  -v mp3-data:/var/www/html/data -v mp3-playlists:/var/www/html/playlists \
  mp3-web:main
```

Open the page and the setup screen walks you through the admin account and music folders. To skip it, pass
`-e ADMIN_USERNAME=… -e ADMIN_PASSWORD=… -e MUSIC_FOLDERS=music` (see [Environment](#environment-env)).

### Docker Compose

```yml
services:
  mp3-web:
    build: .            # directory containing the Dockerfile
    image: mp3-web:main
    container_name: mp3-web
    ports:
      - "9096:80"
    volumes:
      - /path/to/your/music:/var/www/html/music
      - mp3-data:/var/www/html/data
      - mp3-playlists:/var/www/html/playlists
    environment:
      # Optional: skip the setup screen on first start
      # ADMIN_USERNAME: admin
      # ADMIN_PASSWORD: change-me
      # MUSIC_FOLDERS: music
    restart: unless-stopped

volumes:
  mp3-data:
  mp3-playlists:
```

### Manual

1. Clone this repo (or download the [latest release](https://github.com/Darknetzz/mp3-web/releases/latest)) into your web root.
2. Install dependencies (required, the app won't start without `vendor/autoload.php`):
   ```bash
   composer install
   ```
3. Optional: copy `.env.example` to `.env` (see [Environment](#environment-env)).
4. Optional: create `config.local.php` for your settings, so the tracked `config.php` stays unchanged
   (see [Settings](#settings)).
5. Put some music in the `music` folder, or add your own folders in Settings → Library.
6. Open the page in a browser: the setup screen creates the admin account. Then start listening.

For a quick local try without a web server, PHP's built-in server works:

```bash
php -S 127.0.0.1:8080
```

## Configuration

### Settings

Open **Settings** (gear button) to change everything from the browser: settings are grouped into General, Library,
Playback, Appearance, Layout and Background, with a search box. Changes are saved immediately; most apply after a reload.

Where settings live:

- `config.default.php` defines every setting (name, type, options, default). Don't edit it to change settings.
- `config.local.php` (git-ignored), or `config.php` if there is no local file, holds your values. Only the values are
  read from it, so new settings and options added in updates show up automatically with their defaults.
  A minimal override file:
  ```php
  <?php
  $config = [
      "music_folders" => ["value" => [
          ["path" => "/srv/music", "recursive" => true],
          ["path" => "music",      "recursive" => false],
      ]],
      "player_theme"  => ["value" => "auto"],
  ];
  ```
- The settings dialog writes to that active file, so it must be writable by the web server.

| Key | Default | Description |
| --- | --- | --- |
| **General** | | |
| `site_title` | `Music Player` | Header and browser tab title |
| `no_song_text` | `No song selected` | Player text when nothing is selected |
| `env` | `dev` | `dev`, `prod` or `demo`. `demo` makes `api.php` reject every request (listing, downloads, uploads, deletes, settings, playlists, sessions, lyrics) and hides file and playlist actions in the UI |
| **Library** | | |
| `music_folders` | `music`, with subfolders | Folders with audio files, each with its own *include subfolders* switch. Relative paths are relative to the app folder. Uploads go to the first folder. A song is identified by its path inside its folder (`Artist/Album/song.mp3`; songs at the top keep their plain file name), and when two folders hold the same path the first folder wins. Older configs with a single `audio_path` are read as one folder with subfolders |
| `allowed_types` | `mp3, ogg, wav` | Extensions that are listed and accepted for upload. Only audio types are kept: `mp3 ogg oga wav flac m4a aac opus webm` |
| `include_file_extension` | `true` | Show file extensions in song names |
| `default_sort` / `default_sort_order` | `none` / `asc` | Initial sort of the song list (name, duration, size, date added) |
| `default_table_cols` | `name, duration, queue` | Columns shown in the song table |
| **Playback** | | |
| `autoplay_next` | `true` | Play the next song automatically; when off, playback stops after each song |
| `default_volume` | `0.5` | Starting volume (the last used volume is remembered per browser) |
| `seek_step` | `5` | Seconds jumped by ←/→ and media seek buttons |
| `start_shuffle` / `start_loop` | `false` | Turn shuffle / loop on when the page loads |
| `remember_position` | `true` | Restore song, position, loop and shuffle after a refresh |
| `auto_scroll` | `true` | Keep the playing song in view |
| `auto_scroll_on_end` | `false` | Also scroll to the next song when it starts because the previous one finished |
| `cast_end_action` | `continue` | When casting stops: `continue` playing on this device or `pause` |
| **Appearance** | | |
| `player_theme` | `dark` | See [Themes](#themes) |
| `accent_color` | `green` | green, blue, purple, pink, orange, red, teal, yellow or `custom` |
| `custom_accent` | `#1db954` | Accent when `accent_color` is `custom` |
| `custom_base`, `custom_background`, `custom_surface`, `custom_text` | dark, `#121212`, `#1e1e1e`, `#f0f0f0` | Colors for the `custom` theme |
| **Player bar** | | |
| `player_position` | `bottom` | Player bar at the `bottom` or `top` |
| `player_size` | `normal` | `compact`, `normal` or `large` |
| `player_buttons` | all | Buttons shown: `shuffle`, `stop`, `prevnext`, `repeat`, `autoplay`, `volume`, `lyrics`, `queue`, `cast` |
| `player_show_cover` | `true` | Show the cover art |
| `player_progress` | `center` | Progress bar under the controls (`center`) or as a full-width line on the bar's `edge` |
| `player_time_format` | `total` | Show the song's `total` length or the time `remaining` |
| `show_waveform` | `true` | Show the waveform |
| `waveform_placement` | `inline` | Next to the title (`inline`) or across the whole bar (`background`) |
| `waveform_style` | `bars` | `bars`, `bottom` (bars from the bottom) or `line` (a smooth wave) |
| `waveform_bars` | `0` | Number of bars; `0` is auto (20 inline, 64 as background) |
| `waveform_color` / `waveform_custom_color` | `accent` / `#2fb344` | `accent`, `text` or `custom` |
| `waveform_opacity` | `0.3` | Opacity of the background waveform |
| `waveform_sensitivity` | `1` | Gain on the levels (0.5–2) |
| `use_legacy_player` | `false` | Use the browser's built-in audio controls |
| **Layout** | | |
| `playlist_nav` | `sidebar` | List All songs and playlists in a left `sidebar` or `top` (above the songs) |
| `show_quick_buttons`, `show_upload` | `true` | Show the Play first/Random buttons in the page header, the upload box |
| `compact_rows` | `false` | Tighter song list rows |
| `fluid-container` | `false` | Full-width layout |
| **Background** | | |
| `background_image` | empty | Image URL (`https://…`) or relative path (`img/bg.jpg`) |
| `background_dim` / `background_blur` | `0.6` / `0` | Darken (or lighten, on light themes) and blur the image |

### Themes

`player_theme` offers **Follow system** (switches between Light and Dark with your device), **Light**, **Arctic**
(cool light), **Dark**, **OLED black**, **Midnight** (navy), **Forest** (deep green), **Sunset** (warm plum) and
**Custom colors**, where you pick the background, surface and text colors and whether they build on the light or dark
look. Combine any theme with an accent color, and optionally a background image.

### Environment (`.env`)

Loaded with [`vlucas/phpdotenv`](https://github.com/vlucas/phpdotenv) when the file exists. Real environment
variables (e.g. `docker run -e`) win over `.env`. Copy [`.env.example`](.env.example) to start.

| Variable | Default | Used for |
| --- | --- | --- |
| `VERSION` | `dev` | Shown next to the site title in the header |
| `URL` | GitHub repo | Link target of that version label |
| `ENV` | `prod` | Read into `$env` in `_includes.php` but not otherwise used. The environment that changes behaviour is the `env` *setting* |
| `AUTH_REQUIRED` | (setting) | `true`/`false`: require sign-in. Overrides the *Require sign-in* setting and locks it |
| `ADMIN_USERNAME`, `ADMIN_PASSWORD` | | On first start (no accounts yet), create this admin and skip the setup screen |
| `ADMIN_DISPLAY_NAME` | | Display name for that admin |
| `MUSIC_FOLDERS` | | With `ADMIN_*`: music folders to set, separated by `:` (end a path with `!` to skip its subfolders) |
| `SITE_TITLE` | | With `ADMIN_*`: the site title to set |
| `SETUP_TOKEN` | | If set, the setup screen asks for it, so a fresh server reachable by others can't be claimed by them |
| `DATA_DIR` | `data/` | Where the accounts database is kept |
| `APP_NAME`, `AUTHOR` | | Not read by the code |

```ini
APP_NAME = "mp3-web"
AUTHOR   = "<your name>"
URL      = "https://github.com/Darknetzz/mp3-web"
ENV      = "prod"
VERSION  = "vX.Y.Z"
```

## Project structure

| Path | Purpose |
| --- | --- |
| `index.php` | Main page: player, song table, upload form |
| `_includes.php` | Bootstrap: session + CSRF token, Composer autoload, `.env`, config loading, constants |
| `config.default.php` | Definition and default of every setting |
| `config.php` | Tracked settings file, used when there is no `config.local.php` |
| `functions.php` | Helpers: config read/write, listing, upload, delete, durations and ID3 tags, `apiResponse()` |
| `library.php` | The music library: scans the music folders, maps song keys to files (`musicLibrary()`, `songPath()`) |
| `stream.php` | Streams a song (`?file=<song key>`) with `Range` support, so folders outside the web root play, seek and cast |
| `cover.php` | Cover art endpoint (`?file=<song key>&size=sm\|lg`), cached in `cache/covers/` |
| `sessions.php` | Listening sessions: storage (locked JSON files), membership, host handover, sync API |
| `playlists.php` | Playlists and Favorites: storage (locked JSON files), M3U import/export |
| `lyrics.php` | Lyrics lookup on LRCLIB (ID3 tags / file name matching) with a disk cache |
| `metadata.php` | Online song info for the song info card (MusicBrainz, Cover Art Archive, Wikipedia, ListenBrainz, optional AcoustID), cached in `cache/online/` |
| `metajob.php` | The *Fetch metadata* background job: state, start/stop, songs without info |
| `tools/fetch-metadata.php` | Command-line runner for the *Fetch metadata* job (also usable from cron) |
| `songedit.php` | Edited song info: app overrides (`metadata/`) and writing ID3 tags with getID3 |
| `songedit.js` | Song editor dialog and the *Fetch metadata* panel in Settings |
| `auth.php` | Accounts: SQLite storage, sign-in, remember-me, setup, media tokens, user management |
| `login.php`, `setup.php` | Sign-in page and first-run setup screen |
| `activity.php`, `activity.js` | What other users are playing (Activity panel), Listen along |
| `account.js` | Settings → Account and Users, sign out |
| `api.php` | JSON API used by the frontend |
| `js.php` | Frontend logic (jQuery), served inline |
| `modals.php` | Settings and session modals |
| `cast.js` | Google Cast sender + AirPlay fallback |
| `waveform.js` | Playbar waveform (Web Audio analyser) |
| `session.js` | Listening session sync and session modal |
| `playlists.js` | Playlist bar and views, ⋯ song menu, favorites, drag-to-reorder |
| `queue.js` | Queue side panel: now playing, queue (drag to reorder), next from the play context |
| `lyrics.js` | Lyrics panel: synced highlighting, click to seek |
| `style.css` | Custom styles |
| `themes.css` | Theme and accent color variables |
| `assets/branding/` | Logo, banner, favicons and placeholder cover. `python3 build.py assets/branding` regenerates the SVGs (needs `fontTools` and the Lato font); render PNGs with `rsvg-convert` |
| `assets/icons/` | Lucide icon sprite and `table.css`. `python3 assets/icons/build.py` rebuilds them from `lucide-static` (downloads from jsDelivr) |
| `assets/screenshots/` | README screenshots |
| `music/` | Default music folder (ships with a few sample tracks) |
| `session/` | Listening session JSON files (created at runtime, git-ignored) |
| `playlists/` | Playlist JSON files (created at runtime, git-ignored) |
| `data/` | Accounts database `mp3web.sqlite` (created at runtime, git-ignored) |
| `metadata/` | Edited song info and covers (created at runtime, git-ignored) |
| `cache/` | `covers/`, `meta/` (ID3 tags and durations) and `lyrics/` caches (created at runtime, git-ignored) |
| `deleted/` | Songs deleted from the UI are moved here, not erased (created at runtime, git-ignored) |
| `Dockerfile` | Container image definition |
| `IMPROVEMENTS.md` | Notes on known issues and suggested improvements |

## API

All actions live in `api.php`. Responses are JSON shaped like
`{ "status": "success" | "error" | "conflict", "statuscode": 0 | 1, "response": "<message, may contain HTML>", "data": {...} }`
(`statuscode` is `1` only for `error`). `conflict` is returned by `sessionUpdate` and `reorderPlaylist` when the
`if_version` you sent is out of date; `data` then holds the current state. `exportPlaylist` is the exception: it
returns an `.m3u` file.

The action is passed as `action` in the query string or POST body. Actions marked POST require a POST with a valid
`csrf_token` (from the PHP session; the frontend sends it automatically). Every action except `login`, `logout`,
`setupFinish` (and `browseDirs` during setup) needs a signed-in session while sign-in is required; without one the
response is HTTP 401 with `data.auth = false`. Actions marked *admin* answer 403 for other users. When the `env`
setting is `demo`, every action returns an error.

| Action | Method | Parameters | Description |
| --- | --- | --- | --- |
| `ls` | GET | | List songs in every music folder |
| `dl` | GET | `file` | Calls `download()`, which is commented out in `functions.php`, so this action currently fails. The UI downloads songs by opening their `stream.php` URL |
| `login` | POST | `username`, `password`, optional `remember` | Sign in (`remember`: stay signed in for 30 days) |
| `logout` | POST | | Sign out |
| `setupFinish` | POST | `username`, `password`, `password2`, `display_name`, `music_folders` (JSON), `site_title`, `auth_required`, `player_theme`, `share_playing`, optional `setup_token` | First-run setup; only works until it's done |
| `me` | GET | | The current user, and whether sign-in is required |
| `accountUpdate` | POST | `display_name`, `share_playing`, or `current_password` + `new_password` | Change your own account |
| `usersList` / `usersCreate` / `usersUpdate` / `usersDelete` | GET / POST | `username`, `password`, `role`, `display_name`; `id` for update/delete | *Admin.* Manage accounts. Deleting a user deletes their private playlists; shared ones pass to you |
| `nowPlaying` | POST | `song`, `position`, `playing` | Report what you're playing (ignored unless your account shares it; empty `song` clears it) |
| `activity` | GET | | What other users are playing now |
| `setPlaylistSharing` | POST | `id`, `shared`, `collab` | Owner only: share a playlist with everyone, read-only or editable |
| `rm` | POST | `file` (song key) | *Admin.* Move a song to `deleted/` |
| *(upload)* | POST | `files[]`, no `action` | *Admin.* A multipart request with files and no `action` uploads them to the first music folder. Extensions must be in `allowed_types`; characters other than `A-Z a-z 0-9 . _ -` in the file name become `_` |
| `getconfig` | GET | optional `key` | All settings, or one setting's value in `data.value` |
| `browseDirs` | GET | optional `path` | *Admin.* Subfolders (names only) of a folder on the server, for the music folder browser. Starts at the app folder |
| `setconfig` | POST | `config[key]`, `config[value]` | Update one setting: per-user settings go to your account, *All users* settings (admin only) to the active config file |
| `createSession` | POST | `name`, `public`, `allow_all` | Create a listening session and join it as host |
| `joinSession` / `leaveSession` | POST | `sessionCode` (join) | Join or leave a listening session |
| `listSessions` | GET | | Public sessions with active listeners |
| `sessionState` | GET | | Current session state (polled by members) |
| `sessionUpdate` | POST | `state` (JSON), optional `if_version` | Publish playback state; `if_version` makes it compare-and-swap |
| `setDisplayName` | POST | `name` (max 30 characters) | Set your display name; also updates it in your current session |
| `sessionSettings` | POST | `allow_all` | Host only: let everyone control playback |
| `setUserPrefs` | POST | `prefs` (JSON object) | Save session defaults: `session_allow_all`, `session_public`, `session_name`, `session_share_playback`. Unknown keys are ignored; returns the full set |
| `listPlaylists` | GET | | Playlist summaries |
| `getPlaylist` | GET | `id` | One playlist's songs (`favorites` is the built-in Favorites list) |
| `createPlaylist` | POST | `name`, optional `songs[]` | Create a playlist |
| `renamePlaylist` | POST | `id`, `name` | Rename a playlist |
| `deletePlaylist` | POST | `id` | Delete a playlist |
| `addToPlaylist` | POST | `id`, `songs[]` | Add songs |
| `removeFromPlaylist` | POST | `id`, `song` | Remove a song |
| `reorderPlaylist` | POST | `id`, `songs[]`, `if_version` | Set a new order; `conflict` if the playlist changed meanwhile |
| `toggleFavorite` | POST | `song` | Add or remove a song from Favorites |
| `exportPlaylist` | GET | `id` | Download the playlist as `.m3u` |
| `importPlaylist` | POST | `playlist` (file, max 1 MB) | Create a playlist from an `.m3u` / `.m3u8` upload |
| `lyrics` | GET | `song` | `found`, `synced` (`[[seconds, line], …]`), `plain`, `instrumental`, `match` |

Songs are referred to by their song key (path inside their music folder) everywhere: `file`, `song` and `songs[]`.
Cover art is served by `cover.php?file=<song key>&size=sm|lg[&v=<mtime>]` (96 px / 512 px), and audio by
`stream.php?file=<song key>`, not by `api.php`. `stream.php` and `cover.php` keep working in `demo` mode.

> **Note:** there is no authentication. Anyone who can reach the page can upload, delete, change settings and
> browse the server's folder names (music folder picker),
> so don't expose it to the internet without putting it behind auth (reverse proxy, VPN, etc.), or set `env` to `demo`.

## Troubleshooting

### Unable to upload/delete music or save settings

This is almost always file permissions. You may see errors like *The directory `<PATH>` is not writable.*
Run the following, replacing `<PATH>` with the mp3-web directory:

```bash
# Example: chown -R www-data:www-data /var/www/html/mp3-web
chown -R www-data:www-data <PATH>
```

```bash
# Example: chmod -R 775 /var/www/html/mp3-web
chmod -R 775 <PATH>
```

If your music folder is on a remote share where ownership can't be changed, you may have to use `777` instead of `775`.

### Large uploads fail

Raise `upload_max_filesize` and `post_max_size` in your PHP config (the Docker image sets both to `50M`).
The upload box itself only accepts `.mp3` files up to 10 MB each (Dropzone options in `js.php`), even if
`allowed_types` lists more.

### The song list is empty or the page breaks after adding a file

`getDuration()` catches `Exception` only. Some MP3s make `wapmorgan/mp3info` throw a `DivisionByZeroError`
(seen with low sample rate MPEG-2 files), which is not caught and stops the page. Re-encoding the file to a common
format (44.1 kHz) avoids it.

### "The Composer autoload file is missing."

Run `composer install` in the project directory.

### "The data folder … is not writable" / "attempt to write a readonly database"

The web server must own (or be able to write) `data/` and every file in it, including `mp3web.sqlite-wal` and
`-shm`. These appear when anything opens the database, so don't open it as another user (e.g. with `sqlite3` as
yourself) while the app runs, or fix the owner afterwards: `chown -R www-data:www-data data`.

### Locked out

An admin can set a new password for anyone in Settings → Users. If no admin can sign in, set
`AUTH_REQUIRED=false` (or `auth_required` → `false` in the config file), open the app (you act as the first
admin), set a new password, and turn sign-in back on.
