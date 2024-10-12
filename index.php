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

/* ───────────────────────── Startup (session) modal ──────────────────────── */
echo '
<div class="modal fade" id="sessionModal" tabindex="-1" aria-labelledby="sessionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="sessionModalLabel">Session</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <div class="modal-status bg-primary"></div>
      </div>
      <div class="modal-body">';

      $sessionCards = '<div class="d-flex justify-content-around sessionCards noSessionCards">

      <!-- # NOTE: CREATE SESSION CARD -->
      <div class="card m-2 w-100">
        <div class="card-status-top bg-success"></div>
        <h4 class="card-header text-center">Create session</h4>
        <div class="card-body text-center">

          <a class="btn btn-success m-2 sessionBtn" data-target="#createSessionForm">
              '.icon("person-plus-fill", 2).'
          </a>

          <form class="sessionForm apiForm" id="createSessionForm" style="display:none;" method="POST">
            <input type="hidden" name="action" value="createSession">
            <input type="text" class="form-control m-1 w-100" name="sessionCode" placeholder="Session name (optional)">
            
            <div class="m-2">
              <label>
                <input type="checkbox" class="form-check-input" name="public" id="isPublic" value="1">
                Public
              </label>
            </div>

            <div class="btn-group w-100 m-1">
              <button type="submit" class="btn btn-success m-1">Create Session</button>
              <a href="javascript:void(0);" class="btn btn-secondary m-1 cancelSessionBtn" data-bs-dismiss="modal">Cancel</a>
            </div>
          </form>

          <div class="sessionResponse" id="createSessionResponse" style="display:none;">
            <!-- Response from createSession will be displayed here -->
          </div>

        </div>
      </div>


      <!-- # NOTE: JOIN SESSION CARD -->
      <div class="card m-2 w-100">
        <div class="card-status-top bg-primary"></div>
        <h4 class="card-header text-center">Join session</h4>
        <div class="card-body text-center">

          <a class="btn btn-primary m-2 sessionBtn" data-target="#joinSessionForm">
              '.icon("people-fill", 2).'
          </a>

          <form class="sessionForm apiForm" id="joinSessionForm" style="display:none;" method="POST">
              <input type="text" class="form-control m-1 w-100" name="sessionCode" placeholder="Session code">
              <div class="btn-group w-100 m-1">
                <button type="submit" class="btn btn-primary m-1">Join Session</button>
                <a href="javascript:void(0);" class="btn btn-secondary m-1 cancelSessionBtn" data-bs-dismiss="modal">Cancel</a>
              </div>
          </form>

          <div class="sessionResponse" id="joinSessionResponse" style="display:none;">
            <!-- Response from joinSession will be displayed here -->
          </div>

        </div>
      </div>

    </div>';

    // <!-- # NOTE: CANCEL MODAL
    // <div class="card m-2 w-100">
    //   <h4 class="card-header text-center">Listen alone</h4>
    //   <div class="card-status-top bg-secondary"></div>
    //   <div class="card-body text-center">
    //     <a class="btn btn-secondary m-2 sessionActionBtn" data-bs-dismiss="modal">
    //         '.icon("person-x-fill", 2).'
    //     </a>
    //   </div>
    // </div>
    // -->

    if (!empty($_SESSION['session_code'])) {
      $sessionCards .= '
      <div class="d-flex justify-content-around sessionCards hasSessionCards">
        <div class="card m-2 w-100">
          <h4 class="card-header text-center">Session</h4>
          <div class="card-body text-center">
            <p class="text-muted"> ' . $_SESSION['session_code'] . ' </p>
            <a href="api.php?action=leaveSession" class="btn btn-danger m-2">Leave session</a>
          </div>
        </div>
      </div>';
      }

      echo $sessionCards;

echo '
      </div>
    </div>
  </div>
</div>
';

/* ────────────────────────────── Config Modal ────────────────────────────── */
echo '
<div class="modal fade" id="configModal" tabindex="-1" aria-labelledby="configModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="configModalLabel">Configuration</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <div class="modal-status bg-primary"></div>
      </div>
      <div class="modal-body">
      <form action="api.php" method="POST">
      <div class="modalTable">
      <table class="table table-bordered">
        <thead>
          <tr>
            <th colspan="100%" class="text-bg-secondary text-center">'.CONFIG_FILE.'</th>
          </tr>
        </thead>
        <tbody>';
      foreach (CONFIG as $key => $values) {
        $name          = $values["name"];
        $value         = $values["value"];
        $description   = $values["description"];
        $type          = $values["type"];
        $attributes    = $values["attributes"] ?? [];
        $inputData     = "data-key='".$key."'";
        $cfgInputClass = "settingInput";
        $badgeClass    = "badge text-bg-primary";
        
        # String
        if ($type == "string") {
          $badgeClass = "badge text-bg-secondary";
          $input      = '<textarea class="autoheight form-control '.$cfgInputClass.'" '.$inputData.'>'.$value.'</textarea>';
        }

        # Array
        if ($type == "array") {
          $badgeClass = "badge text-bg-info";
          $value      = json_encode($value, JSON_PRETTY_PRINT);
          $input      = '<textarea class="autoheight form-control '.$cfgInputClass.'" '.$inputData.'>'.$value.'</textarea>';
        }

        # Boolean
        if ($type == "bool") {
          $badgeClass = "badge text-bg-azure";
          if ($value === "true" || $value === 1) {
            $value = True;
          } elseif ($value === "false" || $value === 0 || empty($value)) {
            $value = False;
          }
          $input = '
            <div class="form-check form-switch">
              <input class="form-check-input '.$cfgInputClass.'" type="checkbox" '.($value ? 'checked' : '').' '.$inputData.'>
            </div>';
        }

        # Range
        if ($type == "range") {
          $badgeClass = "badge text-bg-warning";
          $value      = $value ?? 0;
          $min        = $attributes["min"] ?? 0;
          $max        = $attributes["max"] ?? 1;
          $step       = $attributes["step"] ?? .1;
          $input      = '
            <input class="form-range '.$cfgInputClass.' settingRange" data-valueobject="'.$key.'-val" type="range" value="'.$value.'" min="'.$min.'" max="'.$max.'" step="'.$step.'" '.$inputData.'>
            <output for="'.$key.'" class="form-label" id="'.$key.'-val">'.($value * 100).'%</output>
          ';
        }

        # Selection
        if ($type == "selection") {
          $badgeClass = "badge text-bg-success";
          $options = $values["options"];
          $input = '<select class="form-select '.$cfgInputClass.'" '.$inputData.'>';
          if (is_array($options)) {
            foreach ($options as $optionValue => $optionName) {
              $selected  = ($optionValue == $value) ? 'selected' : '';
              $input    .= '<option value="'.$optionValue.'" '.$selected.'>'.$optionName.'</option>';
            }
          } else {
            $selected = Null;
            $input = "Invalid selection (not an array)";
          }
          $input .= '</select>';
        }
        echo '
        <tr>
            <td class="text-primary">
            <div class="d-flex justify-content-between">
              <div>
                <label for="'.$key.'" class="form-label">'.$name.'</label>
                <small class="text-muted">'.$key.': '.$description.'</small>
              </div>
              <div>
                <span class="'.$badgeClass.'">'.$type.'</span>
              </div>
            </div>
            </td>
          <td>
            '.$input.'
          </td>
        </tr>';
      }
echo '</tbody>
      </table>
      </div>
      </form>
      <div class="d-flex justify-content-end">
        <a href="javascript:void(0);" class="btn btn-outline-secondary m-1" data-bs-dismiss="modal">Close</a>
        <a href="?reload=1" class="btn btn-success m-1 reloadCfgBtn" style="display:none;">Reload page to apply changes</a>
      </div>
      </div>
    </div>
  </div>
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

<!--
/* ────────────────────────────────────────────────────────────────────────── */
/*                                 JAVASCRIPT                                 */
/* ────────────────────────────────────────────────────────────────────────── */
-->
<script>
  var playlistTable         = $('#playlistTable');
  var pauseIcon             = "⏸ ";
  var playIcon              = "⏵ ";
  var pauseIconHTML         = '<?= icon('pause-fill') ?>';
  var playIconHTML          = '<?= icon('play-fill') ?>';
  var playing               = false;
  var shuffle               = false;
  var loop                  = false;
  var duration              = 0;
  var currentTime           = 0;
  var currentIndex          = 0;
  var apiURL                = "api.php";
  var queue                 = [];
  var activeClass           = "table-active text-success";
  var warningClass          = "text-warning border border-warning is-warning";
  var successClass          = "text-success border border-success is-valid";

  /* ────────────────────────── FUNCTION: setWarning ────────────────────────── */
  function setWarning(selectorOrElement) {
    var element = (typeof selectorOrElement === 'string') ? $(selectorOrElement) : selectorOrElement;
    element.addClass(warningClass);
    element.removeClass(successClass);
  }

  /* ────────────────────────── FUNCTION: setSuccess ────────────────────────── */
  function setSuccess(selectorOrElement) {
    var element = (typeof selectorOrElement === 'string') ? $(selectorOrElement) : selectorOrElement;
    element.addClass(successClass);
    element.removeClass(warningClass);
  }

  /* ────────────────────────── FUNCTION: toggleFade ────────────────────────── */
  function toggleFade(selectorOrElement) {
    var element = (typeof selectorOrElement === 'string') ? $(selectorOrElement) : selectorOrElement;
    if ($(element).is(":visible")) {
      $(element).fadeOut();
      console.log("Fading out: " + element);
      return;
    }
    console.log("Fading in: " + element);
    element.fadeIn();
  }

  /* ─────────────────────────── FUNCTION: getSongNameByIndex ─────────────────── */
  function urldecode(str) {
    return decodeURIComponent((str + '').replace(/\+/g, '%20'));
  }

  /* ─────────────────────────── FUNCTION: getSongNameByIndex ─────────────────── */
  function getSongNameByIndex(index) {
    var songName_decoded = urldecode(playlistTable.bootstrapTable('getRowByUniqueId', index).filename);
    console.log("getSongNameByIndex: " + index + " => " + songName_decoded);
    return songName_decoded;
  }

  /* ──────────────────────── FUNCTION: getSongByIndex ──────────────────────── */
  function getSongByIndex(index) {
    var row = playlistTable.bootstrapTable('getRowByUniqueId', index);
    console.log("getSongByIndex: " + index + " => " + row.name);
    return row;
  }

  /* ────────────────────────── FUNCTION: updateTitle ───────────────────────── */
  function updateTitle(title) {
    if (typeof title === "undefined") {
      title = "Music Player";
    }
    if (title.length === 0) {
      title = "No song selected";
    }
    $("#songtitle").text(title);
    if (playing) {
      title = playIcon + title;
    } else {
      title = pauseIcon + title;
    }
    document.title = title;
  }

  /* ────────────────────── FUNCTION: scrollToActiveSong ────────────────────── */
  function scrollToActiveSong() {
    var autoScroll = <?= json_encode(getConfig("auto_scroll")) ?>;
    if (!autoScroll) {
        return;
    }
    $("tr.songrow[data-uniqueid='" + currentIndex + "']")[0].scrollIntoView({ behavior: "smooth", block: "center" });
  }

  /* ─────────────────────────── FUNCTION: playSong ─────────────────────────── */
  function playSong(index) {
    console.log("Playing song at index: " + index);
    $("tr.songrow").removeClass(activeClass).children().removeClass(activeClass);
    var tableData  = playlistTable.bootstrapTable('getData');
    var firstIndex = tableData[0].id;
    var lastIndex  = tableData.slice(-1)[0].id;
    console.log("firstIndex: " + firstIndex + ", lastIndex: " + lastIndex);
    if (+index < +firstIndex) {
      console.log("(" + index + "<" + firstIndex + ") Playing last row: " + lastIndex);
      index = lastIndex; // Play the last song if we are at the first
    } else if (+index > +lastIndex) {
      console.log("(" + index + ">" + lastIndex + ") Playing first row: " + firstIndex);
      index = firstIndex; // Play the first song if we are at the last
    }
    var playingRow = playlistTable.bootstrapTable('getRowByUniqueId', index);
    if (!playingRow) {
      showToast("Invalid row: " + index, "danger");
      return;
    }

    // Update next in queue text
    $("#nextInQueueText").parent().hide();
    var nextInQueue = getQueuedSong();
    if (nextInQueue !== null) {
      $("#nextInQueueText").text(getSongNameByIndex(nextInQueue));
      $("#nextInQueueText").parent().show();
    }

    window.songName = getSongNameByIndex(index);
    if (!songName || songName.length === 0) {
      showToast("Invalid songname: " + songName, "danger");
      return;
    }
    updateTitle(songName);
    var filePath = "<?= getConfig("audio_path") ?>/" + songName;
    $("#audioSource").attr("src", filePath);
    $("audio")[0].load();
    $("audio")[0].play();
    var activeRow = $("tr.songrow[data-uniqueid='" + index + "']");
    window.currentIndex = index;
    activeRow.addClass(activeClass).children().addClass(activeClass);
    scrollToActiveSong();
  }

  /* ─────────────────────────── FUNCTION: pauseSong ────────────────────────── */
  function pauseSong() {
    updateTitle(songName);
    $("audio")[0].pause();
  }

  /* ────────────────────────── FUNCTION: resumeSong ────────────────────────── */
  function resumeSong() {
    updateTitle(songName);
    $("audio")[0].play();
  }

  /* ────────────────────────── FUNCTION: toggleSong ────────────────────────── */
  function toggleSong() {
    if (playing) {
      pauseSong();
      return;
    }
    resumeSong();
  }

  /* ────────────────────────── FUNCTION: randomSong ────────────────────────── */
  function randomSong() {
    var rowCount = playlistTable.bootstrapTable('getData').length;
    var randomIndex = Math.floor(Math.random() * rowCount);
    console.log("Playing random row: " + randomIndex);
    playSong(randomIndex);
  }

  /* ─────────────────────────── FUNCTION: stopSong ─────────────────────────── */
  function stopSong() {
    pauseSong();
    window.currentIndex = undefined;
    playing = false;
    $("#audioSource").attr("src", "");
    $("audio")[0].currentTime = 0;
    updateTitle();
  }

  /* ───────────────────────── FUNCTION: prevSong ───────────────────────── */
  function prevSong() {
    if ($("audio")[0].currentTime > 5) {
      $("audio")[0].currentTime = 0;
      return;
    }
    var prevIndex = parseInt(currentIndex) - 1;
    if (shuffle) {
      randomSong();
      return;
    }
    var prevSongItem = playlistTable.bootstrapTable('getData')[prevIndex];
    console.log("Playing previous row: " + prevIndex);
    if (!prevSongItem) {
      prevIndex = playlistTable.bootstrapTable('getData').length - 1;
    }
    playSong(prevIndex);
  }

  /* ─────────────────────────── FUNCTION: nextSong ─────────────────────────── */
  function nextSong() {
    if (queue.length > 0) {
      var nextQueueIndex = queue.shift();
      playSong(nextQueueIndex);
      return;
    }
    var nextIndex = parseInt(currentIndex) + 1;
    if (shuffle) {
      randomSong();
      return;
    }
    var nextSongItem = playlistTable.bootstrapTable('getData')[nextIndex];
    console.log("Playing next row: " + nextIndex);
    if (!nextSongItem) {
      nextIndex = 0;
    }
    playSong(nextIndex);
  }

  /* ───────────────────────── FUNCTION: getQueuedSong ──────────────────────── */
  function getQueuedSong() {
    if (queue.length > 0) {
      return queue[0];
    }
    return null;
  }

  /* ─────────────────────────── FUNCTION: queueSong ────────────────────────── */
  function queueSong(index) {
    queue.push(index);
    showToast("Added to queue: " + getSongNameByIndex(index), "success");
    $("#nextInQueueText").parent().show();
    $("#nextInQueueText").text(getSongNameByIndex(getQueuedSong()));
  }

  /* ────────────────────────── FUNCTION: toggleLoop ────────────────────────── */
  function toggleLoop() {
    loop = !loop;
    var audioElement = $("audio")[0];
    audioElement.loop = !audioElement.loop;
    console.log("Loop: " + loop);
    $(".toggleLoopBtn").toggleClass("active");
  }

  /* ───────────────────────── FUNCTION: toggleShuffle ──────────────────────── */
  function toggleShuffle() {
    shuffle = !shuffle;
    console.log("Shuffle: " + shuffle);
    $(".toggleShuffleBtn").toggleClass("active");
  }

  /* ────────────────────────── FUNCTION: formatTime ────────────────────────── */
  function formatTime(seconds) {
    const minutes = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return minutes + ":" + (secs < 10 ? "0" : "") + secs;
  }

  /* ────────────────────────── FUNCTION: updateTime ────────────────────────── */
  function updateTime() {
    currentTime = $("audio")[0].currentTime;
    $(".audioCurrentTime").text(formatTime(currentTime));
    $(".audioSlider").attr("max", duration);
    $(".audioSlider").val(currentTime);
  }

  /* ─────────────────────────── FUNCTION: showToast ────────────────────────── */
  function showToast(message, type = "info") {
    if (typeof(message) === "object") {
      message = JSON.stringify(message);
    }
    $("#apiResponse").append(`
      <div class='toast align-items-center text-bg-`+type+` border-0' role='alert' aria-live='assertive' aria-atomic='true'>
        <div class='d-flex'>
          <div class='toast-body'>
            `+message+`
          </div>
          <button type='button' class='btn-close btn-close-white me-2 m-auto' data-bs-dismiss='toast' aria-label='Close'></button>
        </div>
      </div>
    `);
    var toast = new bootstrap.Toast($("#apiResponse .toast").last()[0]);
    toast.show();
  }

  /* ─────────────────────────── FUNCTION: api ─────────────────────────── */
  function api(action, data = {}, method = "GET", callback = null) {
    $.ajax({
      url: apiURL,
      type: method,
      data: { action: action, ...data },
      success: function(response) {
        console.log("API Response: ", response);
        statusCode = response.statuscode;
        responseMessage = response.response ? response.response : response;
        responseData = response.data ? response.data : response;
        // 0 = success, 1 = error
        if (statusCode == 0) {
          var type = "success";
        } else if (statusCode == 1) {
          var type = "danger";
        } else {
          var type = "warning";
        }
        showToast(responseMessage, type);
      },
      error: function(error) {
        console.log("API Error: ", error);
        res = JSON.stringify(error);
        showToast("An error occurred: " + res, "danger");
      }
    });
  }

  /* ───────────────────────────── NOTE: Document Ready ───────────────────────────── */
  $(document).ready(function() {

  // showToast("Welcome to the Music Player!", "success");

    /* ────────────────────── NOTE: autoheight ───────────────────── */
    $(".autoheight").each(function() {
      var lbr    = ($(this).text().match(/\n/g) || []).length;
      var height = (lbr * 25) + 25;
      $(this).css("height", height+"px");
      $(this).css("resize", "none");
    });

    /* ───────────────────────────── NOTE: Dropzone ───────────────────────────── */
    $("#musicDropzone").dropzone({
      url: apiURL,
      paramName: "files",
      maxFilesize: 10,
      acceptedFiles: ".mp3",
      uploadMultiple: true,
      parallelUploads: 1,
      dictDefaultMessage: "Drag and drop MP3 files here or click to upload",
      clickable: ".dropzoneSelect",
      disablePreviews: true,
      init: function() {
        var res = {};
        this.on("complete", function(response) {
          console.log("Response: ", response);
          
          if (typeof response.xhr.responseText === 'string') {
            res = JSON.parse(response.xhr.responseText);
          } else {
            res = response.xhr.responseText;
          }

          if (typeof res !== 'object' || res === null) {
            showToast("Response is not an object: "+res, "danger");
            return;
          }

          var statusText      = res.status;
          var statusCode      = res.statuscode;
          var responseMessage = res.response;
          console.log("Response: ", responseMessage);
          console.log("Status: ", statusText);
          console.log("Code: ", statusCode);
          if (statusCode !== 0) {
            var type = "danger";
          } else {
            var type = "success";
          }
          showToast(responseMessage, type);
        });
      }
    });

    var audioElement = $("audio")[0];
    audioElement.volume = <?= getConfig("default_volume") ?>;

    /* ──────────────────────────── NOTE: sessionBtn ──────────────────────────── */
    $(".sessionBtn").on("click", function() {
      $(".sessionForm").hide();
      var target = $(this).data("target");
      toggleFade(target);
    });

    /* ──────────────────────────── NOTE: sessionForm ─────────────────────────── */
    // REVIEW: incomplete
    $(".sessionForm").on("submit", function(e) {
      e.preventDefault();
      var method = $(this).attr("method");
      var data   = $(this).serializeArray().reduce(function(obj, item) {
        obj[item.name] = item.value;
        return obj;
      }, {});
      var action = data.action;
      console.log("Session form data: ", data);
      api(action, data, method);
      $(".sessionCards").find(".noSessionCards").hide();
      // $(".sessionCards").find(".hasSessionCards").show();
    });

    /* ───────────────────────────── Cursor pointer ───────────────────────────── */
    $('#playlistTable').on('post-body.bs.table', function () {
      $('#playlistTable').find('td[data-field="name"]').addClass('cursor-pointer');
    });

    $(".cancelSessionBtn").on("click", function() {
      $(".sessionForm").hide();
    });

    /* ───────────────────────────── NOTE: Interval ───────────────────────────── */
    setInterval(updateTime, 1000);

    /* ──────────────────────── NOTE: table event listeners ──────────────────────── */
    playlistTable.on('click-cell.bs.table', function (e, field, value, row, $element) {
      // e: Event object
      // field: The field name of the clicked cell
      // value: The value of the clicked cell
      // row: The entire row data object
      // $element: The jQuery object of the clicked cell element

      var rowid = row.id;
      var file = getSongNameByIndex(rowid);

      if (field === 'delete') {
        $.ajax({
          url: apiURL,
          type: 'POST',
          data: { action: 'rm', file: file },
          success: function(response) {
            if (response.success) {
              showToast(response.success, "success");
              $("tr.songrow[data-filename='"+file+"']").remove();
            } else {
              showToast("Error deleting file: " + response.error, "danger");
            }
          },
          error: function(error) {
            showToast("An error occurred while deleting the file: "+error, "danger");
          }
        });
      } else if (field === 'download') {
        window.open('<?= getConfig("audio_path") ?>/' + file, '_blank');
      } else if (field === 'queue') {
        queueSong(rowid);
      } else {
        window.currentIndex = row.id;
        playSong(rowid);
      }
    });

    /* ────────────────────────────── NOTE: audioSlider ───────────────────────── */
    $(".audioSlider").on('input', function() {
      currentTime = $(this).val();
      audioElement.currentTime = $(this).val();
    });

    /* ────────────────────────────── NOTE: volumeSlider ──────────────────────── */
    $("#volumeSlider").on('input', function() {
      audioElement.volume = $(this).val();
      if ($(this).val() == 0) {
        $(".volumeIcon").html('<?= icon("volume-mute-fill", 2) ?>');
      } else if ($(this).val() <= 0.5) {
        $(".volumeIcon").html('<?= icon("volume-down-fill", 2) ?>');
      } else {
        $(".volumeIcon").html('<?= icon("volume-up-fill", 2) ?>');
      }
      $(".audioVolume").text(Math.round($(this).val() * 100) + "%");
    });

    /* ────────────────────────────── NOTE: volumeIcon ─────────────────────────── */
    $(".volumeIcon").on('click', function() {
      if ($("#volumeSlider").val() == 0) {
        $("#volumeSlider").val(0.5);
      } else {
        $("#volumeSlider").val(0);
      }
      $("#volumeSlider").trigger('input');
    });

    /* ────────────────────────────────────────────────────────────────────────── */
    /*                                Audio Element                               */
    /* ────────────────────────────────────────────────────────────────────────── */
    /* ────────────────────────────── NOTE: canplay ───────────────────────────── */
    $(audioElement).on('canplay', function() {
      duration = audioElement.duration;
      $(".audioDuration").text(formatTime(duration));
      $(".ctrlBtn").prop("disabled", false);
    });

    /* ─────────────────────────────── NOTE: Ended ────────────────────────────── */
    $(audioElement).on('ended', function() {
      nextSong();
    });

    /* ─────────────────────────────── NOTE: Play ─────────────────────────────── */
    $(audioElement).on('play', function() {
      playing = true;
      playPauseBtn = $(".playPauseBtn");
      playPauseBtn.html(pauseIconHTML);
      playPauseBtn.removeClass("btn-outline-success");
      playPauseBtn.removeClass("btn-outline-warning");
      playPauseBtn.addClass("btn-success");
    });

    /* ────────────────────────────── NOTE: Pause ─────────────────────────────── */
    $(audioElement).on('pause', function() {
      playPauseBtn = $(".playPauseBtn");
      playPauseBtn.removeClass("btn-success");
      playPauseBtn.removeClass("btn-outline-success");
      playPauseBtn.addClass("btn-outline-warning");
      playing = false;
      $(".playPauseBtn").html(playIconHTML);
    });

    if (window.history.replaceState) {
      const url = new URL(window.location);
      url.search = '';
      window.history.replaceState({ path: url.href }, '', url.href);
    }

    // Support media keys
    if ('mediaSession' in navigator) {
      navigator.mediaSession.setActionHandler('play', function() {
        resumeSong();
      });
      navigator.mediaSession.setActionHandler('pause', function() {
        pauseSong();
      });
      navigator.mediaSession.setActionHandler('previoustrack', function() {
        prevSong();
      });
      navigator.mediaSession.setActionHandler('nexttrack', function() {
        nextSong();
      });
    }

    // Keyboard shortcuts
    $(document).keydown(function(e) {
      if (e.code === 'Space' && !$(':focus').is('input, textarea')) {
        e.preventDefault();
        toggleSong();
      }
    });

    // cfgInputClass
    $(".<?= $cfgInputClass ?>")
      .on("keydown", function() {
        setWarning($(this));
        $(".reloadCfgBtn").show();
      })
      .on("change", function() {
        $(".reloadCfgBtn").show();
        var key   = $(this).data("key");
        if (typeof(key) === "undefined") {
          console.log("Key is undefined.", key);
          return;
        }
        if ($(this).is(":checkbox")) {
          var value = ($(this).is(":checked") ? true : false);
        } else {
          var value = $(this).val();
        }
        console.log("Key: " + key + ", Value: " + value);
        api("setconfig", { config: { key: key, value: value } }, "POST");
        setSuccess($(this));
      });

    // Setting Range
    $(".settingRange").on("input", function() {
      var key      = $(this).attr("id");
      var value    = $(this).val();
      var valueObj = $(this).attr("data-valueobject");
      $("#" + valueObj).text(value * 100 + "%");
    });
});
</script>

</html>
