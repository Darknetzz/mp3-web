<!--
/* ────────────────────────────────────────────────────────────────────────── */
/*                                 JAVASCRIPT                                 */
/* ────────────────────────────────────────────────────────────────────────── */
-->
<script>
  var playlistTable         = $('#playlistTable');
  // var pauseIcon             = "⏸ ";
  // var playIcon              = "⏵ ";
  // Same markup as icon() in functions.php; name must be in assets/icons/build.py
  var ICON_SPRITE           = <?= json_encode(ICON_SPRITE) ?>;
  function lucideIcon(name, cls) {
    return '<svg class="lucide icon-' + name + (cls ? ' ' + cls : '') + '" aria-hidden="true" focusable="false">' +
           '<use href="' + ICON_SPRITE + '#' + name + '"></use></svg>';
  }
  var pauseIconHTML         = '<?= icon('pause', filled: true) ?>';
  var playIconHTML          = '<?= icon('play', filled: true) ?>';
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
  // Autoplay (play the next song when one ends): the playbar toggle is remembered
  // per browser; the autoplay_next setting is the default.
  var autoplayNext          = (function() {
    try {
      var saved = localStorage.getItem("autoplayNext");
      if (saved === "1" || saved === "0") return saved === "1";
    } catch (e) {}
    return <?= json_encode((bool) getConfig("autoplay_next")) ?>;
  })();
  var seekStep              = <?= json_encode(max(1, (int) getConfig("seek_step"))) ?>;
  var startShuffle          = <?= json_encode((bool) getConfig("start_shuffle")) ?>;
  var startLoop             = <?= json_encode((bool) getConfig("start_loop")) ?>;
  var rememberPosition      = <?= json_encode((bool) getConfig("remember_position")) ?>;
  var stateKey              = "playerState";
  var songName              = null;
  var sessionCode           = <?= json_encode(sessionValidCode($_SESSION['session_code'] ?? null) ? $_SESSION['session_code'] : null) ?>;
  var displayName           = <?= json_encode(sessionDisplayName()) ?>;

  /* ───────────────────────── Listening session hooks ──────────────────────── */
  // session.js defines window.sessionSync. These stay no-ops outside a session.
  function sessionBlocked() {
    if (window.sessionSync && sessionSync.blocks()) {
      sessionSync.denied();
      return true;
    }
    return false;
  }
  function sessionChanged(override) {
    if (window.sessionSync) sessionSync.localChange(override);
  }
  // Called when the current song finishes (locally or on a Cast speaker).
  function onSongEnded() {
    if (!autoplayNext && !loop) {
      // Stop at the end of the song instead of moving on.
      if (window.sessionSync && sessionSync.active() && !sessionSync.canControl()) return;
      setPlayingUI(false);
      sessionChanged({ playing: false });
      return;
    }
    if (window.sessionSync && sessionSync.active()) {
      sessionSync.songEnded();
      return;
    }
    if (loop && window.currentIndex !== undefined) {
      playSong(window.currentIndex);
      return;
    }
    nextSong();
  }

  /* ───────────────────────── Table sorters (bootstrap-table) ──────────────── */
  function durationSorter(a, b) {
    var secs = function(v) {
      var parts = String(v).replace(/<[^>]*>/g, "").trim().split(":").map(Number);
      return parts.reduce(function(acc, n) { return acc * 60 + (isNaN(n) ? 0 : n); }, 0);
    };
    return secs(a) - secs(b);
  }
  function sizeSorter(a, b) {
    return (parseFloat(String(a).replace(/<[^>]*>/g, "")) || 0) - (parseFloat(String(b).replace(/<[^>]*>/g, "")) || 0);
  }

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
      if (p && p.catch) p.catch(function(e) {
        console.log("Playback was blocked:", e);
        if (e && e.name === "NotAllowedError" && window.sessionSync) sessionSync.playBlocked();
      });
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
      $(".volumeIcon").html('<?= icon("volume-x", 2) ?>');
    } else if (v <= 0.5) {
      $(".volumeIcon").html('<?= icon("volume-1", 2) ?>');
    } else {
      $(".volumeIcon").html('<?= icon("volume-2", 2) ?>');
    }
    $(".audioVolume").text(Math.round(v * 100) + "%");
  }

  /* ─────────────────────── FUNCTION: updateMediaMetadata ──────────────────── */
  // Artwork is the song's cover once known (see resolveCover), else the app logo.
  function updateMediaMetadata(title) {
    if (!('mediaSession' in navigator) || typeof MediaMetadata === "undefined") return;
    var art = coverKnown(title) ? coverCache[title] : null;
    navigator.mediaSession.metadata = new MediaMetadata({
      title:   title,
      artist:  siteTitle,
      artwork: [ art ? { src: art } : { src: fallbackArtwork, sizes: "512x512", type: "image/png" } ],
    });
  }

  /* ──────────────────────────────── Cover art ─────────────────────────────── */
  // Covers come from cover.php (never extracted while rendering the page).
  // coverCache: song name -> absolute 512px cover URL, or null when it has none.
  var fallbackArtwork = new URL("assets/branding/logo.png", window.location.href).href;
  var coverCache      = {};
  function coverKnown(name) {
    return Object.prototype.hasOwnProperty.call(coverCache, name);
  }
  // Absolute cover URL for a song; reuses the versioned (?v=mtime) URL from its row.
  function coverUrl(name, size) {
    var id  = findIndexByFilename(name);
    var row = id !== null ? getSongByIndex(id) : null;
    var m   = row && /src="([^"]+)"/.exec(row.cover || "");
    var url = m ? m[1].replace(/&amp;/g, "&") : "cover.php?file=" + encodeURIComponent(name) + "&size=sm";
    url = url.replace(/([?&])size=sm\b/, "$1size=" + (size || "sm"));
    return new URL(url, window.location.href).href;
  }
  // Calls cb(url) with the large cover URL, or cb(null) when the song has none.
  function resolveCover(name, cb) {
    if (!name) return cb(null);
    if (coverKnown(name)) return cb(coverCache[name]);
    var url = coverUrl(name, "lg");
    var img = new Image();
    img.onload  = function() { coverCache[name] = url;  cb(url); };
    img.onerror = function() { coverCache[name] = null; cb(null); };
    img.src = url;
  }
  // Playbar cover + Media Session artwork for the current song.
  function updateCoverArt(name) {
    var el = $("#playerCoverImg");
    if (!name || findIndexByFilename(name) === null) {
      el.attr("hidden", true).removeAttr("src");
      return;
    }
    resolveCover(name, function(url) {
      if (name !== songName) return;
      if (url) el.attr("src", url).removeAttr("hidden");
      else el.attr("hidden", true).removeAttr("src");
      updateMediaMetadata(name);
    });
  }

  /* ───────────────────────────── Library model ───────────────────────────── */
  // Every song in the music folder, captured once from the server-rendered table.
  // The table itself may show a playlist, so songs are always looked up here.
  var library     = null;   // rows in folder order
  var libraryById = {};     // String(id) -> row
  var libraryByName = {};   // file name -> String(id)
  function initLibrary() {
    if (library) return;
    library = playlistTable.bootstrapTable('getData').slice();
    library.forEach(function(row) {
      row.id = String(row.id).trim();
      var name = urldecode(row.filename).trim();
      row._song = name;
      libraryById[row.id] = row;
      libraryByName[name] = row.id;
    });
  }

  /* ──────────────────────── FUNCTION: findIndexByFilename ─────────────────── */
  function findIndexByFilename(name) {
    initLibrary();
    return Object.prototype.hasOwnProperty.call(libraryByName, name) ? libraryByName[name] : null;
  }

  /* ──────────────────────────── Playback context ──────────────────────────── */
  // Where playback was started from: the library or a playlist. Next/previous/
  // shuffle stay inside it, even if another view is shown in the table.
  var playContext = { type: "library", id: null };
  function currentView() {
    return window.playlistsUI ? playlistsUI.currentView() : { type: "library", id: null };
  }
  function sameContext(a, b) {
    return a.type === b.type && (a.type === "library" || a.id === b.id);
  }
  function setContextFromView() {
    var view = currentView();
    playContext = { type: view.type, id: view.id };
  }
  // Song ids of the current context, in play order.
  function contextOrder() {
    initLibrary();
    if (sameContext(playContext, currentView())) {
      return playlistTable.bootstrapTable('getData').map(function(r) { return String(r.id); });
    }
    if (playContext.type === "playlist" && window.playlistsUI) {
      var ids = playlistsUI.orderFor(playContext.id);
      if (ids && ids.length) return ids;
    }
    return library.map(function(r) { return r.id; });
  }

  /* ─────────────────────────── FUNCTION: saveState ────────────────────────── */
  function saveState() {
    if (!rememberPosition) return;
    try {
      localStorage.setItem(stateKey, JSON.stringify({
        filename: songName,
        time:     songName ? player.getTime() : 0,
        context:  playContext,
        loop:     loop,
        shuffle:  shuffle,
      }));
    } catch (e) {}
  }

  /* ────────────────────────── FUNCTION: restoreState ──────────────────────── */
  function restoreState() {
    if (sessionCode) return; // the session's state wins
    var applyStartToggles = function() {
      if (startLoop && !loop) toggleLoop();
      if (startShuffle && !shuffle) toggleShuffle();
    };
    var state = null;
    if (rememberPosition) {
      try {
        state = JSON.parse(localStorage.getItem(stateKey) || "null");
      } catch (e) {}
    } else {
      try { localStorage.removeItem(stateKey); } catch (e) {}
    }
    if (!state) {
      applyStartToggles();
      return;
    }
    if (state.loop && !loop) toggleLoop();
    if (state.shuffle && !shuffle) toggleShuffle();
    if (!state.filename) return;
    var index = findIndexByFilename(state.filename);
    if (index === null) {
      try { localStorage.removeItem(stateKey); } catch (e) {}
      return;
    }
    if (state.context && (state.context.type === "library" || state.context.type === "playlist")) {
      playContext = { type: state.context.type, id: state.context.id || null };
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
    var row = getSongByIndex(index);
    return row ? row._song : null;
  }

  /* ──────────────────────── FUNCTION: getSongByIndex ──────────────────────── */
  function getSongByIndex(index) {
    initLibrary();
    return libraryById[String(index)] || null;
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
    updateCoverArt(songName);
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
    var row = $("tr.songrow[data-uniqueid='" + currentIndex + "']")[0];
    if (row) row.scrollIntoView({ behavior: "smooth", block: "center" });
  }

  /* ─────────────────────────────── Row selection ──────────────────────────── */
  // A single click selects a row (.songSelected); a double-click, Enter or the
  // play button on the cover plays it. Separate from the "now playing" row.
  var selectedId = null;
  function songRows() {
    return $("#playlistTable > tbody > tr[data-uniqueid]");
  }
  function selectSong(id, opts) {
    selectedId = (id === null || id === undefined) ? null : String(id);
    songRows().filter(".songSelected").removeClass("songSelected");
    if (selectedId === null) return;
    var row = songRows().filter("[data-uniqueid='" + selectedId + "']");
    row.addClass("songSelected");
    if (opts && opts.scroll && row[0]) ensureRowVisible(row[0]);
  }
  function moveSelection(delta) {
    var rows = songRows();
    if (!rows.length) return;
    var idx = rows.index(rows.filter(".songSelected"));
    idx = idx < 0 ? (delta > 0 ? 0 : rows.length - 1) : Math.max(0, Math.min(rows.length - 1, idx + delta));
    selectSong(rows.eq(idx).attr("data-uniqueid"), { scroll: true });
  }
  // Scroll a row into view, keeping clear of the fixed playbar.
  function ensureRowVisible(row) {
    var r = row.getBoundingClientRect();
    var top = 0, bottom = window.innerHeight;
    var bar = $(".audio-player-container")[0];
    if (bar) {
      var b = bar.getBoundingClientRect();
      if (b.top <= 1) top = b.bottom; else bottom = b.top;
    }
    if (r.top < top) window.scrollBy(0, r.top - top - 8);
    else if (r.bottom > bottom) window.scrollBy(0, r.bottom - bottom + 8);
  }
  function playSelected() {
    if (selectedId === null) return;
    setContextFromView();
    playSong(selectedId);
  }

  /* ─────────────────────────── FUNCTION: playSong ─────────────────────────── */
  function playSong(index, opts) {
    opts = opts || {};
    if (sessionBlocked()) return;
    console.log("Playing song at index: " + index);
    index = String(index);
    var playingRow = getSongByIndex(index);
    if (!playingRow) {
      showToast("That song is no longer in the library.", "danger");
      return;
    }
    $("tr.songrow").removeClass(activeClass).children().removeClass(activeClass);

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
    if (window.playlistsUI) playlistsUI.songChanged();
    sessionChanged({ position: opts.startTime || 0, playing: opts.autoplay !== false });
  }

  /* ─────────────────────────── FUNCTION: pauseSong ────────────────────────── */
  function pauseSong() {
    if (sessionBlocked()) return;
    player.pause();
    saveState();
    sessionChanged({ playing: false });
  }

  /* ────────────────────────── FUNCTION: resumeSong ────────────────────────── */
  function resumeSong() {
    if (!songName) return;
    if (sessionBlocked()) return;
    updateTitle(songName);
    player.play();
    sessionChanged({ playing: true });
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
  function randomSong(fromView) {
    if (sessionBlocked()) return;
    if (fromView) setContextFromView();
    var order = contextOrder();
    if (!order.length) return;
    if (order.length > 1) {
      order = order.filter(function(id) { return id !== String(currentIndex); });
    }
    playSong(order[Math.floor(Math.random() * order.length)]);
  }

  /* ─────────────────────────── FUNCTION: playFirst ────────────────────────── */
  // Play the first song of the view on screen (library or playlist).
  function playFirst() {
    if (sessionBlocked()) return;
    setContextFromView();
    var order = contextOrder();
    if (order.length) playSong(order[0]);
  }

  /* ─────────────────────────── FUNCTION: stopSong ─────────────────────────── */
  function stopSong() {
    if (sessionBlocked()) return;
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
    sessionChanged();
  }

  /* ───────────────────────── FUNCTION: prevSong ───────────────────────── */
  function prevSong() {
    if (sessionBlocked()) return;
    if (player.getTime() > 5) {
      player.seek(0);
      sessionChanged({ position: 0 });
      return;
    }
    if (shuffle) {
      randomSong();
      return;
    }
    var order = contextOrder();
    if (!order.length) return;
    var pos = order.indexOf(String(currentIndex));
    playSong(order[pos <= 0 ? order.length - 1 : pos - 1]);
  }

  /* ─────────────────────────── FUNCTION: nextSong ─────────────────────────── */
  function nextSong() {
    if (sessionBlocked()) return;
    if (queue.length > 0) {
      var nextQueueIndex = queue.shift();
      playSong(nextQueueIndex);
      return;
    }
    if (shuffle) {
      randomSong();
      return;
    }
    var order = contextOrder();
    if (!order.length) return;
    var pos = order.indexOf(String(currentIndex));
    playSong(order[(pos + 1) % order.length]);
  }

  /* ───────────────────────── FUNCTION: getQueuedSong ──────────────────────── */
  function getQueuedSong() {
    if (queue.length > 0) {
      return queue[0];
    }
    return null;
  }

  /* ─────────────────────────── FUNCTION: queueSong ────────────────────────── */
  // opts.next: insert at the front ("Play next"); opts.silent: no toast
  function queueSong(index, opts) {
    opts = opts || {};
    if (sessionBlocked()) return;
    var ids = (Array.isArray(index) ? index : [index]).map(String).filter(function(id) { return !!getSongByIndex(id); });
    if (!ids.length) return;
    if (opts.next) {
      queue.unshift.apply(queue, ids);
    } else {
      queue.push.apply(queue, ids);
    }
    sessionChanged();
    if (!opts.silent) {
      var label = ids.length === 1 ? getSongNameByIndex(ids[0]) : ids.length + " songs";
      showToast((opts.next ? "Playing next: " : "Added to queue: ") + $("<div>").text(label).html(), "success");
    }
    $("#nextInQueueText").parent().show();
    $("#nextInQueueText").text(getSongNameByIndex(getQueuedSong()));
  }

  /* ────────────────────────── FUNCTION: toggleLoop ────────────────────────── */
  function toggleLoop() {
    if (sessionBlocked()) return;
    loop = !loop;
    player.audio().loop = loop; // Remote looping is handled in cast.js
    console.log("Loop: " + loop);
    $(".toggleLoopBtn").toggleClass("active", loop);
    saveState();
    sessionChanged();
  }

  /* ───────────────────────── FUNCTION: toggleAutoplay ─────────────────────── */
  function toggleAutoplay() {
    autoplayNext = !autoplayNext;
    try { localStorage.setItem("autoplayNext", autoplayNext ? "1" : "0"); } catch (e) {}
    updateAutoplayBtn();
    showToast("Autoplay " + (autoplayNext ? "on: the next song plays automatically" : "off: playback stops after each song"), "info");
  }

  function updateAutoplayBtn() {
    $(".toggleAutoplayBtn")
      .toggleClass("active", autoplayNext)
      .attr("aria-pressed", autoplayNext ? "true" : "false")
      .attr("title", "Autoplay next song: " + (autoplayNext ? "on" : "off") + " (A)");
  }

  /* ───────────────────────── FUNCTION: toggleShuffle ──────────────────────── */
  function toggleShuffle() {
    if (sessionBlocked()) return;
    shuffle = !shuffle;
    console.log("Shuffle: " + shuffle);
    $(".toggleShuffleBtn").toggleClass("active", shuffle);
    saveState();
    sessionChanged();
  }

  /* ───────────────────────── FUNCTION: seekBy ─────────────────────────────── */
  function seekBy(seconds) {
    if (!songName) return;
    if (sessionBlocked()) return;
    var t = player.getTime() + seconds;
    var d = player.getDuration();
    t = Math.max(0, d ? Math.min(t, d - 0.5) : t);
    player.seek(t);
    updateTime();
    sessionChanged({ position: t });
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

    // Capture the full library before anything can load a playlist into the table
    initLibrary();

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
      // Must be a function: Dropzone 6.2+ deep-merges an object `params` onto its
      // default params() function, so a plain object is silently never sent.
      params: function () { return { csrf_token: csrfToken }; },
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

    /* ───────────────────────────── Cursor pointer ───────────────────────────── */
    $('#playlistTable').on('post-body.bs.table', function () {
      $('#playlistTable').find('td[data-field="name"]').addClass('cursor-pointer');
      if (selectedId !== null) selectSong(selectedId); // keep the selection after re-renders
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

      var rowid = String(row.id).trim();
      var file = getSongNameByIndex(rowid);

      // Heart and ⋯ menu are handled by playlists.js and never start playback
      if (field === 'favorite' || field === 'more') {
        if (window.playlistsUI) playlistsUI.cellClicked(field, rowid, $element, e);
        return;
      }

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
      } else if (field === 'cover') {
        // The cover is a play button (also the touch-friendly way to play).
        // Ignore the second click of a double-click so the song doesn't restart.
        var now = Date.now();
        selectSong(rowid);
        if (lastCoverPlay.id === rowid && now - lastCoverPlay.at < 500) return;
        lastCoverPlay = { id: rowid, at: now };
        setContextFromView();
        playSong(rowid);
      } else {
        // Single click only selects; focus the table so ↑/↓/Enter act on it
        selectSong(rowid);
        playlistTable[0].focus({ preventScroll: true });
      }
    });
    var lastCoverPlay = { id: null, at: 0 };

    // Double-click (double-tap on most touch browsers) plays the song
    var noDblClick = ['favorite', 'more', 'delete', 'download', 'queue', 'cover'];
    playlistTable.on('dbl-click-cell.bs.table', function (e, field, value, row) {
      if (noDblClick.indexOf(field) !== -1) return;
      selectSong(String(row.id).trim());
      playSelected();
    });
    // Don't select text when double-clicking a row
    playlistTable.on('mousedown', 'tbody > tr', function (e) {
      if (e.originalEvent && e.originalEvent.detail > 1) e.preventDefault();
    });
    playlistTable.attr('tabindex', '0');

    /* ────────────────────────────── NOTE: audioSlider ───────────────────────── */
    $(".audioSlider").on('input', function() {
      if (sessionBlocked()) {
        updateTime();
        return;
      }
      currentTime = parseFloat($(this).val());
      player.seek(currentTime);
      sessionChanged({ position: currentTime });
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
      onSongEnded();
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
        seekto:       function(d) { if (sessionBlocked()) return; player.seek(d.seekTime); updateTime(); sessionChanged({ position: d.seekTime }); },
        seekbackward: function(d) { seekBy(-(d.seekOffset || seekStep)); },
        seekforward:  function(d) { seekBy(d.seekOffset || seekStep); },
      };
      $.each(seekHandlers, function(action, handler) {
        try { navigator.mediaSession.setActionHandler(action, handler); } catch (e) {}
      });
    }

    /* ─────────────────────────── NOTE: Keyboard shortcuts ───────────────────── */
    // Space: play/pause · ←/→: seek 5s · Shift+←/→: prev/next · ↑/↓: volume · M: mute · L: loop · S: shuffle
    // While the song table has focus (click a row): ↑/↓ move the selection, Enter plays it, Esc leaves the table.
    $(document).on("keydown", function(e) {
      if (e.ctrlKey || e.metaKey || e.altKey) return;
      if (e.target === playlistTable[0] && !e.shiftKey && !$('.modal.show').length) {
        switch (e.code) {
          case 'ArrowUp':     moveSelection(-1); e.preventDefault(); return;
          case 'ArrowDown':   moveSelection(1);  e.preventDefault(); return;
          case 'Enter':
          case 'NumpadEnter': if (selectedId !== null) { playSelected(); e.preventDefault(); } return;
          case 'Escape':      playlistTable[0].blur(); return;
        }
      }
      var target = $(e.target);
      if (target.is('textarea, select, [contenteditable="true"]')) return;
      if (target.is('input') && !target.is('[type="range"], [type="checkbox"], [type="radio"], [type="button"]')) return;
      // Let a focused slider handle its own arrow keys
      if (target.is('[type="range"]') && e.code.indexOf('Arrow') === 0) return;
      if ($('.modal.show').length) return;

      var handled = true;
      switch (e.code) {
        case 'Space':      toggleSong(); break;
        case 'ArrowLeft':  e.shiftKey ? prevSong() : seekBy(-seekStep); break;
        case 'ArrowRight': e.shiftKey ? nextSong() : seekBy(seekStep); break;
        case 'ArrowUp':    changeVolume(0.05); break;
        case 'ArrowDown':  changeVolume(-0.05); break;
        case 'KeyM':       $(".volumeIcon").first().trigger('click'); break;
        case 'KeyL':       toggleLoop(); break;
        case 'KeyS':       toggleShuffle(); break;
        case 'KeyA':       toggleAutoplay(); break;
        default:           handled = false;
      }
      if (handled) e.preventDefault();
    });

    updateAutoplayBtn();

    /* ─────────────────────────── NOTE: Restore state ────────────────────────── */
    restoreState();

    /* ────────────────────────────────────────────────────────────────────────── */
    /*                                Settings modal                              */
    /* ────────────────────────────────────────────────────────────────────────── */
    var settingsModal = $("#configModal");
    var previewAccents = ["green", "blue", "purple", "pink", "orange", "red", "teal", "yellow"];
    var previewThemes  = { light: "light", arctic: "light", dark: "dark", oled: "dark", midnight: "dark", forest: "dark", sunset: "dark" };

    function settingValue(key) {
      var inputs = settingsModal.find(".settingInput[data-key='" + key + "']");
      if (!inputs.length) return undefined;
      if (inputs.is(":radio")) return inputs.filter(":checked").val();
      if (inputs.is(":checkbox")) return inputs.is(":checked");
      return inputs.val();
    }

    // Live preview for preset themes and accents (custom ones need a reload).
    function previewSetting(key, value) {
      var root = document.documentElement;
      if (key === "player_theme" && previewThemes[value]) {
        root.setAttribute("data-app-theme", value);
        root.setAttribute("data-bs-theme", previewThemes[value]);
      }
      if (key === "accent_color" && previewAccents.indexOf(value) !== -1) {
        root.setAttribute("data-accent", value);
        var dot = settingsModal.find(".accentDot");
        var swatches = { green: "#2fb344", blue: "#3b82f6", purple: "#a855f7", pink: "#ec4899", orange: "#f97316", red: "#ef4444", teal: "#14b8a6", yellow: "#eab308" };
        dot.css("background", swatches[value]);
      }
    }

    function updateConditionalRows() {
      settingsModal.find(".settingRow[data-show-when]").each(function() {
        var rule = String($(this).data("show-when"));
        var show;
        if (rule.indexOf("!=") !== -1) {
          var parts = rule.split("!=");
          show = String(settingValue(parts[0]) || "").trim() !== parts[1];
        } else {
          var eq = rule.split("=");
          show = String(settingValue(eq[0])) === eq[1];
        }
        $(this).toggleClass("settingCondHidden", !show);
      });
      updateSearch();
    }

    function saveSetting(key, value, row) {
      $.ajax({
        url: apiURL,
        type: "POST",
        dataType: "json",
        data: { action: "setconfig", csrf_token: csrfToken, config: { key: key, value: value } },
      }).then(function(res) {
        if (res.status !== "success") {
          showToast(res.response || "Could not save the setting.", "danger");
          return;
        }
        if (res.response === "No change.") return;
        settingsModal.find(".settingsFooter").show();
        if (row && row.length) {
          row.addClass("justSaved");
          setTimeout(function() { row.removeClass("justSaved"); }, 1500);
        }
        previewSetting(key, value);
      }, function() {
        showToast("Could not reach the server to save the setting.", "danger");
      });
    }

    function chipValues(chips) {
      return chips.find(".chipText").map(function() { return $(this).text(); }).get();
    }

    function addChip(input) {
      var chips = input.closest(".settingChips");
      var text = input.val().trim().replace(/^\./, "");
      if (!text) return;
      if (chipValues(chips).indexOf(text) === -1) {
        var chip = $('<span class="settingChip"><span class="chipText"></span><button type="button" class="chipRemove">&times;</button></span>');
        chip.find(".chipText").text(text);
        chip.find(".chipRemove").attr("aria-label", "Remove " + text);
        chip.insertBefore(input);
        saveSetting(chips.data("key"), chipValues(chips), chips.closest(".settingRow"));
      }
      input.val("");
    }

    settingsModal
      // Plain controls: switches, pills, selects, ranges, numbers, colors, text
      .on("change", ".settingInput", function() {
        var el  = $(this);
        var key = el.data("key");
        if (!key) return;
        var value;
        if (el.is(":radio")) {
          if (!el.is(":checked")) return;
          value = el.val();
        } else if (el.is(":checkbox")) {
          value = el.is(":checked");
        } else {
          value = el.val();
        }
        if (el.is("[type=color]")) {
          el.closest(".settingControl").find(".settingColorHex").text(value);
        }
        saveSetting(key, value, el.closest(".settingRow"));
        updateConditionalRows();
      })
      .on("keydown", "input[type=text].settingInput", function(e) {
        if (e.key === "Enter") {
          e.preventDefault();
          $(this).trigger("blur");
        }
      })
      // Range value label while dragging
      .on("input", ".settingRange", function() {
        var v = parseFloat($(this).val());
        var label = $(this).data("format") === "px" ? Math.round(v) + " px" : Math.round(v * 100) + "%";
        $(this).siblings(".settingRangeValue").text(label);
      })
      // Chips (array settings)
      .on("click", ".chipRemove", function() {
        var chips = $(this).closest(".settingChips");
        $(this).closest(".settingChip").remove();
        saveSetting(chips.data("key"), chipValues(chips), chips.closest(".settingRow"));
      })
      .on("keydown", ".chipAdd", function(e) {
        if (e.key === "Enter" || e.key === ",") {
          e.preventDefault();
          addChip($(this));
        } else if (e.key === "Backspace" && !$(this).val()) {
          $(this).prev(".settingChip").find(".chipRemove").trigger("click");
        }
      })
      .on("blur", ".chipAdd", function() {
        addChip($(this));
      })
      .on("click", ".settingChips", function(e) {
        if (e.target === this) $(this).find(".chipAdd").trigger("focus");
      })
      // Multi-select pills
      .on("change", ".multiOpt", function() {
        var group = $(this).closest(".settingMulti");
        var values = group.find(".multiOpt:checked").map(function() { return this.value; }).get();
        saveSetting(group.data("key"), values, group.closest(".settingRow"));
      });

    /* ──────────────────────────────── Search ─────────────────────────────────── */
    function updateSearch() {
      var q = String(settingsModal.find(".settingsSearchInput").val() || "").trim().toLowerCase();
      var anyVisible = false;
      settingsModal.find(".settingRow").each(function() {
        var match = !q || String($(this).data("search")).indexOf(q) !== -1;
        $(this).toggleClass("settingSearchHidden", !match);
      });
      // While searching, rows that are normally hidden (e.g. custom colors) are shown if they match.
      settingsModal.find(".settingsContent").toggleClass("searchActive", !!q);
      settingsModal.find(".settingsSection").each(function() {
        var rows = $(this).find(".settingRow:not(.settingSearchHidden)");
        var visible = (q ? rows : rows.not(".settingCondHidden")).length > 0;
        $(this).toggle(visible);
        if (visible) anyVisible = true;
      });
      settingsModal.find(".settingsEmpty").toggle(!anyVisible);
      settingsModal.find(".settingsNav").toggleClass("searching", !!q);
    }
    settingsModal.on("input", ".settingsSearchInput", updateSearch);

    /* ─────────────────────────── Section navigation ──────────────────────────── */
    settingsModal.on("click", ".settingsNavLink", function(e) {
      e.preventDefault();
      var target = document.getElementById($(this).data("target"));
      if (target) target.scrollIntoView({ behavior: "smooth", block: "start" });
    });
    settingsModal.find(".settingsBody").on("scroll", function() {
      var bodyTop = this.getBoundingClientRect().top;
      var current = null;
      settingsModal.find(".settingsSection:visible").each(function() {
        if (this.getBoundingClientRect().top - bodyTop <= 120) current = this.id;
      });
      if (this.scrollTop + this.clientHeight >= this.scrollHeight - 4) {
        current = settingsModal.find(".settingsSection:visible").last().attr("id");
      }
      if (!current) current = settingsModal.find(".settingsSection:visible").first().attr("id");
      settingsModal.find(".settingsNavLink").each(function() {
        $(this).toggleClass("active", $(this).data("target") === current);
      });
    });
    settingsModal.on("shown.bs.modal", function() {
      settingsModal.find(".settingsBody").trigger("scroll");
    });

    updateConditionalRows();

});
</script>