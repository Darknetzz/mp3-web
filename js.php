<!--
/* ────────────────────────────────────────────────────────────────────────── */
/*                                 JAVASCRIPT                                 */
/* ────────────────────────────────────────────────────────────────────────── */
-->
<script>
  var playlistTable         = $('#playlistTable');
  // var pauseIcon             = "⏸ ";
  // var playIcon              = "⏵ ";
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
  var csrfToken             = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>';
  var audioPath             = <?= json_encode(getConfig("audio_path")) ?>;
  var siteTitle             = <?= json_encode(getConfig("site_title")) ?>;
  var defaultVolume         = <?= json_encode((float) getConfig("default_volume")) ?>;
  var stateKey              = "playerState";
  var songName              = null;

  /* ─────────────────────────── FUNCTION: songUrl ──────────────────────────── */
  // Absolute, encoded URL for a song (Cast devices need an absolute URL).
  function songUrl(name) {
    return new URL(audioPath.replace(/\/+$/, "") + "/" + encodeURIComponent(name), window.location.href).href;
  }

  /* ──────────────────────────────── player ────────────────────────────────── */
  // Routes playback to the local <audio> element, or to `player.remote`
  // (set by cast.js while a Cast session is active).
  var player = {
    remote: null,
    audio: function() {
      return $("audio")[0];
    },
    isRemote: function() {
      return this.remote !== null;
    },
    load: function(name, opts) {
      opts = opts || {};
      var autoplay  = opts.autoplay !== false;
      var startTime = opts.startTime || 0;
      if (this.remote) {
        this.remote.load(songUrl(name), name, startTime, autoplay);
        return;
      }
      var a = this.audio();
      $("#audioSource").attr("src", songUrl(name));
      a.load();
      if (startTime > 0) {
        $(a).one("loadedmetadata", function() { a.currentTime = startTime; });
      }
      if (autoplay) {
        this.play();
      }
    },
    play: function() {
      if (this.remote) return this.remote.play();
      var p = this.audio().play();
      if (p && p.catch) p.catch(function(e) { console.log("Playback was blocked:", e); });
    },
    pause: function() {
      if (this.remote) return this.remote.pause();
      this.audio().pause();
    },
    stop: function() {
      if (this.remote) return this.remote.stop();
      var a = this.audio();
      a.pause();
      $("#audioSource").attr("src", "");
      a.removeAttribute("src");
      a.load();
    },
    seek: function(t) {
      if (this.remote) return this.remote.seek(t);
      this.audio().currentTime = t;
    },
    setVolume: function(v) {
      if (this.remote) return this.remote.setVolume(v);
      this.audio().volume = v;
    },
    getTime: function() {
      if (this.remote) return this.remote.getTime();
      return this.audio().currentTime;
    },
    getDuration: function() {
      var d = this.remote ? this.remote.getDuration() : this.audio().duration;
      return (isFinite(d) && d > 0) ? d : 0;
    },
  };

  /* ────────────────────────── FUNCTION: setPlayingUI ──────────────────────── */
  function setPlayingUI(isPlaying) {
    playing = isPlaying;
    var playPauseBtn = $(".playPauseBtn");
    if (isPlaying) {
      playPauseBtn.removeClass("btn-outline-success btn-outline-warning").addClass("btn-success");
      playPauseBtn.html(pauseIconHTML);
      changeFavicon("favicon_play.ico");
    } else {
      playPauseBtn.removeClass("btn-success btn-outline-success").addClass("btn-outline-warning");
      playPauseBtn.html(playIconHTML);
      changeFavicon("favicon_pause.ico");
    }
    if ('mediaSession' in navigator) {
      navigator.mediaSession.playbackState = isPlaying ? "playing" : "paused";
    }
    if (window.waveform) {
      window.waveform.setPlaying(isPlaying);
    }
  }

  /* ───────────────────────── FUNCTION: updateVolumeIcon ───────────────────── */
  function updateVolumeIcon(v) {
    if (v == 0) {
      $(".volumeIcon").html('<?= icon("volume-mute-fill", 2) ?>');
    } else if (v <= 0.5) {
      $(".volumeIcon").html('<?= icon("volume-down-fill", 2) ?>');
    } else {
      $(".volumeIcon").html('<?= icon("volume-up-fill", 2) ?>');
    }
    $(".audioVolume").text(Math.round(v * 100) + "%");
  }

  /* ─────────────────────── FUNCTION: updateMediaMetadata ──────────────────── */
  function updateMediaMetadata(title) {
    if (!('mediaSession' in navigator) || typeof MediaMetadata === "undefined") return;
    navigator.mediaSession.metadata = new MediaMetadata({ title: title, artist: siteTitle });
  }

  /* ──────────────────────── FUNCTION: findIndexByFilename ─────────────────── */
  function findIndexByFilename(name) {
    var rows = playlistTable.bootstrapTable('getData');
    for (var i = 0; i < rows.length; i++) {
      if (urldecode(rows[i].filename).trim() === name) {
        return rows[i].id;
      }
    }
    return null;
  }

  /* ─────────────────────────── FUNCTION: saveState ────────────────────────── */
  function saveState() {
    try {
      localStorage.setItem(stateKey, JSON.stringify({
        filename: songName,
        time:     songName ? player.getTime() : 0,
        loop:     loop,
        shuffle:  shuffle,
      }));
    } catch (e) {}
  }

  /* ────────────────────────── FUNCTION: restoreState ──────────────────────── */
  function restoreState() {
    var state = null;
    try {
      state = JSON.parse(localStorage.getItem(stateKey) || "null");
    } catch (e) {}
    if (!state) return;
    if (state.loop && !loop) toggleLoop();
    if (state.shuffle && !shuffle) toggleShuffle();
    if (!state.filename) return;
    var index = findIndexByFilename(state.filename);
    if (index === null) {
      try { localStorage.removeItem(stateKey); } catch (e) {}
      return;
    }
    playSong(index, { autoplay: false, startTime: +state.time || 0 });
  }

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
    // if (playing) {
    //   title = playIcon + title;
    // } else {
    //   title = pauseIcon + title;
    // }
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
  function playSong(index, opts) {
    opts = opts || {};
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
    updateMediaMetadata(songName);
    player.load(songName, { autoplay: opts.autoplay !== false, startTime: opts.startTime || 0 });
    var activeRow = $("tr.songrow[data-uniqueid='" + index + "']");
    window.currentIndex = index;
    activeRow.addClass(activeClass).children().addClass(activeClass);
    scrollToActiveSong();
  }

  /* ─────────────────────────── FUNCTION: pauseSong ────────────────────────── */
  function pauseSong() {
    player.pause();
    saveState();
  }

  /* ────────────────────────── FUNCTION: resumeSong ────────────────────────── */
  function resumeSong() {
    if (!songName) return;
    updateTitle(songName);
    player.play();
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
    player.stop();
    window.currentIndex = undefined;
    songName = null;
    setPlayingUI(false);
    if (window.waveform) window.waveform.clear();
    $("tr.songrow").removeClass(activeClass).children().removeClass(activeClass);
    $(".audioCurrentTime, .audioDuration").text("0:00");
    $(".audioSlider").val(0);
    duration = 0;
    updateTitle();
    saveState();
  }

  /* ───────────────────────── FUNCTION: prevSong ───────────────────────── */
  function prevSong() {
    if (player.getTime() > 5) {
      player.seek(0);
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
    player.audio().loop = loop; // Remote looping is handled in cast.js
    console.log("Loop: " + loop);
    $(".toggleLoopBtn").toggleClass("active", loop);
    saveState();
  }

  /* ───────────────────────── FUNCTION: toggleShuffle ──────────────────────── */
  function toggleShuffle() {
    shuffle = !shuffle;
    console.log("Shuffle: " + shuffle);
    $(".toggleShuffleBtn").toggleClass("active", shuffle);
    saveState();
  }

  /* ───────────────────────── FUNCTION: seekBy ─────────────────────────────── */
  function seekBy(seconds) {
    if (!songName) return;
    var t = player.getTime() + seconds;
    var d = player.getDuration();
    player.seek(Math.max(0, d ? Math.min(t, d - 0.5) : t));
    updateTime();
  }

  /* ───────────────────────── FUNCTION: changeVolume ───────────────────────── */
  function changeVolume(delta) {
    var slider = $("#volumeSlider");
    var current = slider.length ? parseFloat(slider.val()) : player.audio().volume;
    var v = Math.round(Math.min(1, Math.max(0, current + delta)) * 100) / 100;
    if (slider.length) {
      slider.val(v).trigger("input");
    } else {
      player.setVolume(v);
    }
  }

  /* ────────────────────────── FUNCTION: formatTime ────────────────────────── */
  function formatTime(seconds) {
    const minutes = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return minutes + ":" + (secs < 10 ? "0" : "") + secs;
  }

  /* ────────────────────────── FUNCTION: updateTime ────────────────────────── */
  function updateTime() {
    currentTime = player.getTime() || 0;
    var d = player.getDuration();
    if (d) {
      duration = d;
      $(".audioDuration").text(formatTime(duration));
    }
    $(".audioCurrentTime").text(formatTime(currentTime));
    $(".audioSlider").attr("max", duration);
    $(".audioSlider").val(currentTime);
    if ('mediaSession' in navigator && navigator.mediaSession.setPositionState && duration > 0 && !player.isRemote()) {
      try {
        navigator.mediaSession.setPositionState({
          duration:     duration,
          position:     Math.min(currentTime, duration),
          playbackRate: player.audio().playbackRate || 1,
        });
      } catch (e) {}
    }
  }

  /* ─────────────────────────── FUNCTION: showToast ────────────────────────── */
  function showToast(message, type = "info") {
    if (typeof(message) === "object") {
      message = JSON.stringify(message);
    }
    toastObj = `
      <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true"
        data-bs-autohide="false" data-bs-toggle="toast">
        <div class="toast-header">
          <!--
            <strong class="">`+type+`</strong>
            <small>11 mins ago</small>
          -->
          <button type="button" class="ms-2 btn-close" data-bs-dismiss="toast"
            aria-label="Close"></button>
        </div>
        <div class="toast-body text-`+type+`">`+message+`</div>
      </div>
    `;
    $("#apiResponse").append(toastObj);
    var $toast = $("#apiResponse .toast").last();
    setTimeout(function() {
      $toast.fadeOut(500, function() { $(this).remove(); });
    }, 5000);
    // $("#apiResponse").append(`
    //   <div class='toast align-items-center text-bg-`+type+` border-0' role='alert' aria-live='assertive' aria-atomic='true'>
    //     <div class='d-flex'>
    //       <div class='toast-body'>
    //         `+message+`
    //       </div>
    //       <button type='button' class='btn-close btn-close-white me-2 m-auto' data-bs-dismiss='toast' aria-label='Close'></button>
    //     </div>
    //   </div>
    // `);
    // var toast = new bootstrap.Toast($("#apiResponse .toast").last()[0]);
    // toast.show();
  }

  /* ─────────────────────────── FUNCTION: api ─────────────────────────── */
  function api(action, data = {}, method = "GET", callback = null) {
    $.ajax({
      url: apiURL,
      type: method,
      data: { action: action, csrf_token: csrfToken, ...data },
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

  /* ──────────────────────── FUNCTION: changeFavicon ──────────────────────── */
  function changeFavicon(favicon) {
    var link = document.querySelector("link[rel~='icon']");
    if (!link) {
        link = document.createElement('link');
        link.rel = 'icon';
        document.head.appendChild(link);
    }
    link.href = favicon;
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
      params: { csrf_token: csrfToken },
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
    var storedVolume = NaN;
    try {
      var rawVolume = localStorage.getItem('volume');
      if (rawVolume !== null) storedVolume = parseFloat(rawVolume);
    } catch (e) {}
    var initialVolume = (!isNaN(storedVolume) && storedVolume >= 0 && storedVolume <= 1) ? storedVolume : defaultVolume;
    console.log("Initial volume: " + initialVolume);
    $("#volumeSlider").val(initialVolume);
    audioElement.volume = initialVolume;
    updateVolumeIcon(initialVolume);

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
    var tick = 0;
    setInterval(function() {
      updateTime();
      if (playing && ++tick % 5 === 0) {
        saveState();
      }
    }, 1000);
    window.addEventListener("pagehide", saveState);

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
          data: { action: 'rm', file: file, csrf_token: csrfToken },
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
        window.open(songUrl(file), '_blank');
      } else if (field === 'queue') {
        queueSong(rowid);
      } else {
        window.currentIndex = row.id;
        playSong(rowid);
      }
    });

    /* ────────────────────────────── NOTE: audioSlider ───────────────────────── */
    $(".audioSlider").on('input', function() {
      currentTime = parseFloat($(this).val());
      player.seek(currentTime);
    });

    /* ────────────────────────────── NOTE: volumeSlider ──────────────────────── */
    $("#volumeSlider").on('input', function() {
      var v = parseFloat($(this).val());
      if (!player.isRemote()) {
        try { localStorage.setItem('volume', v); } catch (e) {}
      }
      player.setVolume(v);
      updateVolumeIcon(v);
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
    $(audioElement).on('loadedmetadata canplay', function() {
      if (player.isRemote() || !isFinite(audioElement.duration)) return;
      duration = audioElement.duration;
      $(".audioDuration").text(formatTime(duration));
      $(".ctrlBtn").prop("disabled", false);
      updateTime();
    });

    /* ─────────────────────────────── NOTE: Ended ────────────────────────────── */
    $(audioElement).on('ended', function() {
      if (player.isRemote()) return;
      nextSong();
    });

    /* ─────────────────────────────── NOTE: Play ─────────────────────────────── */
    $(audioElement).on('play', function() {
      if (player.isRemote()) return;
      setPlayingUI(true);
    });

    /* ────────────────────────────── NOTE: Pause ─────────────────────────────── */
    $(audioElement).on('pause', function() {
      if (player.isRemote()) return;
      setPlayingUI(false);
      saveState();
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
      var seekHandlers = {
        seekto:       function(d) { player.seek(d.seekTime); updateTime(); },
        seekbackward: function(d) { seekBy(-(d.seekOffset || 10)); },
        seekforward:  function(d) { seekBy(d.seekOffset || 10); },
      };
      $.each(seekHandlers, function(action, handler) {
        try { navigator.mediaSession.setActionHandler(action, handler); } catch (e) {}
      });
    }

    /* ─────────────────────────── NOTE: Keyboard shortcuts ───────────────────── */
    // Space: play/pause · ←/→: seek 5s · Shift+←/→: prev/next · ↑/↓: volume · M: mute · L: loop · S: shuffle
    $(document).keydown(function(e) {
      if (e.ctrlKey || e.metaKey || e.altKey) return;
      var target = $(e.target);
      if (target.is('textarea, select, [contenteditable="true"]')) return;
      if (target.is('input') && !target.is('[type="range"], [type="checkbox"], [type="radio"], [type="button"]')) return;
      // Let a focused slider handle its own arrow keys
      if (target.is('[type="range"]') && e.code.indexOf('Arrow') === 0) return;
      if ($('.modal.show').length) return;

      var handled = true;
      switch (e.code) {
        case 'Space':      toggleSong(); break;
        case 'ArrowLeft':  e.shiftKey ? prevSong() : seekBy(-5); break;
        case 'ArrowRight': e.shiftKey ? nextSong() : seekBy(5); break;
        case 'ArrowUp':    changeVolume(0.05); break;
        case 'ArrowDown':  changeVolume(-0.05); break;
        case 'KeyM':       $(".volumeIcon").first().trigger('click'); break;
        case 'KeyL':       toggleLoop(); break;
        case 'KeyS':       toggleShuffle(); break;
        default:           handled = false;
      }
      if (handled) e.preventDefault();
    });

    /* ─────────────────────────── NOTE: Restore state ────────────────────────── */
    restoreState();

    // Helper function to collect array values for a given base key within a container
    function collectArrayValues(baseKey, container) {
      var values = [];
      var selector = container ? container.find(".settingInput[data-key^='" + baseKey + "-']") : $(".settingInput[data-key^='" + baseKey + "-']");
      selector.each(function() {
        var value = $(this).val();
        if (value && value.trim() !== "") {
          values.push(value.trim());
        }
      });
      return values;
    }

    // settingInput
    $(document)
      .on("keydown", ".settingInput", function() {
        setWarning($(this));
        $(".reloadCfgBtn").show();
        $(".resetCfgBtn").show();
      })
      .on("change", ".settingInput", function() {
        $(".reloadCfgBtn").show();
        $(".resetCfgBtn").show();
        var key   = $(this).data("key");
        if (typeof(key) === "undefined") {
          console.log("Key is undefined.", key);
          return;
        }
        
        // Check if this is an array item (key contains a dash followed by number or "new")
        var arrayMatch = key.match(/^(.+)-(\d+|new)$/);
        if (arrayMatch) {
          // This is an array item - collect all array values from the same config list
          var baseKey = arrayMatch[1];
          var container = $(this).closest(".configList");
          var values = collectArrayValues(baseKey, container);
          console.log("Array key: " + baseKey + ", Values: " + JSON.stringify(values));
          api("setconfig", { config: { key: baseKey, value: values } }, "POST");
          setSuccess($(this));
        } else {
          // Regular (non-array) config item
          if ($(this).is(":checkbox")) {
            var value = ($(this).is(":checked") ? true : false);
          } else {
            var value = $(this).val();
          }
          console.log("Key: " + key + ", Value: " + value);
          api("setconfig", { config: { key: key, value: value } }, "POST");
          setSuccess($(this));
        }
      });

    // Setting Range
    $(".settingRange").on("input", function() {
      var key      = $(this).attr("id");
      var value    = $(this).val();
      var valueObj = $(this).attr("data-valueobject");
      $("#" + valueObj).text(Math.round(value * 100) + "%");
    });

    // Settings reset
    $(".resetCfgBtn").on("click", function() {
      $(".resetCfgBtn").hide();
      $(".reloadCfgBtn").hide();
    });

    $(document).on("click", ".array-minus", function() {
      var key = $(this).data("key");
      var inputGroup = $(this).closest(".configListItem");
      var container = $(this).closest(".configList");
      inputGroup.remove();
      
      // Rebuild and save the array after removal
      var values = collectArrayValues(key, container);
      console.log("Array key after removal: " + key + ", Values: " + JSON.stringify(values));
      api("setconfig", { config: { key: key, value: values } }, "POST");
      
      if (container.find(".configListItem").length === 0) {
        container.append('<div class="text-muted">No items in array</div>');
      }
    });

    $(".array-plus").on("click", function() {
      var key = $(this).data("key");
      var newItem = `
        <div class='input-group m-2 configListItem'>
          <input type='text' class='form-control settingInput' placeholder='New item' data-key='` + key + `-new'>
          <button type='button' class='btn btn-outline-danger btn-sm array-minus' data-key='` + key + `'>
            <span aria-hidden='true'>&minus;</span>
          </button>
        </div>`;
      $(".configList").append(newItem);
    });

});
</script>