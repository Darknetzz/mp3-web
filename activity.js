/* ──────────────────────────────────────────────────────────────────────────
 * Activity: tell the server what this player is playing (when the account
 * shares it), and show what other users are playing in the header's
 * Activity panel, with Play and Listen along.
 * Uses globals from js.php: apiURL, csrfToken, songName, playing, player,
 * findIndexByFilename, playSong, showToast, coverUrl, currentUser.
 * ────────────────────────────────────────────────────────────────────────── */
(function () {
  "use strict";
  if (!window.currentUser || !currentUser.signed_in) return;

  var REPORT_EVERY = 30000; // while playing, refresh our entry this often
  var CHECK_EVERY  = 4000;  // look for song / play-pause changes
  var COUNT_EVERY  = 60000; // badge refresh while the panel is closed
  var LIST_EVERY   = 15000; // list refresh while it's open

  /* ───────────────────────────── Reporting ───────────────────────────────── */
  var sharing = !!currentUser.share_playing;
  var last = { song: null, playing: null, at: 0 };

  function report(force) {
    if (!sharing) return;
    var song = window.songName || null;
    var isPlaying = !!window.playing && !!song;
    var now = Date.now();
    var changed = song !== last.song || isPlaying !== last.playing;
    if (!force && !changed && !(isPlaying && now - last.at > REPORT_EVERY)) return;
    last = { song: song, playing: isPlaying, at: now };
    var position = 0;
    try { position = song && window.player ? player.getTime() || 0 : 0; } catch (e) {}
    $.ajax({
      url: apiURL, type: "POST", dataType: "json",
      data: { action: "nowPlaying", csrf_token: csrfToken, song: song || "", position: position, playing: isPlaying ? 1 : 0 }
    });
  }
  setInterval(function () { report(false); }, CHECK_EVERY);
  // Paused or stopped when the tab closes: say so, so others don't see a stale "playing".
  window.addEventListener("pagehide", function () {
    if (!sharing || !last.song || !navigator.sendBeacon) return;
    var fd = new FormData();
    fd.append("action", "nowPlaying");
    fd.append("csrf_token", csrfToken);
    fd.append("song", "");
    navigator.sendBeacon(apiURL, fd);
  });

  /* ─────────────────────────────── Panel ─────────────────────────────────── */
  var menu  = $(".activityMenu");
  var panel = menu.find(".activityPanel");
  var badge = menu.find(".activityBadge");
  var open  = false;
  var items = [];
  var listTimer = null;

  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }
  function clock(secs) {
    secs = Math.max(0, Math.floor(secs));
    return Math.floor(secs / 60) + ":" + String(secs % 60).padStart(2, "0");
  }

  function setBadge() {
    var playingNow = items.filter(function (a) { return a.playing; }).length;
    badge.text(playingNow).toggle(playingNow > 0);
  }

  function render() {
    var list = panel.find(".activityList");
    if (!items.length) {
      list.html('<div class="activityEmpty text-secondary">Nobody else is playing anything right now.</div>');
    } else {
      list.html(items.map(function (a, i) {
        var cover = window.coverUrl ? coverUrl(a.song, "sm") : "";
        var known = window.findIndexByFilename && findIndexByFilename(a.song) !== null;
        return '<div class="activityItem" data-i="' + i + '">' +
          '<span class="coverThumb activityCover">' + (cover ? '<img class="coverImg" src="' + esc(cover) + '" alt="" onerror="this.hidden=true">' : '') + '</span>' +
          '<div class="activityText">' +
            '<div class="activityUser">' + esc(a.user) + (a.playing ? ' <span class="activityLive" title="Playing">' + lucideIcon("radio") + '</span>' : ' <span class="text-secondary small">paused</span>') + '</div>' +
            '<div class="activitySong text-truncate" title="' + esc(a.title) + '">' + esc(a.title) + '</div>' +
            '<div class="activityArtist text-secondary small text-truncate">' + esc(a.artist || "") + (a.artist ? " · " : "") + clock(a.position) + (a.session ? " · in a session" : "") + '</div>' +
          '</div>' +
          '<div class="activityActions">' +
            '<button type="button" class="btn btn-sm btn-icon btn-ghost-success activityPlay" title="Play this song"' + (known ? '' : ' disabled') + ' aria-label="Play ' + esc(a.title) + '">' + lucideIcon("play", "filled") + '</button>' +
            '<button type="button" class="btn btn-sm btn-pill btn-outline-success activityJoin" title="' + (a.session ? "Join their listen-together session" : "Play from where they are") + '"' + (known || a.session ? '' : ' disabled') + '>Listen along</button>' +
          '</div>' +
        '</div>';
      }).join(""));
    }
    panel.find(".activityFoot").html(sharing
      ? "Others can see what you play. <a href=\"#settings-account\" class=\"openSettingsSection\" data-section=\"settings-account\">Change</a>"
      : "What you play is private. <a href=\"#settings-account\" class=\"openSettingsSection\" data-section=\"settings-account\">Share it</a>");
  }

  function load() {
    return $.getJSON(apiURL, { action: "activity" }).then(function (res) {
      if (!res || res.status !== "success") return;
      items = res.data.activity || [];
      setBadge();
      if (open) render();
    });
  }

  menu.on("show.bs.dropdown", function () {
    open = true;
    render();
    load();
    clearInterval(listTimer);
    listTimer = setInterval(load, LIST_EVERY);
  }).on("hidden.bs.dropdown", function () {
    open = false;
    clearInterval(listTimer);
  });

  function close() {
    var btn = menu.find(".activityBtn")[0];
    var bs = window.bootstrap || window.tabler;
    if (btn && bs && bs.Dropdown) bs.Dropdown.getOrCreateInstance(btn).hide();
  }

  function playAt(a, at) {
    var id = findIndexByFilename(a.song);
    if (id === null) {
      showToast("That song isn't in your library.", "warning");
      return;
    }
    if (window.sessionSync && sessionSync.blocks && sessionSync.blocks()) {
      showToast("Only the host can change the song in this session.", "warning");
      return;
    }
    playSong(id, { startTime: at || 0 });
  }

  panel.on("click", ".activityPlay", function () {
    var a = items[$(this).closest(".activityItem").data("i")];
    if (!a) return;
    playAt(a, 0);
    close();
  }).on("click", ".activityJoin", function () {
    var a = items[$(this).closest(".activityItem").data("i")];
    if (!a) return;
    close();
    if (a.session && window.sessionSync && sessionSync.join) {
      sessionSync.join(a.session);
      return;
    }
    playAt(a, a.position);
    showToast("Playing along with " + a.user + ".", "success");
  });

  load();
  setInterval(function () { if (!open && !document.hidden) load(); }, COUNT_EVERY);

  window.activityPanel = {
    refresh: load,
    sharingChanged: function (on) {
      sharing = on;
      last = { song: null, playing: null, at: 0 };
      if (on) report(true);
      if (open) render();
    }
  };
})();
