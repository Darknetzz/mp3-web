/* ────────────────────────────────────────────────────────────────────────── */
/*                                    Cast                                    */
/* ────────────────────────────────────────────────────────────────────────── */
// Google Cast (Chrome/Edge) with an AirPlay/Remote Playback fallback (Safari).
// While a Cast session is active, `player.remote` (js.php) routes all playback
// controls to the speaker.
(function () {
  "use strict";

  var remotePlayer     = null;
  var controller       = null;
  var lastRemoteTime   = 0;
  var finishedHandled  = false;
  var warnedLocalhost  = false;
  var initialized      = false;
  var watchedMedia     = null;
  var lastDuration     = 0;     // last known duration; remotePlayer resets it at idle
  var wasPlaying       = false; // speaker was playing/buffering before the last state change
  var loadingMedia     = false; // a load is in flight (the old track goes IDLE/INTERRUPTED)
  var userStopped      = false; // Stop was pressed (the track goes IDLE/CANCELLED)

  // TEMPORARY: Cast debugging. Logs to the console and to debug.log on the server
  // (api.php action "clientLog") so end-of-track behaviour on real speakers can be read.
  function castLog(msg) {
    console.log("Cast: " + msg);
    try {
      $.post(apiURL, { action: "clientLog", csrf_token: csrfToken, msg: "cast " + msg });
    } catch (e) {}
  }

  function flags() {
    return "t=" + Math.round(lastRemoteTime) + "/" + Math.round(lastDuration) +
      " wasPlaying=" + wasPlaying + " loading=" + loadingMedia + " stopped=" + userStopped +
      " handled=" + finishedHandled;
  }

  // Advance to the next song exactly once per track, whichever signal comes first.
  function trackFinished(source) {
    if (finishedHandled || !player.remote || loadingMedia || userStopped) {
      castLog("finish signal ignored (" + source + ") " + flags() + " remote=" + !!player.remote);
      return;
    }
    finishedHandled = true;
    castLog("track finished (" + source + "), advancing. autoplayNext=" + autoplayNext + " loop=" + loop +
      " shuffle=" + shuffle + " listeningSession=" + !!(window.sessionSync && sessionSync.active()));
    onSongEnded();
  }

  // Signal 1: the media session reports idleReason FINISHED. getMediaSession()
  // hides media once it has an idleReason, so keep our own reference to it.
  function watchMedia(media) {
    if (!media || media === watchedMedia) return;
    watchedMedia = media;
    media.addUpdateListener(function (isAlive) {
      castLog("media update: isAlive=" + isAlive + " state=" + media.playerState + " idleReason=" + media.idleReason +
        " watched=" + (media === watchedMedia) + " " + flags());
      if (media !== watchedMedia || !player.remote) return;
      if (media.playerState === chrome.cast.media.PlayerState.IDLE) {
        watchedMedia = null;
        if (media.idleReason === chrome.cast.media.IdleReason.FINISHED) {
          trackFinished("idleReason FINISHED");
        }
      }
    });
  }

  // Signal 2 (fallback): the speaker went idle right at the end of the track.
  function nearEnd() {
    return lastDuration > 0 && lastRemoteTime >= lastDuration - 5;
  }

  var MIME_TYPES = {
    mp3:  "audio/mpeg",
    ogg:  "audio/ogg",
    oga:  "audio/ogg",
    wav:  "audio/wav",
    m4a:  "audio/mp4",
    aac:  "audio/aac",
    flac: "audio/flac",
  };

  function mimeFor(name) {
    var ext = String(name).split(".").pop().toLowerCase();
    return MIME_TYPES[ext] || "audio/mpeg";
  }

  function castBtn()   { return $(".castBtn"); }
  function castGroup() { return $(".castGroup"); }

  function showButton(enabled, title) {
    castGroup().show();
    castBtn().prop("disabled", !enabled).attr("title", title);
  }

  function currentSession() {
    return cast.framework.CastContext.getInstance().getCurrentSession();
  }

  /* ─────────────────────────── Remote player impl ─────────────────────────── */
  var loadToken = 0;
  var remote = {
    load: function (url, name, startTime, autoplay) {
      var session = currentSession();
      if (!session) return;
      if (!warnedLocalhost && /^(localhost|127\.|\[?::1\]?$)/.test(window.location.hostname)) {
        warnedLocalhost = true;
        showToast("The speaker can't reach <code>localhost</code>. Open this page via your LAN address to cast.", "warning");
      }
      // Look up the cover first (cached after the first time) so the speaker
      // gets real artwork, or the app logo when the song has none.
      var token = ++loadToken;
      finishedHandled = false;
      userStopped     = false;
      loadingMedia    = true;
      lastRemoteTime  = startTime || 0;
      lastDuration    = 0;
      resolveCover(name, function (art) {
        if (token !== loadToken) return; // a newer song was loaded meanwhile
        remote.loadWithArt(url, name, startTime, autoplay, art || fallbackArtwork);
      });
    },
    loadWithArt: function (url, name, startTime, autoplay, art) {
      var session = currentSession();
      if (!session) { loadingMedia = false; return; }
      var info        = new chrome.cast.media.MediaInfo(url, mimeFor(name));
      info.streamType = chrome.cast.media.StreamType.BUFFERED;
      var metadata       = new chrome.cast.media.MusicTrackMediaMetadata();
      metadata.title     = name;
      metadata.albumName = siteTitle;
      metadata.images    = [new chrome.cast.Image(art)];  // absolute URL
      info.metadata      = metadata;

      var request         = new chrome.cast.media.LoadRequest(info);
      request.currentTime = startTime || 0;
      request.autoplay    = autoplay !== false;

      finishedHandled = false;
      lastRemoteTime  = startTime || 0;
      session.loadMedia(request).then(
        function () {
          loadingMedia = false;
          var media = session.getMediaSession();
          castLog("loaded " + name + " mediaSession=" + !!media);
          watchMedia(media);
        },
        function (err) {
          loadingMedia = false;
          console.error("Cast: load failed", err);
          showToast("Could not cast this song: " + (err && err.code ? err.code : err), "danger");
        }
      );
    },
    play: function () {
      if (remotePlayer.isPaused) controller.playOrPause();
    },
    pause: function () {
      if (!remotePlayer.isPaused) controller.playOrPause();
    },
    stop: function () {
      userStopped = true;
      controller.stop();
    },
    seek: function (t) {
      remotePlayer.currentTime = t;
      controller.seek();
    },
    setVolume: function (v) {
      remotePlayer.volumeLevel = v;
      controller.setVolumeLevel();
    },
    getTime: function () {
      return remotePlayer.currentTime;
    },
    getDuration: function () {
      return remotePlayer.duration;
    },
  };

  /* ─────────────────────────── Session lifecycle ──────────────────────────── */
  function onConnected(session, resumed) {
    castLog((resumed ? "session resumed" : "session started") + " song=" + songName);
    if (player.remote) return;
    var audio      = player.audio();
    var wasPlaying = !audio.paused;
    var localTime  = audio.currentTime || 0;

    player.remote = remote;   // set first so the local pause event is ignored
    audio.pause();
    if (window.waveform) window.waveform.setRemote(true);

    var device = session.getCastDevice();
    $(".castStatus").text("Casting to " + (device ? device.friendlyName : "speaker")).show();
    castBtn().removeClass("btn-outline-primary").addClass("btn-primary");

    // Reloaded the page while already casting: adopt what the speaker is playing.
    var media = session.getMediaSession();
    if (resumed && media && media.media) {
      watchMedia(media);
      var title = media.media.metadata && media.media.metadata.title;
      if (title) {
        var index = findIndexByFilename(title);
        if (index !== null) {
          songName = title;
          window.currentIndex = index;
          updateTitle(title);
          $("tr.songrow").removeClass(activeClass).children().removeClass(activeClass);
          $("tr.songrow[data-uniqueid='" + index + "']").addClass(activeClass).children().addClass(activeClass);
        }
      }
      setPlayingUI(!remotePlayer.isPaused);
      $(".ctrlBtn").prop("disabled", false);
      return;
    }

    setPlayingUI(false);
    if (songName) {
      player.load(songName, { startTime: localTime, autoplay: wasPlaying });
    }
  }

  function onDisconnected() {
    if (!player.remote) return;
    var resumeAt = lastRemoteTime;
    player.remote = null;
    watchedMedia  = null;
    loadingMedia  = false;
    userStopped   = false;
    wasPlaying    = false;
    lastDuration  = 0;
    if (window.waveform) window.waveform.setRemote(false);
    $(".castStatus").hide().text("");
    castBtn().removeClass("btn-primary").addClass("btn-outline-primary");
    setPlayingUI(false);

    // Hand the volume slider back to the local player
    var localVolume = player.audio().volume;
    $("#volumeSlider").val(localVolume);
    updateVolumeIcon(localVolume);

    if (songName) {
      player.load(songName, { startTime: resumeAt, autoplay: false });
    }
  }

  /* ─────────────────────────── Remote player events ───────────────────────── */
  function bindRemoteEvents() {
    var E = cast.framework.RemotePlayerEventType;

    controller.addEventListener(E.PLAYER_STATE_CHANGED, function () {
      if (!player.remote) return;
      var state = remotePlayer.playerState;
      var S = chrome.cast.media.PlayerState;
      castLog("player state " + state + " mediaLoaded=" + remotePlayer.isMediaLoaded +
        " rpTime=" + Math.round(remotePlayer.currentTime) + "/" + Math.round(remotePlayer.duration) + " " + flags());
      if (state === S.PLAYING) {
        wasPlaying = true;
        setPlayingUI(true);
      } else if (state === S.BUFFERING) {
        wasPlaying = true;
      } else if (state === S.PAUSED) {
        wasPlaying = false;
        setPlayingUI(false);
      } else if (state === S.IDLE || state === null) {
        setPlayingUI(false);
        if (wasPlaying && nearEnd()) {
          trackFinished("went idle at " + Math.round(lastRemoteTime) + "/" + Math.round(lastDuration) + "s");
        }
        wasPlaying = false;
      }
    });

    controller.addEventListener(E.CURRENT_TIME_CHANGED, function () {
      if (!player.remote) return;
      if (remotePlayer.currentTime > 0) lastRemoteTime = remotePlayer.currentTime;
      updateTime();
    });

    controller.addEventListener(E.DURATION_CHANGED, function () {
      if (!player.remote || !remotePlayer.duration) return;
      lastDuration = remotePlayer.duration;
      duration = remotePlayer.duration;
      $(".audioDuration").text(formatTime(duration));
      $(".ctrlBtn").prop("disabled", false);
      updateTime();
    });

    controller.addEventListener(E.IS_MEDIA_LOADED_CHANGED, function () {
      if (!player.remote) return;
      castLog("media loaded changed: " + remotePlayer.isMediaLoaded + " " + flags());
    });

    controller.addEventListener(E.VOLUME_LEVEL_CHANGED, function () {
      if (!player.remote) return;
      $("#volumeSlider").val(remotePlayer.volumeLevel);
      updateVolumeIcon(remotePlayer.volumeLevel);
    });
  }

  /* ─────────────────────────────── Init: Cast ─────────────────────────────── */
  function initCast() {
    if (initialized) return;
    initialized = true;
    var context = cast.framework.CastContext.getInstance();
    context.setOptions({
      receiverApplicationId: chrome.cast.media.DEFAULT_MEDIA_RECEIVER_APP_ID,
      autoJoinPolicy:        chrome.cast.AutoJoinPolicy.ORIGIN_SCOPED,
    });

    remotePlayer = new cast.framework.RemotePlayer();
    controller   = new cast.framework.RemotePlayerController(remotePlayer);
    bindRemoteEvents();

    function updateButton(state) {
      if (state === cast.framework.CastState.NO_DEVICES_AVAILABLE) {
        showButton(false, "No Cast devices found on your network");
      } else if (state === cast.framework.CastState.CONNECTED) {
        showButton(true, "Casting (click to stop or switch device)");
      } else {
        showButton(true, "Cast to a speaker");
      }
    }
    updateButton(context.getCastState());

    context.addEventListener(cast.framework.CastContextEventType.CAST_STATE_CHANGED, function (e) {
      updateButton(e.castState);
    });

    context.addEventListener(cast.framework.CastContextEventType.SESSION_STATE_CHANGED, function (e) {
      var S = cast.framework.SessionState;
      if (e.sessionState === S.SESSION_STARTED) {
        onConnected(e.session, false);
      } else if (e.sessionState === S.SESSION_RESUMED) {
        onConnected(e.session, true);
      } else if (e.sessionState === S.SESSION_ENDED) {
        onDisconnected();
      }
    });

    castBtn().off("click").on("click", function () {
      context.requestSession().catch(function (err) {
        if (err !== "cancel" && err !== chrome.cast.ErrorCode.CANCEL) {
          console.error("Cast: requestSession failed", err);
        }
      });
    });
  }

  /* ──────────────────────── Init: Remote Playback API ─────────────────────── */
  // Safari (AirPlay) and other browsers that implement HTMLMediaElement.remote.
  function initFallback() {
    if (initialized) return;
    initialized = true;
    var audio = player.audio();
    if (audio && audio.remote && typeof audio.remote.prompt === "function") {
      castBtn().off("click").on("click", function () {
        audio.remote.prompt().catch(function (err) {
          if (err && err.name !== "AbortError") {
            showToast("Casting isn't available: " + err.message, "warning");
          }
        });
      });
      var insecureChrome = window.chrome && !window.isSecureContext;
      var unavailableTitle = insecureChrome
        ? "Google Cast needs HTTPS (or localhost) in Chrome"
        : "No cast devices found";
      audio.remote.watchAvailability(function (available) {
        showButton(available, available ? "Cast / AirPlay" : unavailableTitle);
      }).catch(function () {
        // Availability can't be monitored; show the button and let prompt() decide.
        showButton(true, "Cast / AirPlay");
      });
      return;
    }
    if (window.chrome && !window.isSecureContext) {
      showButton(false, "Casting needs HTTPS (or localhost) in Chrome");
    }
  }

  window.__onGCastApiAvailable = function (isAvailable) {
    $(function () {
      if (isAvailable && window.cast && cast.framework) {
        initCast();
      } else {
        initFallback();
      }
    });
  };

  // If the Cast SDK fails to load (blocked or offline), still set up the fallback.
  window.addEventListener("load", function () {
    setTimeout(function () {
      if (!window.cast || !window.cast.framework) {
        initFallback();
      }
    }, 3000);
  });
})();
