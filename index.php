<!DOCTYPE html>
<meta charset="utf-8">
<title>Music Player</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/js/tabler.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/css/tabler.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

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

/* ───────────────────────────── FUNCTION: download ─────────────────────────── */
function download($file) {
    $filePath = 'music/' . $file;
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
    $filePath = 'music/' . $file;
    $deletedDir = 'deleted/';
    if (!is_dir($deletedDir)) {
      mkdir($deletedDir, 0777, true);
    }
    if (file_exists($filePath)) {
      rename($filePath, $deletedDir . $file);
    }
}

/* ───────────────────────────── FUNCTION: icon ───────────────────────────── */
function icon($icon) {
    return '<i class="bi bi-' . $icon . ' mx-2"></i>';
}

/* ───────────────────────────────── Actions ──────────────────────────────── */
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'dl' && isset($_GET['file'])) {
        download($_GET['file']);
    } elseif ($_GET['action'] === 'rm' && isset($_GET['file'])) {
        remove($_GET['file']);
    }
}

$musicDir = 'music/';
$musicFiles = array_diff(scandir($musicDir), array('..', '.'));

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
      <div class="card-body">
          <h4 id="songtitle" class="mt-2">No song selected</h4>
          <audio controls style="width:100%">
          <source id="audioSource" src="' . htmlspecialchars($filePath) . '" type="audio/mpeg">
          Your browser does not support the audio element.
          </audio>
          <div class="btn-group">
            <button class="btn btn-pill btn-outline-secondary" onclick="playSong(currentIndex - 1)">'.icon('skip-backward-fill').' Play Previous Song</button>
            <button class="btn btn-pill btn-outline-primary playPauseBtn">'.icon("music-note-beamed").' Play</button>
            <button class="btn btn-pill btn-outline-secondary" onclick="playSong(currentIndex + 1)">'.icon('skip-forward-fill').' Play Next Song</button>
          </div>
          <div class="btn-group">
            <button class="btn btn-pill btn-outline-primary">'.icon('arrow-repeat').' Loop</button>
            <button class="btn btn-pill btn-outline-primary">'.icon('shuffle').' Shuffle</button>
          </div>
          <div class="btn-group">
            <button class="btn btn-pill btn-outline-success" onclick="playSong(0)">'.icon('play-fill').' Play First Song</button>
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
    echo '<p>Upload some music files to the <code>music/</code> directory.</p>';
}
echo "<table class='table table-striped'>";
$i = 0;
foreach ($musicFiles as $file) {
    $filePath = $musicDir . $file;
    if (pathinfo($filePath, PATHINFO_EXTENSION) !== 'mp3') {
        continue;
    }
    echo '
    <tr>
      <td><a href="javascript:void(0);" class="musicitem" id="musicitem-'.$i.'" data-id="'.$i.'">' . htmlspecialchars($file) . '</a></td>
      <td><a href="?action=dl&file=' . urlencode($file) . '" class="link-success">'.icon('download').'</a></td>
      <td><a href="?action=rm&file=' . urlencode($file) . '" class="link-danger">'.icon('trash-fill').'</a></td>
    </tr>';
    $i++;
}
echo '</table>';
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

  /* ─────────────────────────── FUNCTION: playSong ─────────────────────────── */
  function playSong(index) {
    console.log("Playing song " + index);
    if (index < 0) {
      index = $(".musicitem").length - 1; // Play the last song if we are at the first
    } else if (index >= $(".musicitem").length) {
      index = 0; // Play the first song if we are at the last
    }
    songName = $(".musicitem").eq(index).text();
    if (songName.length === 0) {
      index = 0;
    }
    document.title = playIcon + songName;
    var filePath = "music/" + songName;
    $("#audioSource").attr("src", filePath);
    $("#songtitle").text(songName);
    $("audio")[0].load();
    $("audio")[0].play();
    $(".musicitem").removeClass("link-success");
    $(".musicitem").eq(index).addClass("link-success");
    window.currentIndex = index;
  }

  /* ─────────────────────────── FUNCTION: pauseSong ────────────────────────── */
  function pauseSong() {
    document.title = pauseIcon + songName;
    $("audio")[0].pause();
  }

  /* ────────────────────────── FUNCTION: resumeSong ────────────────────────── */
  function resumeSong() {
    document.title = playIcon + songName;
    $("audio")[0].play();
  }


  $(document).ready(function() {

    var audioElement = $("audio")[0];
    audioElement.volume = 0.5;

    $(".musicitem").click(function() {
      playSong($(this).data("id"));
      return;
    });

    $(audioElement).on('ended', function() {
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

    $(audioElement).on('play', function() {
      playing = true;
      $(".playPauseBtn").html(pauseIconHTML + "Pause");
      document.title = playIcon + $("#songtitle").text();
    });
    $(audioElement).on('pause', function() {
      playing = false;
      $(".playPauseBtn").html(playIconHTML + "Play");
      document.title = pauseIcon + $("#songtitle").text();
    });
    // $(audioElement).on('change', function() {
    //   // playing = !audioElement.paused;
    //   if (audioElement.paused) {
    //     playing = false;
    //     $(".playPauseBtn").html(playIconHTML + "Play");
    //     document.title = pauseIcon + $("#songtitle").text();
    //   } else {
    //     playing = true;
    //     $(".playPauseBtn").html(pauseIconHTML + "Pause");
    //     document.title = playIcon + $("#songtitle").text();
    //   }
    // });

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
});
</script>