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

    // settingInput
    $(".settingInput")
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