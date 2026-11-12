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
  var resumeTimer      = null;  // pending "keep playing here" after casting stopped

  function castLog(msg) {
    console.log("Cast: " + msg);
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
    media.addUpdateListener(function () {
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
        remote.loadWithArt(url, name, startTime, autoplay, art || fallbackArtwork, token, 0);
      });
    },
    loadWithArt: function (url, name, startTime, autoplay, art, token, attempt) {
      var session = currentSession();
      if (!session) { loadingMedia = false; return; }
      var info        = new chrome.cast.media.MediaInfo(url, mimeFor(name));
      info.streamType = chrome.cast.media.StreamType.BUFFERED;
      var metadata       = new chrome.cast.media.MusicTrackMediaMetadata();
      var tags           = songInfo(name);
      metadata.title     = tags.title;
      metadata.artist    = tags.artist;
      metadata.albumName = tags.album || siteTitle;
      metadata.images    = [new chrome.cast.Image(art)];  // absolute URL
      info.metadata      = metadata;
      info.customData    = { file: name };  // the title may be a tag, not the file name

      var request         = new chrome.cast.media.LoadRequest(info);
      request.currentTime = startTime || 0;
      request.autoplay    = autoplay !== false;

      finishedHandled = false;
      lastRemoteTime  = startTime || 0;
      session.loadMedia(request).then(
        function () {
          loadingMedia = false;
          watchMedia(session.getMediaSession());
        },
        function (err) {
          if (token !== loadToken || !player.remote) return; // superseded or disconnected
          // A session that has only just started sometimes refuses the first
          // load; one retry fixes it.
          if (!attempt) {
            castLog("load failed (" + (err && err.code ? err.code : err) + "), retrying");
            setTimeout(function () {
              if (token !== loadToken || !player.remote) return;
              remote.loadWithArt(url, name, startTime, autoplay, art, token, 1);
            }, 1000);
            return;
          }
          loadingMedia = false;
          console.error("Cast: load failed", err);
          showToast("Could not cast this song: " + (err && err.code ? err.code : err), "danger");
        }
      );
    },
    play: function () {
      // Nothing on the speaker (a failed or interrupted load, or it went idle):
      // load the current song again instead of doing nothing.
      if (loadingMedia) return;
      var media = currentMedia();
      if (!media) { reloadCurrent("play with nothing loaded"); return; }
      if (media.playerState === chrome.cast.media.PlayerState.PAUSED) {
        media.play(null, function () {}, function (err) {
          reloadCurrent("play refused (" + (err && err.code ? err.code : err) + ")");
        });
      }
    },
    pause: function () {
      var media = currentMedia();
      if (!media) { setPlayingUI(false); return; }
      media.pause(null, function () {}, function (err) {
        castLog("pause refused (" + (err && err.code ? err.code : err) + ")");
      });
    },
    stop: function () {
      userStopped = true;
      controller.stop();
    },
    seek: function (t) {
      if (nothingLoaded()) { lastRemoteTime = t; return; }
      remotePlayer.currentTime = t;
      controller.seek();
    },
    setVolume: function (v) {
      remotePlayer.volumeLevel = v;
      controller.setVolumeLevel();
    },
    // While loading (or with nothing loaded) remotePlayer still describes the
    // previous track, so report the position we asked for instead.
    getTime: function () {
      return nothingLoaded() ? lastRemoteTime : remotePlayer.currentTime;
    },
    getDuration: function () {
      return nothingLoaded() ? lastDuration : remotePlayer.duration;
    },
  };

  // The speaker's current media, or null when it has none it can play
  // (never loaded, load failed, finished, stopped or taken over by another app).
  function currentMedia() {
    var session = currentSession();
    var media   = session && session.getMediaSession();
    if (!media || media.playerState === chrome.cast.media.PlayerState.IDLE) return null;
    return media;
  }

  // True while a load is in flight or the speaker has no usable media.
  function nothingLoaded() {
    return loadingMedia || !currentMedia();
  }

  function reloadCurrent(why) {
    if (!songName || !player.remote) return;
    var at = nearEnd() ? 0 : lastRemoteTime;
    castLog(why + ", reloading " + songName + " at " + Math.round(at) + "s");
    remote.load(songUrl(songName), songName, at, true);
  }

  /* ─────────────────────────── Session lifecycle ──────────────────────────── */
  function cancelLocalResume() {
    if (resumeTimer) clearTimeout(resumeTimer);
    resumeTimer = null;
  }

  function castConnecting() {
    var s = cast.framework.CastContext.getInstance().getCastState();
    return s === cast.framework.CastState.CONNECTING || s === cast.framework.CastState.CONNECTED;
  }

  function onConnected(session, resumed) {
    if (player.remote) return;
    var audio = player.audio();
    // Read before `player.remote` is set. A local resume still pending (we're
    // switching from one speaker to another) counts as playing.
    var state = player.handoff();
    if (resumeTimer) state.playing = true;
    cancelLocalResume();

    player.remote = remote;   // set first so the local pause event is ignored
    audio.pause();
    if (window.waveform) window.waveform.setRemote(true);

    var device = session.getCastDevice();
    $(".castStatus").text("Casting to " + (device ? device.friendlyName : "speaker")).show();
    castBtn().removeClass("btn-ghost-secondary").addClass("btn-primary");

    // Reloaded the page while already casting: adopt what the speaker is playing.
    var media = session.getMediaSession();
    var MS = chrome.cast.media.PlayerState;
    if (resumed && media && media.media && media.playerState !== MS.IDLE) {
      watchMedia(media);
      lastRemoteTime = media.getEstimatedTime ? media.getEstimatedTime() : (media.currentTime || 0);
      lastDuration   = media.media.duration || 0;
      var title = (media.media.customData && media.media.customData.file) ||
                  (media.media.metadata && media.media.metadata.title);
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
      setPlayingUI(media.playerState === MS.PLAYING || media.playerState === MS.BUFFERING);
      $(".ctrlBtn").prop("disabled", false);
      return;
    }

    setPlayingUI(false);
    if (songName) {
      castLog("handing off " + songName + " at " + Math.round(state.time) + "s, playing=" + state.playing);
      player.load(songName, { startTime: state.time, autoplay: state.playing });
    }
  }

  function onDisconnected() {
    if (!player.remote) return;
    var resumeAt      = lastRemoteTime;
    var speakerPlayed = !!window.playing;
    loadToken++;              // drop a load still waiting for its cover / retry
    player.remote = null;
    watchedMedia  = null;
    loadingMedia  = false;
    userStopped   = false;
    wasPlaying    = false;
    lastDuration  = 0;
    if (window.waveform) window.waveform.setRemote(false);
    $(".castStatus").hide().text("");
    castBtn().removeClass("btn-primary").addClass("btn-ghost-secondary");
    setPlayingUI(false);

    // Hand the volume slider back to the local player
    var localVolume = player.localVolume();
    $("#volumeSlider").val(localVolume);
    updateVolumeIcon(localVolume);

    if (!songName) return;
    player.load(songName, { startTime: resumeAt, autoplay: false });
    if (!speakerPlayed || castEndAction === "pause") return;

    // Keep playing here, but wait a moment: when switching to another speaker
    // the new session starts right after this one ends, and the computer
    // shouldn't blip in between. onConnected() cancels this.
    cancelLocalResume();
    resumeTimer = setTimeout(function () {
      resumeTimer = null;
      if (player.remote || castConnecting()) return;
      castLog("casting stopped, continuing here at " + Math.round(resumeAt) + "s");
      player.play();
    }, 1500);
  }

  /* ─────────────────────────── Remote player events ───────────────────────── */
  function bindRemoteEvents() {
    var E = cast.framework.RemotePlayerEventType;

    controller.addEventListener(E.PLAYER_STATE_CHANGED, function () {
      if (!player.remote) return;
      var state = remotePlayer.playerState;
      var S = chrome.cast.media.PlayerState;
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

  /* ───────────────────────────── Media keys ──────────────────────────────── */
  // Chrome sends media keys (and shows its media controls) only to a tab that is
  // playing audio. While casting, the tab is silent, so the keys went nowhere.
  // A looping silent clip keeps the tab's media session alive; its play/pause
  // state mirrors the speaker, so the play/pause key toggles the right way.
  // Chrome ignores clips shorter than about 5 s, so the clip is 10 s long.
  var keepAlive = null;

  function silentWavUrl(seconds) {
    var rate = 8000, n = rate * seconds;
    var buf = new ArrayBuffer(44 + n), v = new DataView(buf);
    function str(o, s) { for (var i = 0; i < s.length; i++) v.setUint8(o + i, s.charCodeAt(i)); }
    str(0, "RIFF"); v.setUint32(4, 36 + n, true); str(8, "WAVE");
    str(12, "fmt "); v.setUint32(16, 16, true); v.setUint16(20, 1, true); v.setUint16(22, 1, true);
    v.setUint32(24, rate, true); v.setUint32(28, rate, true); v.setUint16(32, 1, true); v.setUint16(34, 8, true);
    str(36, "data"); v.setUint32(40, n, true);
    new Uint8Array(buf, 44).fill(128); // 8-bit unsigned silence
    return URL.createObjectURL(new Blob([buf], { type: "audio/wav" }));
  }

  window.castKeepAlive = {
    update: function (isPlaying) {
      var casting = window.player && player.isRemote && player.isRemote();
      if (!casting) {
        if (keepAlive) keepAlive.pause();
        return;
      }
      if (!keepAlive) {
        keepAlive = new Audio(silentWavUrl(10));
        keepAlive.loop = true;
      }
      if (isPlaying) {
        // Without a prior user gesture (e.g. a resumed session on page load) this
        // is refused; the gesture listener below retries.
        var p = keepAlive.play();
        if (p && p.catch) p.catch(function () {});
      } else {
        keepAlive.pause();
      }
    },
  };
  ["pointerdown", "keydown"].forEach(function (type) {
    document.addEventListener(type, function () {
      if (keepAlive && keepAlive.paused && window.playing && player.isRemote()) {
        window.castKeepAlive.update(true);
      }
    }, true);
  });

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
