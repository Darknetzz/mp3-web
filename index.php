<?php 
require_once('_includes.php');

$musicFiles = array_diff(scandir(AUDIO_PATH), array('..', '.'));
?>

<!DOCTYPE html>
<html lang="en">
  <head>

    <meta charset="utf-8">
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <title><?= getConfig("site_title") ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta21/dist/js/tabler.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta21/dist/css/tabler.min.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <script src=" https://cdn.jsdelivr.net/npm/bootstrap-table@1.23.5/dist/bootstrap-table.min.js "></script>
    <link href=" https://cdn.jsdelivr.net/npm/bootstrap-table@1.23.5/dist/bootstrap-table.min.css " rel="stylesheet">

    <script src="https://unpkg.com/dropzone@6.0.0-beta.2/dist/dropzone-min.js"></script>
    <link href="https://unpkg.com/dropzone@6.0.0-beta.2/dist/dropzone.css" rel="stylesheet" type="text/css" />

    <link rel="stylesheet" href="style.css">
  </head>
<body data-bs-theme="dark">
<?php


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

echo '
<div class="container">
';

// if (getConfig('env') != 'demo') {
//   echo '<div class="alert alert-danger">Please run <code>composer install</code> to install the required dependencies.</div>';
// }

echo '
<div class="audio-player-container">
  <div class="d-flex align-items-center card">
      <h3 id="songtitle" class="card-header text-success">'.getConfig("no_song_text").'</h3>
      <h5 class="card-header text-muted" style="display:none;">Next in queue: <span id="nextInQueueText" class="mx-2"></span></h5>
      <div class="card-body">
          <audio '.(getConfig('use_legacy_player') ? 'controls' : '').' style="width:100%">
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
              <label for="volumeSlider" class="volumeIcon form-label mb-0 me-2">'.icon("volume-down-fill", 2).'</label>
              <input type="range" class="form-range" id="volumeSlider" min="0" max="1" step="0.01" value="'.getConfig('default_volume').'">
            </div>
            ';
          }
echo '
          <div class="btn-group mx-2">
            <button class="btn btn-sm btn-pill btn-success ctrlBtn playPauseBtn" onclick="toggleSong()" disabled>'.icon("play").'</button>
            <button class="btn btn-sm btn-pill btn-outline-danger ctrlBtn stopBtn" onclick="stopSong()" disabled>'.icon('stop-fill').'</button>
          </div>
          <div class="btn-group mx-2">
            <button class="btn btn-sm btn-pill btn-outline-primary ctrlBtn" onclick="prevSong()" disabled>'.icon('skip-backward-fill').'</button>
            <button class="btn btn-sm btn-pill btn-outline-primary ctrlBtn" onclick="nextSong()" disabled>'.icon('skip-forward-fill').'</button>
          </div>
          <div class="btn-group mx-2">
            <button class="btn btn-sm btn-pill btn-outline-primary toggleLoopBtn" onclick="toggleLoop()">'.icon('arrow-repeat').'</button>
            <button class="btn btn-sm btn-pill btn-outline-primary toggleShuffleBtn" onclick="toggleShuffle()">'.icon('shuffle').'</button>
          </div>
          <div class="btn-group mx-2">
            <button class="btn btn-sm btn-pill btn-outline-success" onclick="playSong(0)">'.icon('play-fill').' Play First Song</button>
            <button class="btn btn-sm btn-pill btn-outline-success" onclick="randomSong()">'.icon('dice-'.mt_rand(1,6)).' Random Song</button>
          </div>
        </div>
  </div>
</div>
';

echo '
<div class="card m-3">
  <div class="card-header d-flex justify-content-between">
      <div>
        <h2>'.getConfig("site_title").' <small class="text-muted m-2">'.ENV['VERSION'].'</small></h2>
      </div>
      <div class="btn-group">
        <button type="button" class="btn btn-sm btn-pill btn-primary configBtn" data-bs-toggle="modal" data-bs-target="#configModal">
          '.icon("gear").'
        </button>
        <button type="button" class="btn btn-sm btn-pill btn-primary" data-bs-toggle="modal" data-bs-target="#sessionModal">
          '.icon("people").'
        </button>
    </div>
  </div>
<div class="card-body">

<div id="musicDropzone" class="border border-secondary align-items-center p-3">
      <h3>Upload Music</h3>
      <a href="javascript:void(0);" class="dropzoneSelect text-muted">Drag and drop MP3 files here or click to upload.</a>
</div>
<div id="dropzoneResponse" class="my-3">
  <!-- Response from Dropzone will be displayed here -->
</div>
';



if (empty($musicFiles)) {
    echo '<p>No music files found.</p>';
    echo '<p>Upload some music files to the <code>'.getConfig("audio_path").'/</code> directory.</p>';
}
echo '
<div id="toolbar"></div>
<table id="playlistTable" data-toolbar="#toolbar" class="table table-striped" 
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
    <th data-sortable="true" data-field="id" data-visible="false">#</th>
    <th data-sortable="true" data-field="name">Name</th>
    <th data-sortable="true" data-field="filename" data-visible="false">Filename</th>
    <th data-sortable="true" data-field="duration">Duration</th>
    <th data-sortable="true" data-field="size" data-visible="false">Size</th>
    <th data-sortable="true" data-field="date" data-visible="false">Date</th>
    <th data-field="queue">Queue</th>
    <th data-field="download">Download</th>
    <th data-field="delete" data-visible="false">Delete</th>
  </tr>
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
    # TODO: Fix `data-value` attribute
    echo '
    <tr class="songrow" data-filename="'.$audioName.'">
      <td class="text-muted">
        '.($i).'
      </td>
      <td>
        '.htmlspecialchars($audioName).'
      </td>
      <td>
        '.$urlFilename.'
      </td>
      <td class="durationCol">'.getDuration($filePath).'</td>
      <td class="sizeCol">'.round(filesize($filePath) / 1024 / 1024).'MB</td>
      <td class="dateCol">'.date("Y-m-d H:i:s", filemtime($filePath)).'</td>
      <td class="action"><a href="javascript:void(0);" data-filename="' . $urlFilename . '" class="link-primary queueBtn">'.icon('music-note-list', margin: 0).'</a></td>
      <td class="action"><a href="javascript:void(0);" data-filename="' . $urlFilename . '" class="link-success downloadBtn">'.icon('download', margin: 0).'</a></td>
      <td class="action"><a href="javascript:void(0);" data-filename="' . $urlFilename . '" class="link-danger deleteBtn">'.icon('trash-fill', margin: 0).'</a></td>
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

</html>
