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

# Configuration (defaults)
$config["allowed_types"]          = ["mp3"];
$config["audio_path"]             = "music";
$config["use_legacy_player"]      = false;
$config["include_file_extension"] = true;
$config["default_volume"]         = 0.5;

# Configuration (JSON)
if (file_exists('config.json')) {
    $config = array_merge($config, json_decode(file_get_contents('config.json'), true));
}

# Configuration (JSON Local)
if (file_exists('config.local.json')) {
    $config = array_merge($config, json_decode(file_get_contents('config.local.json'), true));
}


/* ───────────────────────────── FUNCTION: download ─────────────────────────── */
function download($file) {
    $filePath = $config["audio_path"] . '/' . $file;
    if (file_exists($filePath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
}

/* ───────────────────────────── FUNCTION: remove ─────────────────────────── */
function remove($file) {
    $filePath = $config["audio_path"] . '/' . $file;
    $deletedDir = 'deleted/';
    if (!is_dir($deletedDir)) {
      mkdir($deletedDir, 0777, true);
    }
    if (file_exists($filePath)) {
      rename($filePath, $deletedDir . $file);
    }
}

/* ───────────────────────────── FUNCTION: icon ───────────────────────────── */
function icon($icon = "", $size = 1.5, $margin = 1) {
  return '<i class="bi bi-' . $icon . ' m-' . $margin . '" style="font-size: ' . $size . 'em;"></i>';
}

/* ───────────────────────────────── Actions ──────────────────────────────── */
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'dl' && isset($_GET['file'])) {
        download($_GET['file']);
    } elseif ($_GET['action'] === 'rm' && isset($_GET['file'])) {
        remove($_GET['file']);
    }
}

$musicFiles = array_diff(scandir($config["audio_path"]), array('..', '.'));

echo '
<html>
<body class="theme-dark">
<div class="container">';

echo '
<div class="card m-3">
  <h2 class="card-header">Upload Music Files</h2>
  <div class="card-body">
    <form action="upload.php" method="post" enctype="multipart/form-data" class="dropzone" id="music-dropzone">
    <input type="file" name="files[]" accept=".mp3" multiple hidden>
    <div class="dz-message">Drag and drop MP3 files here or click to upload</div>
    </form>
  </div>
</div>
';

echo '
<div class="audio-player-container">
  <div class="card">
      <h3 id="songtitle" class="card-header text-success">No song selected</h3>
      <div class="card-body">
          <audio '.($config['use_legacy_player'] ? 'controls' : '').' style="width:100%">
          <source id="audioSource" src="' . htmlspecialchars($filePath) . '" type="audio/mpeg">
          Your browser does not support the audio element.
          </audio>';

          if (!$config['use_legacy_player']) {
            echo '
            <div class="d-flex align-items-center">
              <span class="audioCurrentTime">0:00</span>
              <input type="range" class="form-range audioSlider mx-2" min="0" max="0" step="1" value="0" style="flex: 3;">
              <span class="audioDuration">0:00</span>
            </div>
            <br>
            <div class="btn-group align-items-center mx-2">
              <label for="volumeSlider" class="volumeIcon form-label mb-0 me-2">'.icon("volume-down-fill", 2).'</label>
              <input type="range" class="form-range" id="volumeSlider" min="0" max="1" step="0.01" value="'.$config['default_volume'].'" style="flex: 1;">
            </div>
            ';
          }
echo '
        <div class="btn-group mx-2">
          <button class="btn btn-sm btn-pill btn-outline-primary" onclick="playSong(currentIndex - 1)">'.icon('skip-backward-fill').'</button>
          <button class="btn btn-sm btn-pill btn-outline-success playPauseBtn" disabled>'.icon("music-note-beamed").'</button>
          <button class="btn btn-sm btn-pill btn-outline-primary" onclick="playSong(currentIndex + 1)">'.icon('skip-forward-fill').'</button>
        </div>
        <div class="btn-group mx-2">
          <button class="btn btn-sm btn-pill btn-outline-primary toggleLoopBtn">'.icon('arrow-repeat').'</button>
          <button class="btn btn-sm btn-pill btn-outline-primary toggleShuffleBtn">'.icon('shuffle').'</button>
        </div>
        <div class="btn-group mx-2">
          <button class="btn btn-sm btn-pill btn-outline-success" onclick="playSong(0)">'.icon('play-fill').' Play First Song</button>
        </div>
      </div>
  </div>
</div>
';

echo '
<div class="card m-3">
<h1 class="card-header">Music List</h1>
<div class="card-body">';

if (empty($musicFiles)) {
    echo '<p>No music files found.</p>';
    echo '<p>Upload some music files to the <code>'.$config["audio_path"].'/</code> directory.</p>';
}
echo "<table class='table table-striped' data-toggle='table' data-search='true'>
<thead>
  <tr class='table-success'>
    <th data-field='name'>File Name</th>
    <th>Download</th>
    <th>Delete</th>
  </tr>
    <tbody>
  ";
$i = 0;
foreach ($musicFiles as $file) {
    $filePath = $config["audio_path"] . "/" . $file;
    if (!in_array(pathinfo($filePath, PATHINFO_EXTENSION), $config['allowed_types'])) {
      continue;
    }
    $audioName = $file;
    if (!$config["include_file_extension"]) {
      $audioName = pathinfo($file, PATHINFO_FILENAME);
    }
    echo '
    <tr>
      <td><a href="javascript:void(0);" class="musicitem link-secondary" id="musicitem-'.$i.'" data-id="'.$i.'" data-filename="'.$file.'">' . htmlspecialchars($audioName) . '</a></td>
      <td><a href="?action=dl&file=' . urlencode($file) . '" class="link-success">'.icon('download', margin: 0).'</a></td>
      <td><a href="?action=rm&file=' . urlencode($file) . '" class="link-danger">'.icon('trash-fill', margin: 0).'</a></td>
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


  Dropzone.options.musicDropzone = {
    paramName: "files",
    maxFilesize: 10,
    acceptedFiles: ".mp3",
    dictDefaultMessage: "Drag and drop MP3 files here or click to upload",
    init: function() {
      this.on("success", function(file, response) {
        location.reload();
      });
    }
  };

  /* ────────────────────────── FUNCTION: updateTitle ───────────────────────── */
  function updateTitle(title) {
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

  /* ─────────────────────────── FUNCTION: playSong ─────────────────────────── */
  function playSong(index) {
    console.log("Playing song " + index);
    if (index < 0) {
      index = $(".musicitem").length - 1; // Play the last song if we are at the first
    } else if (index >= $(".musicitem").length) {
      index = 0; // Play the first song if we are at the last
    }
    // songName = $(".musicitem").eq(index).text();
    songName = $(".musicitem").eq(index).data("filename");
    if (songName.length === 0) {
      index = 0;
    }
    updateTitle(songName);
    var filePath = "<?= $config["audio_path"] ?>/" + songName;
    $("#audioSource").attr("src", filePath);
    $("audio")[0].load();
    $("audio")[0].play();
    $(".musicitem").removeClass("link-success");
    $(".musicitem").eq(index).addClass("link-success");
    window.currentIndex = index;
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

  /* ────────────────────────── FUNCTION: toggleLoop ────────────────────────── */
  function toggleLoop() {
    loop = !loop;
    var audioElement = $("audio")[0];
    audioElement.loop = !audioElement.loop;
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

  $(document).ready(function() {

    var audioElement = $("audio")[0];
    audioElement.volume = <?= $config["default_volume"] ?>;

    // Set new interval for updating time
    setInterval(updateTime, 1000);

    $(document).on("click", ".musicitem", function() {
      playSong($(this).data("id"));
      return;
    });

    $(audioElement).on('canplay', function() {
      duration = audioElement.duration;
      $(".audioDuration").text(formatTime(duration));
      $(".playPauseBtn").prop("disabled", false);
    });
    $(".audioSlider").on('input', function() {
      currentTime = $(this).val();
      audioElement.currentTime = $(this).val();
    });
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
    $(".volumeIcon").on('click', function() {
      if ($("#volumeSlider").val() == 0) {
        $("#volumeSlider").val(0.5);
      } else {
        $("#volumeSlider").val(0);
      }
      $("#volumeSlider").trigger('input');
    });

    $(audioElement).on('ended', function() {
      if (shuffle) {
        var randomIndex = Math.floor(Math.random() * $(".musicitem").length);
        playSong(randomIndex);
        return;
      }
      currentIndex++;
      var nextSongItem = $('#musicitem-' + currentIndex);
      console.log("Playing next row: " + nextSongItem.text());
      if (!nextSongItem.length) {
        currentIndex = 0;
      }
      playSong(currentIndex);
    });

    $(".playPauseBtn").click(function() {
      if (playing) {
        pauseSong();
      } else {
        resumeSong();
      }
    });
    $(".toggleLoopBtn").click(function() {
      toggleLoop();
    });
    $(".toggleShuffleBtn").click(function() {
      toggleShuffle();
    });

    $(audioElement).on('play', function() {
      playing = true;
      $(".playPauseBtn").html(pauseIconHTML);
      updateTitle(songName);
    });
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
        if (window.currentIndex > 0) {
          playSong(window.currentIndex - 1);
        }
      });
      navigator.mediaSession.setActionHandler('nexttrack', function() {
        if (window.currentIndex < $(".musicitem").length - 1) {
          playSong(window.currentIndex + 1);
        }
      });
    }

    $(document).keydown(function(e) {
      if (e.code === 'Space') {
        e.preventDefault();
        if (playing) {
          pauseSong();
        } else {
          resumeSong();
        }
      }
    });
});
</script>