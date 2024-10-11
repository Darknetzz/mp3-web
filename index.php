<?php 
require_once('_includes.php');

$musicFiles = array_diff(scandir(AUDIO_PATH), array('..', '.'));
?>

<!DOCTYPE html>
<meta charset="utf-8">
<link rel="icon" href="favicon.ico" type="image/x-icon">
<title>Music Player</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/js/tabler.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/css/tabler.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<script src=" https://cdn.jsdelivr.net/npm/bootstrap-table@1.23.5/dist/bootstrap-table.min.js "></script>
<link href=" https://cdn.jsdelivr.net/npm/bootstrap-table@1.23.5/dist/bootstrap-table.min.css " rel="stylesheet">

<script src="https://unpkg.com/dropzone@6.0.0-beta.1/dist/dropzone-min.js"></script>
<link href="https://unpkg.com/dropzone@6.0.0-beta.1/dist/dropzone.css" rel="stylesheet" type="text/css" />

<style>
  body {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
  }
  .container {
    padding-bottom: 250px; /* Adjust this value based on the height of the audio player */
  }
  .audio-player-container {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    /* background-color: #f8f9fa; */
    box-shadow: 0 -2px 5px rgba(0,0,0,0.1);
    z-index: 1000;
    padding: 10px;
  }
  .audio-player-container audio {
    width: 100%;
  }
  .audio-player-container .btn-group {
    margin-top: 10px;
  }
  .modal-open-blur {
    filter: blur(5px);
  }
  .modal-open-blur .modal {
    filter: none;
  }
</style>
<?php

echo '<html>';

/* ────────────────────────────── Config Modal ────────────────────────────── */
echo '
<div class="modal fade" id="configModal" tabindex="-1" aria-labelledby="configModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="configModalLabel">Configuration</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <div class="modal-status bg-primary"></div>
      </div>
      <div class="modal-body">
      <form action="api.php" method="POST">
      <table class="table table-bordered">
        <!--
        <thead>
          <tr>
            <th>Key</th>
            <th>Value</th>
          </tr>
        </thead>
        -->
        <tbody>';
      foreach (CONFIG as $key => $values) {
        $name          = $values["name"];
        $value         = $values["value"];
        $description   = $values["description"];
        $type          = $values["type"];
        $attributes    = $values["attributes"] ?? [];
        $cfgInputClass = "settingInput";
        $input         = '<textarea class="form-control '.$cfgInputClass.'" type="text" id="'.$key.'">'.$value.'</textarea>';
        // $json_value  = json_encode($value);
        if ($type == "string") {
          $input = '<input class="form-control '.$cfgInputClass.'" type="text" id="'.$key.'" value="'.$value.'">';
        }
        if ($type == "array") {
          $value = implode(", ", $value);
          $input = '<textarea class="form-control '.$cfgInputClass.'" type="text" id="'.$key.'">'.$value.'</textarea>';
        }
        if ($type == "bool") {
          $value = $value ? "true" : "false";
          $input = '<div class="form-check form-switch '.$cfgInputClass.'"><input class="form-check-input" type="checkbox" id="'.$key.'" '.($value ? 'checked' : '').'></div>';
        }
        if ($type == "range") {
          $input = '<input class="form-control '.$cfgInputClass.'" type="number" id="'.$key.'" value="'.$value.'" min="'.$attributes["min"].'" max="'.$attributes["max"].'" step="'.$attributes["step"].'">';
        }
        echo '
        <tr>
          <td class="text-primary">
            <label for="'.$key.'" class="form-label">'.$name.'</label>
            <small class="text-muted '.$cfgInputClass.'">'.$description.'</small>
          </td>
          <td>
            '.$input.'
          </td>
        </tr>';
      }
echo '</tbody>
      </table>
      </form>
      <!--
        <div class="d-flex justify-content-end">
          <input type="submit" class="btn btn-outline-success" value="Save">
        </div>
      -->
      </div>
    </div>
  </div>
</div>';

/* ─────────────────────────────── apiResponse ────────────────────────────── */
echo '
  <div id="apiResponse" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1050;">
    <!-- Response from API will be displayed here -->
  </div>
';

echo '
<body class="theme-dark">
<div class="container">
';

if (!$composer) {
  echo '<div class="alert alert-danger">Please run <code>composer install</code> to install the required dependencies.</div>';
}

echo '
<div class="audio-player-container">
  <div class="d-flex align-items-center card">
      <h3 id="songtitle" class="card-header text-success">'.getConfig("no_song_text").'</h3>
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
<h1 class="card-header">'.getConfig("site_title").'</h1>
<div class="card-body">

<div id="musicDropzone" class="border border-primary align-items-center p-3">
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
<div id="toolbar">
  <div class="btn-group">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#configModal">'.icon("gear").' Configuration</button>
  </div>
</div>
<table id="playlistTable" data-toolbar="#toolbar" class="table table-striped" 
  data-toggle="table" 
  data-search="true" 
  data-sort="true" 
  data-show-refresh="true"
  data-show-toggle="true"
  data-show-columns="true"
  data-show-columns-toggle-all="true"
  data-show-export="true"
  data-unique-id="id"
  data-escape="false"
  data-url="api.php?action=ls"
>
  <thead id="playlistHead">
  <tr class="table-success">
    <th data-field="id" data-visible="false">#</th>
    <th data-field="name">Name</th>
    <th data-field="filename" data-visible="false">Filename</th>
    <th data-field="duration">Duration</th>
    <th data-field="size" data-visible="false">Size</th>
    <th data-field="date" data-visible="false">Date</th>
    <th data-field="download">Download</th>
    <th data-field="delete">Delete</th>
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
      <td class="action"><a href="javascript:void(0);" data-filename="' . $urlFilename . '" class="link-success downloadBtn">'.icon('download', margin: 0).'</a></td>
      <td class="action"><a href="javascript:void(0);" data-filename="' . $urlFilename . '" class="link-danger deleteBtn">'.icon('trash-fill', margin: 0).'</a></td>
    </tr>';
    $i++;
}
echo '</tbody></table>';
echo '</div></div>';
?>


</div>
</body></html>

<!--
/* ────────────────────────────────────────────────────────────────────────── */
/*                                 JAVASCRIPT                                 */
/* ────────────────────────────────────────────────────────────────────────── */
-->
<script>
  var playlistTable = $('#playlistTable');
  var pauseIcon     = "⏸ ";
  var playIcon      = "⏵ ";
  var pauseIconHTML = '<?= icon('pause-fill') ?>';
  var playIconHTML  = '<?= icon('play-fill') ?>';
  var playing       = false;
  var shuffle       = false;
  var loop          = false;
  var duration      = 0;
  var currentTime   = 0;
  var currentIndex  = 0;
  var apiURL        = "api.php";

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
    $("tr.songrow").removeClass("table-success");
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
    activeRow.addClass("table-success");
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
  function api(action, data = {}, callback = null) {
    $.ajax({
      url: apiURL,
      type: 'POST',
      data: { action: action, ...data },
      success: function(response) {
        console.log("API Response: ", response);
        if (response.success) {
          showToast(response.success, "success");
        } else {
          showToast("Error: " + response.error, "danger");
        }
        if (callback) {
          callback(response);
        }
      },
      error: function(error) {
        showToast("An error occurred: " + error, "danger");
      }
    });
  }

  /* ───────────────────────────── NOTE: Document Ready ───────────────────────────── */
  $(document).ready(function() {

    showToast("Welcome to the Music Player!", "success");

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
        this.on("complete", function(file) {
          var response = file.xhr ? JSON.parse(file.xhr.responseText) : {};
          console.log("Dropzone response: ", typeof(response), JSON.stringify(response));
          if (Array.isArray(response)) {
            response.forEach(function(res, index) {
                showToast(res.error || res.success, res.error ? "danger" : "success");
            });
          } else if (typeof response === 'object' && !Array.isArray(response)) {
            showToast(response.error || response.success, response.error ? "danger" : "success");
          } else {
            showToast("An error occurred while uploading the file.", "danger");
          }
        });
      }
    });

    var audioElement = $("audio")[0];
    audioElement.volume = <?= getConfig("default_volume") ?>;

    /* ───────────────────────────── Cursor pointer ───────────────────────────── */
    $('#playlistTable').on('post-body.bs.table', function () {
      $('#playlistTable').find('td[data-field="name"]').addClass('cursor-pointer');
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
      if (e.code === 'Space') {
        e.preventDefault();
        toggleSong();
      }
    });

    // Modal
    $("#configModal").on("show.bs.modal", function () {
      $(".container").addClass("modal-open-blur");
    });

    $("#configModal").on("hidden.bs.modal", function () {
      $(".container").removeClass("modal-open-blur");
    });

    // cfgInputClass
    $(".<?= $cfgInputClass ?>").on("change", function() {
      var key = $(this).attr("id");
      var value = $(this).val();
      console.log("Key: " + key + ", Value: " + value);
      api("setconfig", { config: { [key]: value } }, function(response) {
        if (response.success) {
          showToast(response.success, "success");
        } else {
          showToast("Error saving configuration: " + response.error, "danger");
        }
      });
    });
});
</script>