<?php 
require_once('_includes.php');
$theme = themeSetup();
?>

<!DOCTYPE html>
<html lang="en"<?= $theme["attributes"] ?>>
  <head>

    <meta charset="utf-8">
    <link rel="icon" href="favicon.ico" type="image/x-icon">
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
        icons: {
          refresh: "icon-refresh-cw", toggleOff: "icon-toggle-left", toggleOn: "icon-toggle-right",
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
    <?php if ($theme["css"] !== "") { echo "<style>" . $theme["css"] . "</style>"; } ?>
    <?= $theme["script"] ?>
  </head>
<body class="<?= getConfig('player_position') === 'top' ? 'player-top' : 'player-bottom' ?>">
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
  ],
  "name" => [
    "name"     => "Name",
    "sortable" => true,
    "field"    => "name",
    "visible"  => true,
    "align"    => null,
  ],
  "filename" => [
    "name"     => "Filename",
    "sortable" => true,
    "field"    => "filename",
    "visible"  => false,
    "align"    => null,
  ],
  "duration" => [
    "name"     => "Duration",
    "sortable" => true,
    "field"    => "duration",
    "sorter"   => "durationSorter",
    "visible"  => true,
    "align"    => null,
  ],
  "size" => [
    "name"     => "Size",
    "sortable" => true,
    "field"    => "size",
    "sorter"   => "sizeSorter",
    "visible"  => false,
    "align"    => null,
  ],
  "date" => [
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
    "name"    => "Download",
    "field"   => "download",
    "visible" => false,
    "align"   => "center",
  ],
  "delete" => [
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
<div class="audio-player-container px-3" style="opacity: '.(float) getConfig('player_opacity').';">
  <div class="d-flex align-items-center card border border-success">
      <div class="card-header playerHeader">
        <span class="coverThumb playerCover"><img id="playerCoverImg" class="coverImg" alt="" hidden></span>
        '.(getConfig('show_waveform') ? '<canvas class="waveform" aria-hidden="true"></canvas>' : '').'

        <div class="text-center">
          <div class="d-flex align-items-center justify-content-center gap-2">
          <button type="button" class="playerFavBtn" style="display:none;" title="Add to Favorites" aria-label="Add to Favorites" aria-pressed="false">'.icon("heart", 1.2, 0).'</button>
          <h3 id="songtitle" class="m-0 text-success">'.htmlspecialchars(getConfig("no_song_text")).'</h3>
          </div>
          <small class="castStatus text-muted" style="display:none;"></small>
          <div class="sessionStatus text-muted small" style="display:none;"></div>
        </div>
      </div>
      <h5 class="card-header text-muted" style="display:none;">Next in queue: <span id="nextInQueueText" class="mx-2"></span></h5>
      <div class="card-body">
          <audio controls style="'.(getConfig('use_legacy_player') ? '' : 'display:none').';width:100%">
          <source id="audioSource" src="" type="audio/mpeg">
          Your browser does not support the audio element.
          </audio>';

          if (!getConfig('use_legacy_player')) {
            echo '
            <div class="d-flex align-items-center">
              <span class="audioCurrentTime">0:00</span>
              <input type="range" class="form-range audioSlider mx-2" min="0" max="0" step="1" value="0" style="flex: 3;">
              <span class="audioDuration">0:00</span>
            </div>
            
            <div class="btn-group mx-2 align-items-center">
              <label for="volumeSlider" class="volumeIcon form-label mb-0 me-2">'.icon("volume-1", 2).'</label>
              <input type="range" class="form-range" id="volumeSlider" min="0" max="1" step="0.01" value="'.getConfig('default_volume').'">
            </div>
            ';
          }
echo '
          <div class="btn-group mx-2">
            <button class="btn btn-sm btn-pill btn-success ctrlBtn playPauseBtn" onclick="toggleSong()" disabled>'.icon("play", filled: true).'</button>
            <button class="btn btn-sm btn-pill btn-outline-danger ctrlBtn stopBtn" onclick="stopSong()" disabled>'.icon('square', filled: true).'</button>
          </div>
          <div class="btn-group mx-2">
            <button class="btn btn-sm btn-pill btn-outline-primary ctrlBtn" onclick="prevSong()" disabled>'.icon('skip-back', filled: true).'</button>
            <button class="btn btn-sm btn-pill btn-outline-primary ctrlBtn" onclick="nextSong()" disabled>'.icon('skip-forward', filled: true).'</button>
          </div>
          <div class="btn-group mx-2">
            <button class="btn btn-sm btn-pill btn-outline-primary toggleLoopBtn" onclick="toggleLoop()">'.icon('repeat').'</button>
            <button class="btn btn-sm btn-pill btn-outline-primary toggleShuffleBtn" onclick="toggleShuffle()">'.icon('shuffle').'</button>
            <button type="button" class="btn btn-sm btn-pill btn-outline-primary toggleAutoplayBtn" onclick="toggleAutoplay()" aria-label="Autoplay next song">'.icon('list-end').'</button>
          </div>
          <div class="btn-group mx-2 castGroup" style="display:none;">
            <button type="button" class="btn btn-sm btn-pill btn-outline-primary castBtn" title="Cast to a speaker" aria-label="Cast">'.icon('cast').'</button>
          </div>
          <div class="btn-group mx-2 quickButtons"'.(getConfig('show_quick_buttons') ? '' : ' style="display:none;"').'>
            <button class="btn btn-sm btn-pill btn-outline-success" onclick="playFirst()">'.icon('play', filled: true).' Play First Song</button>
            <button class="btn btn-sm btn-pill btn-outline-success" onclick="randomSong(true)">'.icon('dice-'.mt_rand(1,6)).' Random Song</button>
          </div>
        </div>
  </div>
</div>
';

echo '
<div class="card m-3">
  <div class="card-header d-flex justify-content-between">
      <div>
        <h2>'.getConfig("site_title").' <small class="text-muted m-2"><a href="'.ENV['URL'].'" target="_blank">'.ENV['VERSION'].'</a></small></h2>
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
echo '
<div id="playlistBar" class="playlistBar" aria-label="Playlists"></div>
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
    $sorter  .= isset($column['width']) ? ' data-width="'.(int) $column['width'].'"' : '';
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
    $coverSrc = 'cover.php?file='.rawurlencode($file).'&amp;size=sm&amp;v='.$mtime;
    # TODO: Fix `data-value` attribute
    echo '
    <tr class="songrow hover-pointer" data-filename="'.$audioName.'">
      <td class="text-muted">
        '.($i).'
      </td>
      <td class="coverCell"><span class="coverThumb"><img class="coverImg" src="'.$coverSrc.'" alt="" width="40" height="40" loading="lazy" decoding="async" onerror="this.hidden=true"><span class="coverPlay" aria-hidden="true">'.icon('play', margin: 0, filled: true).'</span></span></td>
      <td>
        '.htmlspecialchars($audioName).'
      </td>
      <td>
        '.$urlFilename.'
      </td>
      <td class="durationCol">'.getDuration($filePath).'</td>
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
?>


</div>
</body>

<?php require_once('modals.php'); ?>
<?php require_once('js.php'); ?>
<script src="waveform.js?v=<?= @filemtime(__DIR__ . '/waveform.js') ?>"></script>
<script src="cast.js?v=<?= @filemtime(__DIR__ . '/cast.js') ?>"></script>
<script src="session.js?v=<?= @filemtime(__DIR__ . '/session.js') ?>"></script>
<script src="playlists.js?v=<?= @filemtime(__DIR__ . '/playlists.js') ?>"></script>
<script src="https://www.gstatic.com/cv/js/sender/v1/cast_sender.js?loadCastFramework=1" async></script>

</html>
