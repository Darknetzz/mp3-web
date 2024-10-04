<?php 

# Configuration (JSON)
if (file_exists('config.php')) {
  include_once('config.php');
}

# Configuration (Local)
if (file_exists('config.local.php')) {
  include_once('config.local.php');
}

/* ───────────────────────────────── Composer ──────────────────────────────── */
$composer = False;
if (file_exists('vendor/autoload.php')) {
  $composer = True;
  require __DIR__ . '/vendor/autoload.php';
}

$musicFiles = array_diff(scandir($config["audio_path"]), array('..', '.'));

/* ───────────────────────────── FUNCTION: icon ───────────────────────────── */
function icon($icon = "", $size = 1.5, $margin = 1) {
  return '<i class="bi bi-' . $icon . ' m-' . $margin . '" style="font-size: ' . $size . 'em;"></i>';
}

/* ──────────────────────────── FUNCTION: mp3info ─────────────────────────── */
function getDuration($file) {
  global $config;
  global $composer;
  if (!$composer || !class_exists('wapmorgan\Mp3Info\Mp3Info')) {
    return "0:00";
  }
  if (!file_exists($file)) {
    return "File <code>$file</code> not found.";
  }
  $mp3info  = new wapmorgan\Mp3Info\Mp3Info($file);
  $duration = $mp3info->duration;
  $minutes = floor($duration / 60);
  $seconds = $duration % 60;
  return sprintf("%d:%02d", $minutes, $seconds);
}

?>

<!DOCTYPE html>
<meta charset="utf-8">
<title>Music Player</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/js/tabler.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/css/tabler.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-table@1.23.4/dist/bootstrap-table.min.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap-table@1.23.4/dist/bootstrap-table.min.js"></script>

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
</style>
<?php

echo '
<html>
<body class="theme-dark">
<div class="container">';

if (!$composer) {
  echo '<div class="alert alert-danger">Please run <code>composer install</code> to install the required dependencies.</div>';
}

echo '
<div class="audio-player-container">
  <div class="d-flex align-items-center card">
      <h3 id="songtitle" class="card-header text-success">'.$config["no_song_text"].'</h3>
      <div class="card-body">
          <audio '.($config['use_legacy_player'] ? 'controls' : '').' style="width:100%">
          <source id="audioSource" src="" type="audio/mpeg">
          Your browser does not support the audio element.
          </audio>';

          if (!$config['use_legacy_player']) {
            echo '
            <div class="d-flex align-items-center">
              <span class="audioCurrentTime">0:00</span>
              <input type="range" class="form-range audioSlider mx-2" min="0" max="0" step="1" value="0" style="flex: 3;">
              <span class="audioDuration">0:00</span>
            </div>
            
            <div class="btn-group mx-2 align-items-center">
              <label for="volumeSlider" class="volumeIcon form-label mb-0 me-2">'.icon("volume-down-fill", 2).'</label>
              <input type="range" class="form-range" id="volumeSlider" min="0" max="1" step="0.01" value="'.$config['default_volume'].'">
            </div>
            ';
          }
echo '
          <div class="btn-group mx-2">
            <button class="btn btn-sm btn-pill btn-outline-success ctrlBtn playPauseBtn" onclick="toggleSong()" disabled>'.icon("play").'</button>
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
<h1 class="card-header">'.$config["site_title"].'</h1>
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
    echo '<p>Upload some music files to the <code>'.$config["audio_path"].'/</code> directory.</p>';
}
echo "<table id='playlistTable' class='table table-striped' data-toggle='table' data-search='true'>
<thead id='playlistHead'>
  <tr class='table-success'>
    <th data-field='id'>#</th>
    <th data-field='name'>File Name</th>
    <th data-field='duration'>Duration</th>
    <th>Download</th>
    <th>Delete</th>
  </tr>
    <tbody id='playlistBody'>
  ";
$i = 0;
foreach ($musicFiles as $file) {
    $filePath    = $config["audio_path"] . "/" . $file;
    $urlFilename = urlencode($file);
    if (!in_array(pathinfo($filePath, PATHINFO_EXTENSION), $config['allowed_types'])) {
      continue;
    }
    $audioName = $file;
    if (!$config["include_file_extension"]) {
      $audioName = pathinfo($file, PATHINFO_FILENAME);
    }
    echo '
    <tr class="songrow" data-songid="'.$i.'" data-filename="'.$file.'">
      <td data-class="cursor-pointer musicitem">
        <span class="text-muted">'.($i + 1).'</span>
      </td>
      <td data-class="cursor-pointer musicitem">
        '.htmlspecialchars($audioName).'
      </td>
      <td class="durationCol">'.getDuration($filePath).'</td>
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

<script>
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

  /* ──────────────────────── FUNCTION: getSongByIndex ──────────────────────── */
  function getSongByIndex(index) {
    return $("tr.songrow[data-songid="+index+"]");
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
    var autoScroll = <?= json_encode($config["auto_scroll"]) ?>;
    if (!autoScroll) {
        return;
    }
    var activeSong = getSongByIndex(currentIndex);
    if (activeSong.length) {
      activeSong[0].scrollIntoView({ behavior: "smooth", block: "center" });
    }
  }

  /* ─────────────────────────── FUNCTION: playSong ─────────────────────────── */
  function playSong(index) {
    console.log("Playing song at index: " + index);
    $("tr.songrow").removeClass("table-success");
    firstIndex = $("tr.songrow").first().data("songid");
    lastIndex  = $("tr.songrow").last().data("songid");
    console.log("firstIndex: " + firstIndex + ", lastIndex: " + lastIndex);
    if (index < firstIndex) {
      index = lastIndex; // Play the last song if we are at the first
    } else if (index > lastIndex) {
      index = firstIndex; // Play the first song if we are at the last
    }
    playingRow = $("tr.songrow[data-songid="+index+"]");
    songName = playingRow.data("filename");
    if (songName.length === 0) {
      console.log("Invalid index: " + index);
      index = 0;
    }
    updateTitle(songName);
    var filePath = "<?= $config["audio_path"] ?>/" + songName;
    $("#audioSource").attr("src", filePath);
    $("audio")[0].load();
    $("audio")[0].play();
    playingRow.addClass("table-success");
    window.currentIndex = index;
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
    var randomIndex = Math.floor(Math.random() * $("tr.songrow").length);
    console.log("Playing random row: " + randomIndex);
    playSong(randomIndex);
  }

  /* ─────────────────────────── FUNCTION: stopSong ─────────────────────────── */
  function stopSong() {
    pauseSong();
    window.currentIndex = undefined;
    playing = false;
    $(".musicitem").removeClass("table-success");
    $("#audioSource").attr("src", "");
    $("audio")[0].currentTime = 0;
    updateTitle();
  }

  /* ───────────────────────── FUNCTION: prevSong ───────────────────────── */
  function prevSong() {
    var prevIndex = currentIndex - 1;
    if (shuffle) {
      randomSong();
      return;
    }
    var prevSongItem = $('tr.songrow[data-songid="' + prevIndex + '"]');
    console.log("Playing previous row: " + prevSongItem.data("songid"));
    if (!prevSongItem.length) {
      prevIndex = $("tr.songrow").last().data("songid");
    }
    playSong(prevIndex);
  }

  /* ─────────────────────────── FUNCTION: nextSong ─────────────────────────── */
  function nextSong() {
    var nextIndex = currentIndex + 1;
    if (shuffle) {
      randomSong();
      return;
    }
    var nextSongItem = $('tr.songrow[data-songid="' + nextIndex + '"]');
    console.log("Playing next row: " + nextSongItem.data("songid"));
    if (!nextSongItem.length) {
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

  /* ───────────────────────────── NOTE: Document Ready ───────────────────────────── */
  $(document).ready(function() {

    $("#musicDropzone").dropzone({
      url: "api.php",
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
          if (Array.isArray(response)) {
            response.forEach(function(res, index) {
              var uniqueId = Math.random().toString(16).substr(2, 8);
              resdiv = $("#dropzoneResponse").append("<div id='resdiv-" + uniqueId + "'>");
              $("#resdiv-" + uniqueId).addClass("alert alert-" + (res.error ? "danger" : "success"));
              if (res.success) {
                $("#resdiv-"+uniqueId).html(res.success);
              } else {
                $("#resdiv-"+uniqueId).html((res.error || 'An unknown error occurred.'));
              }
              setTimeout(function() {
                $("#resdiv-" + uniqueId).fadeOut();
              }, 5000);
            });
          }
        });
      }
    });


    var audioElement = $("audio")[0];
    audioElement.volume = <?= $config["default_volume"] ?>;

    /* ───────────────────────────── NOTE: Interval ───────────────────────────── */
    setInterval(updateTime, 1000);

    /* ──────────────────────── NOTE: click-row.bs.table ──────────────────────── */
    $(document).on("click-row.bs.table", function(e, row, element) {
      currentIndex = element.data("index");
      console.log("Clicked on music item " + currentIndex);
      playSong(currentIndex);
    });

    /* ───────────────────────────── NOTE: deleteBtn ──────────────────────────── */
    $(".deleteBtn").on('click', function() {
      var file = $(this).data("filename");
      $.ajax({
        url: 'api.php',
        type: 'POST',
        data: { action: 'rm', file: file },
        success: function(response) {
          if (response.success) {
            alert("File deleted successfully.");
            location.reload();
          } else {
            alert("Error deleting file: " + response.error);
          }
        },
        error: function() {
          alert("An error occurred while trying to delete the file.");
        }
      });
    });

    /* ──────────────────────────── NOTE: downloadBtn ─────────────────────────── */
    $(".downloadBtn").on('click', function() {
      var file = $(this).data("filename");
      window.location.href = 'api.php?action=dl&file=' + file;
    });

    /* ────────────────────────────── NOTE: canplay ───────────────────────────── */
    $(audioElement).on('canplay', function() {
      duration = audioElement.duration;
      $(".audioDuration").text(formatTime(duration));
      $(".ctrlBtn").prop("disabled", false);
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
    /* ─────────────────────────────── NOTE: Ended ────────────────────────────── */
    $(audioElement).on('ended', function() {
      nextSong();
    });

    /* ─────────────────────────────── NOTE: Play ─────────────────────────────── */
    $(audioElement).on('play', function() {
      playing = true;
      $(".playPauseBtn").html(pauseIconHTML);
      updateTitle(songName);
    });

    /* ────────────────────────────── NOTE: Pause ─────────────────────────────── */
    $(audioElement).on('pause', function() {
      playing = false;
      $(".playPauseBtn").html(playIconHTML);
      updateTitle(songName);
    });

    if (window.history.replaceState) {
      const url = new URL(window.location);
      url.search = '';
      window.history.replaceState({ path: url.href }, '', url.href);
    }

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

    $(document).keydown(function(e) {
      if (e.code === 'Space') {
        e.preventDefault();
        toggleSong();
      }
    });
});
</script>