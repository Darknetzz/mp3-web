<?php 
require_once('_includes.php');
$theme = themeSetup();
?>

<!DOCTYPE html>
<html lang="en"<?= $theme["attributes"] ?>>
  <head>

    <meta charset="utf-8">
    <link rel="icon" href="favicon.ico?v=<?= @filemtime(__DIR__ . '/favicon.ico') ?>" type="image/x-icon">
    <link rel="apple-touch-icon" href="assets/branding/apple-touch-icon.png">
    <meta name="theme-color" content="#2fb344">
    <title><?= getConfig("site_title") ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://code.jquery.com/jquery-4.0.0.min.js" integrity="sha384-fgGyf7Mo7DURSOMnOy7ed+dkq5Job205Gnzu6QIg0BOHKaqt4D76Dt8VlDCzcMHV" crossorigin="anonymous"></script>

    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.7.4/dist/js/tabler.min.js" integrity="sha384-kpYFZpKE7QEV4IYuIatS6xC/fQcrKph3wb7YPoOznHj5g5Kwa+RhX+/zrGUmm9k/" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.7.4/dist/css/tabler.min.css" integrity="sha384-+YtspJF2L+4VLu1yrWfgSSmCUujhd1W+ji1C5TxKZeErkdVQQVTgKFBXiMcDf7VO" crossorigin="anonymous">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap-table@1.28.0/dist/bootstrap-table.min.js" integrity="sha384-8rz2EDULrxGjqTDNDyKc9zEpyD/RthtjiBdE5Prl4FF0Qdevv//gAu603sSFABcK" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-table@1.28.0/dist/bootstrap-table.min.css" integrity="sha384-0sajwRCyTnwBOV6ttb9YAE5jXLlpx/g/XFmbZmz3nGaTSdo0vx0i4i8K60gPnIAy" crossorigin="anonymous">
    <script>
      // Lucide icons for bootstrap-table's toolbar (masks defined in assets/icons/table.css)
      $.extend($.fn.bootstrapTable.defaults, {
        iconsPrefix: "lucide-i",
        formatToggleOn: function () { return "Grid view"; },
        formatToggleOff: function () { return "List view"; },
        icons: {
          refresh: "icon-refresh-cw", toggleOff: "icon-layout-grid", toggleOn: "icon-list",
          columns: "icon-columns-3", search: "icon-search", clearSearch: "icon-trash-2",
          detailOpen: "icon-plus", detailClose: "icon-minus", fullscreen: "icon-maximize",
          paginationSwitchDown: "icon-chevron-down", paginationSwitchUp: "icon-chevron-up"
        }
      });
    </script>
    <link rel="stylesheet" href="assets/icons/table.css?v=<?= @filemtime(__DIR__ . '/assets/icons/table.css') ?>">

    <script src="https://unpkg.com/dropzone@6.3.5/dist/dropzone-min.js" integrity="sha384-wHTterC8uaPhGVKg8kmSlqcmI8CCFkOI+Mn51O3udobTBX0vQLs6a18vJqh0AnW3" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://unpkg.com/dropzone@6.3.5/dist/dropzone.css" integrity="sha384-asD8pW//1IYJd67ZZn/Eyo31U/YtixrhsNyn90jnZTEIgTpYfE2mkwGYVWHwg2vD" crossorigin="anonymous">

    <link rel="stylesheet" href="style.css?v=<?= @filemtime(__DIR__ . '/style.css') ?>">
    <link rel="stylesheet" href="themes.css">
    <style id="themeVars"><?= $theme["css"] ?></style>
    <?= $theme["script"] ?>
  </head>
<?php
# Player bar look (see "Player bar" in the settings); js.php previews changes live.
$playerClasses = [
  getConfig('player_position') === 'top' ? 'player-top' : 'player-bottom',
  getConfig('playlist_nav') === 'top' ? 'nav-top' : 'nav-sidebar',
  'player-size-' . preg_replace('/[^a-z]/', '', (string) getConfig('player_size')),
  'player-progress-' . preg_replace('/[^a-z]/', '', (string) getConfig('player_progress')),
];
if (!getConfig('player_show_cover')) $playerClasses[] = 'player-no-cover';
$shownButtons = (array) getConfig('player_buttons');
foreach (array_keys(getConfig('player_buttons', 'options')) as $btn) {
  if (!in_array($btn, $shownButtons, true)) $playerClasses[] = 'hide-pbtn-' . $btn;
}
?>
<body class="<?= htmlspecialchars(implode(' ', $playerClasses), ENT_QUOTES, 'UTF-8') ?>">
<?php


if (!is_dir(AUDIO_PATH)) {
  mkdir(AUDIO_PATH);
}

$musicFiles = [];
if (is_dir(AUDIO_PATH)) {
  $musicFiles = array_diff(scandir(AUDIO_PATH), array('..', '.'));
}

$columns = [
  "id" => [
    "card"     => false,
    "name"     => "#",
    "sortable" => true,
    "field"    => "id",
    "visible"  => false,
    "align"    => null,
  ],
  "cover" => [
    "name"       => "",
    "field"      => "cover",
    "visible"    => true,
    "align"      => "center",
    "switchable" => false,
    "searchable" => false,
    "width"      => 48,
    "textFormatter" => "coverFormatter",
  ],
  "name" => [
    "name"          => "Name",
    "sortable"      => true,
    "field"         => "name",
    "visible"       => true,
    "align"         => null,
    "textFormatter" => "nameFormatter",
  ],
  "filename" => [
    "card"     => false,
    "name"     => "Filename",
    "sortable" => true,
    "field"    => "filename",
    "visible"  => false,
    "align"    => null,
  ],
  "title" => [
    "name"     => "Title",
    "sortable" => true,
    "field"    => "title",
    "visible"  => false,
    "align"    => null,
    "card"     => false,
  ],
  "artist" => [
    "name"     => "Artist",
    "sortable" => true,
    "field"    => "artist",
    "visible"  => false,
    "align"    => null,
    "card"     => false,
  ],
  "album" => [
    "name"     => "Album",
    "sortable" => true,
    "field"    => "album",
    "visible"  => false,
    "align"    => null,
    "card"     => false,
  ],
  "year" => [
    "name"     => "Year",
    "sortable" => true,
    "field"    => "year",
    "visible"  => false,
    "align"    => null,
    "card"     => false,
  ],
  "duration" => [
    "card"     => false,
    "name"     => "Duration",
    "sortable" => true,
    "field"    => "duration",
    "sorter"   => "durationSorter",
    "visible"  => true,
    "align"    => null,
  ],
  "size" => [
    "card"     => false,
    "name"     => "Size",
    "sortable" => true,
    "field"    => "size",
    "sorter"   => "sizeSorter",
    "visible"  => false,
    "align"    => null,
  ],
  "date" => [
    "card"     => false,
    "name"     => "Date",
    "sortable" => true,
    "field"    => "date",
    "visible"  => false,
    "align"    => null,
  ],
  "favorite" => [
    "name"      => "",
    "field"     => "favorite",
    "visible"   => true,
    "align"     => "center",
    "formatter" => "favoriteFormatter",
  ],
  "queue" => [
    "card"     => false,
    "name"    => "Queue",
    "field"   => "queue",
    "visible" => true,
    "align"   => "center",
  ],
  "more" => [
    "name"      => "",
    "field"     => "more",
    "visible"   => true,
    "align"     => "center",
    "formatter" => "moreFormatter",
  ],
  "download" => [
    "card"     => false,
    "name"    => "Download",
    "field"   => "download",
    "visible" => false,
    "align"   => "center",
  ],
  "delete" => [
    "card"     => false,
    "name"    => "Delete",
    "field"   => "delete",
    "visible" => false,
    "align"   => "center",
  ],
];

/* ──────────────────────────────────── Reload ─────────────────────────────── */
if (isset($_GET['reload'])) {
  ?>
      <script>
          localStorage.clear();
          var url = new URL(window.location.href);
          url.search = '';
          window.history.replaceState({}, document.title, url.toString());
          setTimeout(function() {
          location.reload();
          }, 2000);
      </script>
  <?php 
      exit(alert("Applying changes", "Please wait..."));
  }

/* ─────────────────────────────── apiResponse ────────────────────────────── */
echo '
  <div id="apiResponse" class="toast-container position-fixed top-0 end-0 p-3">
    <!-- Response from API will be displayed here -->
  </div>
';

if (getConfig('fluid-container')) {
  echo '<div class="container-fluid">';
} else {
  echo '<div class="container">';
}

// if (getConfig('env') != 'demo') {
//   echo '<div class="alert alert-danger">Please run <code>composer install</code> to install the required dependencies.</div>';
// }

echo '
<div class="audio-player-container" role="region" aria-label="Player">
  <div class="playerBar">
    <div class="playerLeft">
      <span class="coverThumb playerCover playerSongInfo" role="button" tabindex="0" aria-controls="songInfoPanel" aria-expanded="false" aria-label="Song info (I)" title="Song info (I)"><img id="playerCoverImg" class="coverImg" alt="" hidden></span>
      <div class="playerTitle">
        <div class="playerTitleRow">
          <h3 id="songtitle" class="playerSongInfo m-0 text-success">'.htmlspecialchars(getConfig("no_song_text")).'</h3>
          <button type="button" class="playerFavBtn" style="display:none;" title="Add to Favorites" aria-label="Add to Favorites" aria-pressed="false">'.icon("heart", 1.2, 0).'</button>
        </div>
        <div id="songartist" class="playerArtist playerSongInfo text-secondary" hidden></div>
        <small class="castStatus text-muted" style="display:none;"></small>
        <div class="sessionStatus text-muted small" style="display:none;"></div>
      </div>
      <canvas class="waveform" aria-hidden="true"'.(getConfig('show_waveform') ? '' : ' hidden').'></canvas>
    </div>

    <div class="playerMini">
      <button type="button" class="btn btn-pill btn-success ctrlBtn playPauseBtn" onclick="toggleSong()" disabled aria-label="Play or pause">'.icon("play", filled: true).'</button>
      <button type="button" class="btn btn-pill btn-ghost-secondary ctrlBtn" onclick="nextSong()" disabled aria-label="Next song">'.icon("skip-forward", filled: true).'</button>
      <button type="button" class="btn btn-pill btn-ghost-secondary playerExpandBtn" aria-expanded="false" aria-controls="playerCenter playerRight" aria-label="More player controls" title="More controls">'.icon("chevron-up").'</button>
    </div>

    <div class="playerCenter" id="playerCenter">
      <div class="playerControls">
        <button type="button" class="btn btn-icon btn-pill btn-ghost-secondary toggleShuffleBtn" data-pbtn="shuffle" onclick="toggleShuffle()" title="Shuffle (S)" aria-label="Shuffle">'.icon('shuffle', margin: 0).'</button>
        <button type="button" class="btn btn-icon btn-pill btn-ghost-secondary ctrlBtn stopBtn" data-pbtn="stop" onclick="stopSong()" disabled title="Stop" aria-label="Stop">'.icon('square', filled: true, margin: 0).'</button>
        <button type="button" class="btn btn-icon btn-pill btn-ghost-secondary ctrlBtn" data-pbtn="prevnext" onclick="prevSong()" disabled title="Previous (Shift+←)" aria-label="Previous song">'.icon('skip-back', filled: true, margin: 0).'</button>
        <button type="button" class="btn btn-icon btn-pill btn-success ctrlBtn playPauseBtn playMain" onclick="toggleSong()" disabled title="Play/pause (Space)" aria-label="Play or pause">'.icon("play", filled: true).'</button>
        <button type="button" class="btn btn-icon btn-pill btn-ghost-secondary ctrlBtn" data-pbtn="prevnext" onclick="nextSong()" disabled title="Next (Shift+→)" aria-label="Next song">'.icon('skip-forward', filled: true, margin: 0).'</button>
        <button type="button" class="btn btn-icon btn-pill btn-ghost-secondary toggleLoopBtn" data-pbtn="repeat" onclick="toggleLoop()" title="Repeat (L)" aria-label="Repeat">'.icon('repeat', margin: 0).'</button>
        <button type="button" class="btn btn-icon btn-pill btn-ghost-secondary toggleAutoplayBtn" data-pbtn="autoplay" onclick="toggleAutoplay()" aria-label="Autoplay next song">'.icon('list-end', margin: 0).'</button>
      </div>
      <audio controls style="'.(getConfig('use_legacy_player') ? '' : 'display:none').';width:100%">
        <source id="audioSource" src="" type="audio/mpeg">
        Your browser does not support the audio element.
      </audio>';
      if (!getConfig('use_legacy_player')) {
        echo '
      <div class="playerProgress">
        <span class="audioCurrentTime">0:00</span>
        <input type="range" class="form-range audioSlider" min="0" max="0" step="1" value="0" aria-label="Seek">
        <span class="audioDuration">0:00</span>
      </div>';
      }
echo '
    </div>

    <div class="playerRight" id="playerRight">';
      if (!getConfig('use_legacy_player')) {
        echo '
      <div class="volumeGroup" data-pbtn="volume">
        <label for="volumeSlider" class="volumeIcon form-label mb-0">'.icon("volume-1", 2).'</label>
        <input type="range" class="form-range" id="volumeSlider" min="0" max="1" step="0.01" value="'.getConfig('default_volume').'" aria-label="Volume">
      </div>';
      }
echo '
      <button type="button" class="btn btn-icon btn-pill btn-ghost-secondary lyricsToggleBtn" data-pbtn="lyrics" aria-controls="lyricsPanel" aria-expanded="false" aria-label="Lyrics" title="Lyrics (Y)">'.icon('mic-vocal', margin: 0).'</button>
      <button type="button" class="btn btn-icon btn-pill btn-ghost-secondary queueToggleBtn" data-pbtn="queue" aria-controls="queuePanel" aria-expanded="false" aria-label="Queue" title="Queue (Q)">'.icon('list-music', margin: 0).'</button>
      <span class="castGroup" data-pbtn="cast" style="display:none;">
        <button type="button" class="btn btn-icon btn-pill btn-ghost-secondary castBtn" title="Cast to a speaker" aria-label="Cast">'.icon('cast', margin: 0).'</button>
      </span>
    </div>
  </div>
</div>
';

echo '
<aside id="queuePanel" class="queuePanel card" aria-label="Queue" hidden>
  <div class="queuePanelHead">
    <h3 class="m-0">Queue</h3>
    <button type="button" class="btn btn-sm btn-pill btn-ghost-secondary queuePanelClose" aria-label="Close the queue" title="Close (Q)">'.icon('x', margin: 0).'</button>
  </div>
  <div class="queuePanelBody">
    <section class="qpSection" aria-labelledby="qpNowTitle">
      <h4 id="qpNowTitle" class="qpTitle">Now playing</h4>
      <ul class="qpList" id="qpNow"></ul>
    </section>
    <section class="qpSection qpQueueSection" aria-labelledby="qpQueueTitle">
      <div class="qpTitleRow">
        <h4 id="qpQueueTitle" class="qpTitle">Next in queue</h4>
        <button type="button" class="btn btn-sm btn-pill btn-ghost-secondary qpClear">Clear</button>
      </div>
      <ul class="qpList" id="qpQueue"></ul>
    </section>
    <section class="qpSection" aria-labelledby="qpContextTitle">
      <h4 id="qpContextTitle" class="qpTitle">Next from: <span class="qpContextName"></span></h4>
      <p class="qpHint text-secondary small" hidden></p>
      <ul class="qpList" id="qpContext"></ul>
    </section>
  </div>
</aside>
';

echo '
<aside id="lyricsPanel" class="queuePanel lyricsPanel card" aria-label="Lyrics" hidden>
  <div class="queuePanelHead">
    <div class="lyricsHeadText">
      <h3 class="m-0">Lyrics</h3>
      <div class="lyricsSource text-secondary small"></div>
    </div>
    <button type="button" class="btn btn-sm btn-pill btn-ghost-secondary lyricsPanelClose" aria-label="Close the lyrics" title="Close (Y)">'.icon('x', margin: 0).'</button>
  </div>
  <div class="queuePanelBody lyricsBody" tabindex="-1" aria-live="polite"></div>
</aside>
';

echo '
<aside id="songInfoPanel" class="queuePanel songInfoPanel card" aria-label="Song info" hidden>
  <div class="queuePanelHead">
    <h3 class="m-0 songInfoHeading">Song info</h3>
    <button type="button" class="btn btn-sm btn-pill btn-ghost-secondary songInfoClose" aria-label="Close the song info" title="Close (I)">'.icon('x', margin: 0).'</button>
  </div>
  <div class="queuePanelBody songInfoBody"></div>
</aside>
';

$navSidebar = getConfig('playlist_nav') !== 'top';
echo '<div class="appLayout">';
if ($navSidebar) {
  echo '
<aside class="sideNav card m-3" aria-label="Playlists">
  <div class="sideNavTitle">Your library</div>
  <div id="playlistBar" class="playlistBar"></div>
</aside>';
}
echo '
<div class="card m-3 mainCard">
  <div class="card-header d-flex justify-content-between">
      <div>
        <h2><span class="siteTitle">'.getConfig("site_title").'</span> <small class="text-muted m-2"><a href="'.ENV['URL'].'" target="_blank">'.ENV['VERSION'].'</a></small></h2>
      </div>
      <div class="d-flex align-items-center gap-2">
      <div class="btn-group quickButtons"'.(getConfig('show_quick_buttons') ? '' : ' style="display:none;"').'>
        <button type="button" class="btn btn-sm btn-pill btn-outline-success" onclick="playFirst()" title="Play the first song">'.icon('play', filled: true).'<span class="d-none d-md-inline">Play first</span></button>
        <button type="button" class="btn btn-sm btn-pill btn-outline-success" onclick="randomSong(true)" title="Play a random song">'.icon('dice-'.mt_rand(1,6)).'<span class="d-none d-md-inline">Random</span></button>
      </div>
      <div class="btn-group">
        <button type="button" class="btn btn-sm btn-pill btn-primary configBtn" data-bs-toggle="modal" data-bs-target="#configModal">
          '.icon("settings").'
        </button>
        <button type="button" class="btn btn-sm btn-pill btn-primary" data-bs-toggle="modal" data-bs-target="#sessionModal" title="Listen together">
          '.icon("users").'
          <span class="badge bg-green text-green-fg sessionBadge ms-1" style="display:none;"></span>
        </button>
      </div>
    </div>
  </div>
<div class="card-body">
';

if (getConfig('show_upload')) {
  echo '
<div id="musicDropzone" class="border border-secondary align-items-center p-3">
      <h3>Upload Music</h3>
      <a href="javascript:void(0);" class="dropzoneSelect text-muted">Drag and drop MP3 files here or click to upload.</a>
</div>
<div id="dropzoneResponse" class="my-3">
  <!-- Response from Dropzone will be displayed here -->
</div>
';
}

$sortColumn = getConfig('default_sort');
$sortAttrs  = '';
if (in_array($sortColumn, ['name', 'duration', 'size', 'date'], true)) {
  $sortAttrs = ' data-sort-name="'.$sortColumn.'" data-sort-order="'.(getConfig('default_sort_order') === 'desc' ? 'desc' : 'asc').'"';
}



if (empty($musicFiles)) {
    echo '<p>No music files found.</p>';
    echo '<p>Upload some music files to the <code>'.getConfig("audio_path").'/</code> directory.</p>';
}
if (!$navSidebar) {
  echo '<div id="playlistBar" class="playlistBar" aria-label="Playlists"></div>';
}
echo '
<div id="playlistHeader" class="playlistHeader" style="display:none;"></div>
<div id="toolbar"></div>
<table id="playlistTable" data-toolbar="#toolbar" class="table table-striped'.(getConfig('compact_rows') ? ' table-sm' : '').'"'.$sortAttrs.'
  data-toggle="table" 
  data-search="true"  
  data-show-refresh="true"
  data-show-toggle="true"
  data-show-columns="true"
  data-show-columns-toggle-all="true"
  data-show-export="true"
  data-unique-id="id"
  data-escape="false"
>
  <thead id="playlistHead">
  <tr>
  ';
  foreach ($columns as $key => $column) {
    $visible  = isset($column['visible']) && $column['visible'] ? 'true' : 'false';
    $sortable = isset($column['sortable']) && $column['sortable'] ? 'true' : '';
    $align    = isset($column['align']) ? $column['align'] : '';
    $name     = isset($column['name']) ? htmlspecialchars($column['name']) : '';
    $sorter   = isset($column['sorter']) ? ' data-sorter="'.$column['sorter'].'"' : '';
    $sorter  .= isset($column['formatter']) ? ' data-formatter="'.$column['formatter'].'" data-switchable="false" data-width="44"' : '';
    $sorter  .= isset($column['switchable']) && !$column['switchable'] && !isset($column['formatter']) ? ' data-switchable="false"' : '';
    $sorter  .= isset($column['searchable']) && !$column['searchable'] ? ' data-searchable="false"' : '';
    $sorter  .= isset($column['card']) && !$column['card'] ? ' data-card-visible="false"' : '';
    $sorter  .= isset($column['width']) ? ' data-width="'.(int) $column['width'].'"' : '';
    // A formatter for a text column: search still matches the plain value, not the markup
    $sorter  .= isset($column['textFormatter']) ? ' data-formatter="'.$column['textFormatter'].'" data-search-formatter="false"' : '';
    echo '<th 
      data-sortable="'.$sortable.'"
      data-align="'.$align.'"
      data-field="'.$key.'"'.$sorter.'
      data-visible="'.$visible.'"
      data-sortable="'.$sortable.'"
      data-align="'.$align.'">
        '.$name.'</th>';
  }
    // <th data-sortable="true" data-field="id" data-visible="false">#</th>
    // <th data-sortable="true" data-field="name">Name</th>
    // <th data-sortable="true" data-field="filename" data-visible="false">Filename</th>
    // <th data-sortable="true" data-field="duration">Duration</th>
    // <th data-sortable="true" data-field="size" data-visible="false">Size</th>
    // <th data-sortable="true" data-field="date" data-visible="false">Date</th>
    // <th data-field="queue" data-align="center">Queue</th>
    // <th data-field="download" data-visible="false" data-align="center">Download</th>
    // <th data-field="delete" data-visible="false" data-align="center">Delete</th>
  echo '</tr>
  <tbody id="playlistBody">
  ';
$i = 1;
foreach ($musicFiles as $file) {
    $urlFilename = urlencode($file);
    $filePath    = getConfig("audio_path") . "/" . $file;
    if (!in_array(pathinfo($filePath, PATHINFO_EXTENSION), getConfig('allowed_types'))) {
      continue;
    }

    if (!getConfig("include_file_extension")) {
      $audioName = pathinfo($file, PATHINFO_FILENAME);
    } else {
      $audioName = pathinfo($file, PATHINFO_FILENAME) . "." . pathinfo($file, PATHINFO_EXTENSION);
    }
    # Cover thumbnails are fetched lazily from cover.php; nothing is extracted here.
    $mtime    = filemtime($filePath);
    $meta     = getSongMeta($filePath);  # ID3 tags + duration, cached in cache/meta/
    $coverSrc = 'cover.php?file='.rawurlencode($file).'&amp;size=sm&amp;v='.songCoverVersion($filePath);
    # TODO: Fix `data-value` attribute
    echo '
    <tr class="songrow hover-pointer" data-filename="'.$audioName.'">
      <td class="text-muted">
        '.($i).'
      </td>
      <td class="coverCell"><span class="coverThumb"><img class="coverImg" src="'.$coverSrc.'" alt="" width="40" height="40" loading="lazy" decoding="async" onerror="this.hidden=true"><span class="coverPlay" aria-hidden="true">'.icon('play', margin: 0, filled: true).icon('pause', margin: 0, filled: true).'</span></span></td>
      <td>
        '.htmlspecialchars($audioName).'
      </td>
      <td>
        '.$urlFilename.'
      </td>
      <td>'.htmlspecialchars($meta['title']).'</td>
      <td>'.htmlspecialchars($meta['artist']).'</td>
      <td>'.htmlspecialchars($meta['album']).'</td>
      <td>'.htmlspecialchars($meta['year']).'</td>
      <td class="durationCol">'.$meta['duration'].'</td>
      <td class="sizeCol">'.round(filesize($filePath) / 1024 / 1024).'MB</td>
      <td class="dateCol">'.date("Y-m-d H:i:s", $mtime).'</td>
      <td class="action"></td>
      <td class="action"><a href="javascript:void(0);" data-filename="' . $urlFilename . '" class="link-primary queueBtn">'.icon('circle-plus', margin: 0).'</a></td>
      <td class="action"></td>
      <td class="action"><a href="javascript:void(0);" data-filename="' . $urlFilename . '" class="link-success downloadBtn">'.icon('download', margin: 0).'</a></td>
      <td class="action"><a href="javascript:void(0);" data-filename="' . $urlFilename . '" class="link-danger deleteBtn">'.icon('trash-2', margin: 0).'</a></td>
    </tr>';
    $i++;
}
echo '</tbody></table>';
echo '</div></div>';
echo '</div>'; // .appLayout
?>


</div>
</body>

<?php require_once('modals.php'); ?>
<?php require_once('js.php'); ?>
<script src="audiograph.js?v=<?= @filemtime(__DIR__ . '/audiograph.js') ?>"></script>
<script src="waveform.js?v=<?= @filemtime(__DIR__ . '/waveform.js') ?>"></script>
<script src="cast.js?v=<?= @filemtime(__DIR__ . '/cast.js') ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js" integrity="sha384-8FWZA6BGMXhsfO+BLtrJK0We6gg5o1JyO8xQm6peWDEUs17ACA5ziE/NIAkl9z2k" crossorigin="anonymous"></script>
<script src="session.js?v=<?= @filemtime(__DIR__ . '/session.js') ?>"></script>
<script src="playlists.js?v=<?= @filemtime(__DIR__ . '/playlists.js') ?>"></script>
<script src="queue.js?v=<?= @filemtime(__DIR__ . '/queue.js') ?>"></script>
<script src="lyrics.js?v=<?= @filemtime(__DIR__ . '/lyrics.js') ?>"></script>
<script src="songinfo.js?v=<?= @filemtime(__DIR__ . '/songinfo.js') ?>"></script>
<script src="songedit.js?v=<?= @filemtime(__DIR__ . '/songedit.js') ?>"></script>
<script src="https://www.gstatic.com/cv/js/sender/v1/cast_sender.js?loadCastFramework=1" async></script>

</html>
