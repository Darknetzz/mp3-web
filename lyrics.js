/* ──────────────────────────────────────────────────────────────────────────
 * Lyrics panel: lyrics for the current song from LRCLIB (via api.php, which
 * caches every answer). Synced lyrics highlight and follow the current line;
 * click a line to jump there. Plain lyrics are shown as text.
 *
 * Shares the queue panel's spot: opening one closes the other.
 * Uses globals from js.php: songName, player, seekBy, sessionBlocked, lucideIcon.
 * ────────────────────────────────────────────────────────────────────────── */
(function () {
  "use strict";

  var OPEN_KEY = "lyricsPanelOpen";
  var panel = $("#lyricsPanel");
  if (!panel.length) return;
  var body = panel.find(".lyricsBody");
  var source = panel.find(".lyricsSource");

  var loadedFor = null;   // song whose lyrics are shown (or loading)
  var request = null;
  var lines = null;       // synced: [[seconds, text], …]
  var lineEls = null;
  var activeLine = -1;
  var timer = null;
  var userScrollUntil = 0; // don't auto-scroll right after the user scrolled
  var cache = {};          // song → API data, for this page load

  function esc(str) {
    return $("<div>").text(str == null ? "" : String(str)).html();
  }
  function isOpen() {
    return !panel.prop("hidden");
  }

  /* ──────────────────────────────── Rendering ─────────────────────────────── */
  function showMessage(icon, title, detail) {
    lines = lineEls = null;
    activeLine = -1;
    body.html(
      '<div class="lyricsEmpty text-secondary">' +
        (icon ? '<div class="lyricsEmptyIcon">' + lucideIcon(icon) + '</div>' : "") +
        '<div class="lyricsEmptyTitle">' + esc(title) + '</div>' +
        (detail ? '<div class="small">' + detail + '</div>' : "") +
      '</div>'
    );
  }

  function render(song, data) {
    if (!data.found) {
      source.text("");
      var tried = (data.tried || []).slice(0, 3).map(esc).join("<br>");
      showMessage("mic-vocal", "No lyrics found",
        "LRCLIB has nothing for this song." + (tried ? '<div class="lyricsTried">Looked for:<br>' + tried + '</div>' : ""));
      return;
    }
    var m = data.match || {};
    source.html(esc([m.artist, m.title].filter(Boolean).join(" – ")) +
      ' · <a href="https://lrclib.net" target="_blank" rel="noopener">LRCLIB</a>');

    if (data.instrumental && !data.synced && !data.plain) {
      showMessage("music", "Instrumental", "This song has no lyrics.");
      return;
    }
    if (data.synced && data.synced.length) {
      lines = data.synced;
      var html = '<ol class="lyricsLines lyricsSynced">';
      lines.forEach(function (l, i) {
        html += '<li class="lyLine" data-i="' + i + '" tabindex="-1">' + (l[1] ? esc(l[1]) : '<span class="lyGap">♪</span>') + '</li>';
      });
      body.html(html + '</ol>');
      lineEls = body.find(".lyLine");
      activeLine = -1;
      body.scrollTop(0);
      tick(true);
      return;
    }
    lines = lineEls = null;
    activeLine = -1;
    body.html('<div class="lyricsLines lyricsPlain">' + esc(data.plain) + '</div>').scrollTop(0);
  }

  function load(song) {
    loadedFor = song;
    if (request) { request.abort(); request = null; }
    if (!song) {
      source.text("");
      showMessage("", "Nothing is playing.");
      return;
    }
    if (cache[song]) {
      render(song, cache[song]);
      return;
    }
    source.text("");
    lines = lineEls = null;
    body.html('<div class="lyricsEmpty text-secondary"><div class="spinner-border spinner-border-sm" role="status"></div><div>Looking for lyrics…</div></div>');
    request = $.ajax({ url: "api.php", data: { action: "lyrics", song: song }, dataType: "json", timeout: 60000 })
      .done(function (res) {
        if (song !== loadedFor) return;
        if (res && res.status === "success" && res.data) {
          cache[song] = res.data;
          render(song, res.data);
        } else {
          showMessage("", "Couldn't load lyrics", esc(res && res.response ? res.response : "Unknown error."));
        }
      })
      .fail(function (xhr, status) {
        if (status === "abort" || song !== loadedFor) return;
        showMessage("", "Couldn't load lyrics", "Check the connection and try again.");
        loadedFor = null; // retry on the next tick
      })
      .always(function () { request = null; });
  }

  /* ─────────────────────────────── Sync to time ───────────────────────────── */
  function lineAt(t) {
    if (!lines) return -1;
    var lo = 0, hi = lines.length - 1, ans = -1;
    while (lo <= hi) {
      var mid = (lo + hi) >> 1;
      if (lines[mid][0] <= t + 0.15) { ans = mid; lo = mid + 1; } else { hi = mid - 1; }
    }
    return ans;
  }

  function tick(force) {
    if (!isOpen()) return;
    var song = window.songName || null;
    if (song !== loadedFor) {
      load(song);
      return;
    }
    if (!lines || !lineEls) return;
    var i = lineAt(player.getTime() || 0);
    if (i === activeLine && !force) return;
    if (activeLine >= 0) lineEls.eq(activeLine).removeClass("active");
    activeLine = i;
    lineEls.removeClass("past");
    if (i >= 0) {
      lineEls.slice(0, i).addClass("past");
      var el = lineEls.eq(i).addClass("active")[0];
      if (Date.now() > userScrollUntil) {
        var top = el.offsetTop - body[0].clientHeight / 2 + el.offsetHeight / 2;
        body.stop(true).animate({ scrollTop: Math.max(0, top) }, force ? 0 : 250);
      }
    }
  }

  // Pause auto-scrolling for a moment while the user scrolls the lyrics.
  body.on("wheel touchmove", function () { userScrollUntil = Date.now() + 4000; });

  // Click (or Enter on) a synced line to jump there.
  body.on("click", ".lyLine", function () {
    if (!lines || !window.songName) return;
    var t = lines[+$(this).data("i")][0];
    if (sessionBlocked()) return;
    seekBy(t - (player.getTime() || 0));
    userScrollUntil = 0;
    tick(true);
  });

  /* ──────────────────────────────── Open/close ────────────────────────────── */
  function setOpen(on, opts) {
    if (on && window.queuePanel && queuePanel.close) queuePanel.close();
    panel.prop("hidden", !on);
    $("body").toggleClass("lyrics-open", on);
    $(".lyricsToggleBtn").toggleClass("active", on).attr("aria-expanded", on ? "true" : "false");
    if (!opts || opts.save !== false) {
      try { localStorage.setItem(OPEN_KEY, on ? "1" : "0"); } catch (e) {}
    }
    clearInterval(timer);
    timer = null;
    if (on) {
      timer = setInterval(tick, 250);
      loadedFor = undefined; // reload for the current song
      tick(true);
    }
  }
  function toggle() {
    var on = !isOpen();
    setOpen(on);
    if (on) panel.find(".lyricsPanelClose").trigger("focus");
  }

  $(".lyricsToggleBtn").on("click", toggle);
  panel.find(".lyricsPanelClose").on("click", function () {
    setOpen(false);
    $(".lyricsToggleBtn:visible").first().trigger("focus");
  });
  panel.on("keydown", function (e) {
    if (e.key === "Escape" && !$(".popMenu:visible").length) {
      setOpen(false);
      $(".lyricsToggleBtn:visible").first().trigger("focus");
    }
  });

  window.lyricsPanel = {
    toggle: toggle,
    open: function () { setOpen(true); },
    close: function () { if (isOpen()) setOpen(false); },
  };
  $(function () {
    var saved = false;
    try { saved = localStorage.getItem(OPEN_KEY) === "1"; } catch (e) {}
    if (saved) setOpen(true, { save: false });
  });
})();
