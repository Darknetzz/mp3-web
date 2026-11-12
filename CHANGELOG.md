# Changelog

All notable changes to mp3-web are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [2.0.0] - 2026-11-12

A near-complete redesign: accounts, a new player, playlists, casting and listening together.

### Upgrade notes (breaking)

- **Sign-in is now required by default.** A first-run setup screen creates the admin account.
  Set `AUTH_REQUIRED=false` (or turn off "Require sign-in") to keep the old open behaviour,
  or set `ADMIN_USERNAME`/`ADMIN_PASSWORD` to skip setup. `SETUP_TOKEN` protects the setup page.
- The first account takes over existing playlists and favorites; these are now per user.
- Users are stored in SQLite at `data/mp3web.sqlite`. In Docker, keep `data/` and `playlists/` as volumes.
- The single `audio_path` setting became a list of music folders. Old configs are read as one
  folder with subfolders.
- Songs are now served through `stream.php` with signed URLs instead of directly from the web root.
- `.env` is optional; see `.env.example` for the available variables.

### Added

- **Accounts:** users with admin and user roles, remember me, sign-in throttling, Account and
  Users settings, per-user favorites, playlists and settings (global settings stay admin-only).
- **First-run setup** for the admin account, music folders, site title, theme, sign-in and metadata lookup.
- **Activity panel** showing what other users are playing, with Play and Listen along.
- **Multiple music folders** with optional subfolders and an inline folder browser.
- **Playlists:** server-side playlists with a playlist bar, favorites, reordering, M3U import/export,
  details, images, pinning, sidebar reorder, read-only or editable sharing, and drag-and-drop of songs
  onto playlists in the sidebar.
- **Left sidebar** with All songs and playlists.
- **Grid view** with song cards.
- **Cover art**, select and double-click to play, Lucide icons.
- **Queue side panel** (now playing, queue, next from playlist) with drag-to-reorder rows.
- **Lyrics panel** with synced lyrics from LRCLIB.
- **Song info card** that slides in from the right, with online lookup.
- **Song editing** and metadata fetching for the whole library.
- **Google Cast** support with media keys and the cast device's volume on the slider.
- **Listening sessions** (listen together) with member display names, QR code to join and per-user session defaults.
- Right-click context menu for songs.
- Docked three-column player bar with waveform; customizable in the new Player bar settings.
- Autoplay toggle, loudness equalization setting, scroll-to-adjust volume.
- Previous goes back through the play history.
- Themes, accent colors and many more settings in a redesigned, categorized settings dialog that applies changes live.
- Logo, banner, social preview, branded favicons and README screenshots.
- `.env.example`.

### Changed

- Player bar shows the tag title and artist, and a pause button on the playing song's cover.
- Compact phone player bar, two-line song names and a new cover placeholder.
- Toned-down player bar button colors.
- No auto-scroll when the next song starts because the previous one ended.
- README brought up to date with the code.

### Fixed

- Auto-advance to the next song while casting.
- Playback stopping when switching between a cast device and this computer.
- Loudness equalization being far too loud at low volume.
- Lyrics matching a title that is only the artist's name.

### Security

- CSRF protection on all state-changing API requests.
- Song keys are resolved inside the music folders only; `stream.php` only serves audio extensions.

## [1.5.0] - 2025-12-28

### Added

- Light mode option (dark stays default), player theme and opacity settings.
- Reset settings button and `config.default.php`.
- Editable list settings with add/remove buttons.
- Public option for sessions; session settings moved to a separate stylesheet.
- Dynamic favicons that show play/pause state.
- Volume saved in localStorage, taking precedence over the config value.
- Options for which table columns to show by default and a fluid container toggle.

### Changed

- Updated Tabler, bootstrap-table and Bootstrap Icons.
- Download button hidden by default, centered action buttons, new add-to-queue icon.
- Docker image on PHP 8.3 with Apache, installs Composer dependencies and clones `main` with submodules.
- File uploads report per-file success and error counts and validate MIME types against the allowed types.

### Fixed

- Duration formatting (rounding to 60 seconds, float-to-int deprecation).
- Toast messages (#21), event listeners on dynamic elements, volume slider rounding.

### Security

- Path traversal protection in file operations, escaped settings output to prevent XSS.

## [1.4.4] - 2024-10-12

### Added

- Environment variables via `.env` (`vlucas/phpdotenv`).
- Sortable playlist.
- Input warning/success helpers.

### Changed

- Session modal improvements and modal backdrop.
- Volume range in settings shows a percentage; config textareas auto-size.
- Updated Tabler to 1.0.0-beta21 and Dropzone to 6.0.0-beta.2.

### Fixed

- Toasts hiding behind other elements.

## [1.4.3] - 2024-10-11

### Added

- `VERSION` file; the Dockerfile pulls the app from git.

### Fixed

- File uploads.
- Reload script placement and failsafe constants in `_includes.php`.

## [1.4.2] - 2024-10-11

### Fixed

- Page reload.

## [1.4.1] - 2024-10-11

### Added

- `env` config option.

### Fixed

- Config type selection and the `$key` check in `getConfig`.

## [1.4.0] - 2024-10-11

### Added

- Queue system (#6).
- Change the config from the GUI.

### Changed

- Delete button and unnecessary table columns hidden by default; new play/pause button.
- Better file upload error messages.
- Previous resets the current song's time.

### Fixed

- `Array to string conversion` error after the config restructure.

## [1.3.0] - 2024-10-05

### Added

- Auto-scroll to the playing song (with a config option to disable it).
- Favicon.
- API responses shown as toast messages.

### Changed

- Song table uses bootstrap-table; functions moved to `functions.php` and includes to `_includes.php`.

### Fixed

- Download and delete buttons after the table update.

## [1.2.0] - 2024-10-03

### Added

- API for uploading, deleting and downloading songs.
- `config.php` replaces `config.json`.
- Song durations in the table.
- Dockerfile and Composer setup.

### Changed

- Centered player layout.

### Fixed

- Media keys and upload permissions in the Docker image.

## [1.1.1] - 2024-10-01

### Added

- `default_volume` and `include_file_extension` settings.

### Changed

- Improved song indexing.

## [1.1.0] - 2024-10-01

### Added

- Custom audio player with loop, shuffle and volume control.
- Click the volume icon to mute; space toggles play/pause.
- `config.json` for customization.
- Song table with search.

## [1.0.1] - 2024-10-01

### Fixed

- Play/pause behaviour and playlist looping.

## [1.0.0] - 2024-10-01

Initial release.

[2.0.0]: https://github.com/Darknetzz/mp3-web/compare/v1.5.0...v2.0.0
[1.5.0]: https://github.com/Darknetzz/mp3-web/compare/v1.4.4...v1.5.0
[1.4.4]: https://github.com/Darknetzz/mp3-web/compare/v1.4.3...v1.4.4
[1.4.3]: https://github.com/Darknetzz/mp3-web/compare/v1.4.2...v1.4.3
[1.4.2]: https://github.com/Darknetzz/mp3-web/compare/v1.4.1...v1.4.2
[1.4.1]: https://github.com/Darknetzz/mp3-web/compare/v1.4.0...v1.4.1
[1.4.0]: https://github.com/Darknetzz/mp3-web/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/Darknetzz/mp3-web/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/Darknetzz/mp3-web/compare/v1.1.1...v1.2.0
[1.1.1]: https://github.com/Darknetzz/mp3-web/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/Darknetzz/mp3-web/compare/v1.0.1...v1.1.0
[1.0.1]: https://github.com/Darknetzz/mp3-web/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/Darknetzz/mp3-web/releases/tag/v1.0.0
