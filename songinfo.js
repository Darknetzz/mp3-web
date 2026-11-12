/* ──────────────────────────────────────────────────────────────────────────
 * Song info panel: a card with everything known about one song — cover, tags,
 * audio details, file info and the playlists it is in.
 *
 * Opened by clicking the song in the playbar (follows the current song) or
 * "Song info" in a row's menu (shows that song until closed). Shares the
 * queue/lyrics panels' spot: opening one closes the others.
 * Uses globals from js.php: songName, playing, toggleSong, findIndexByFilename,
 * getSongByIndex, songInfo, coverUrl, coverChanged, selectSong, playSelected, downloadSong,
 * lucideIcon.
 * Online details (release, artist bio, similar artists, a missing cover) come
 * from api.php?action=songOnline (metadata.php) and load after the local ones.
 * ────────────────────────────────────────────────────────────────────────── */
(function () {
  "use strict";

  var panel = $("#songInfoPanel");
  if (!panel.length) return;
  var body = panel.find(".songInfoBody");
  var heading = panel.find(".songInfoHeading");

  var pinned = null;   // song file shown when opened from a row menu; null = follow the playbar
  var shown = null;    // song file currently rendered
  var cache = {};      // song → API data, for this page load
  var online = {};     // song → online metadata (MusicBrainz & co.), for this page load
  var request = null;
  var onlineRequest = null;

  function esc(str) {
    return $("<div>").text(str == null ? "" : String(str)).html();
  }
  function isOpen() {
    return !panel.prop("hidden");
  }
  function currentSong() {
    return pinned || window.songName || null;
  }

  /* ─────────────────────────────── Formatting ─────────────────────────────── */
  function fmtSize(bytes) {
    if (!bytes) return "";
    if (bytes < 1048576) return Math.round(bytes / 1024) + " KB";
    return (bytes / 1048576).toFixed(1) + " MB";
  }
  function fmtDate(unix) {
    if (!unix) return "";
    return new Date(unix * 1000).toLocaleString(undefined, { dateStyle: "medium", timeStyle: "short" });
  }
  function fmtRate(hz) {
    return hz ? (hz / 1000).toLocaleString(undefined, { maximumFractionDigits: 1 }) + " kHz" : "";
  }

  /* ──────────────────────────────── Rendering ─────────────────────────────── */
  // The hero (cover, title, actions) comes from the library row right away;
  // the details below it from the API.
  function render(song) {
    shown = song;
    if (!song || findIndexByFilename(song) === null) {
      heading.text("Song info");
      body.html(
        '<div class="lyricsEmpty text-secondary">' +
          '<div class="lyricsEmptyIcon">' + lucideIcon("music") + '</div>' +
          '<div class="lyricsEmptyTitle">Nothing playing</div>' +
          '<div class="small">Play a song, or pick “Song info” from a song’s menu.</div>' +
        '</div>'
      );
      return;
    }
    var info = songInfo(song);
    body.html(
      '<div class="siHero">' +
        '<span class="coverThumb siCover">' +
          '<img class="coverImg" alt="" src="' + esc(coverUrl(song, "lg")) + '" onerror="this.hidden=true">' +
        '</span>' +
        '<h4 class="siTitle">' + esc(info.title) + '</h4>' +
        '<div class="siArtist text-secondary"' + (info.artist ? "" : " hidden") + '>' + esc(info.artist) + '</div>' +
        '<div class="siAlbum text-secondary small" hidden></div>' +
        '<div class="siActions"></div>' +
      '</div>' +
      '<div class="siDetails"><div class="siLoading text-secondary small">Loading details…</div></div>' +
      '<div class="siOnline"></div>'
    );
    renderActions();
    load(song);
    loadOnline(song);
  }

  function renderActions() {
    var song = shown;
    if (!song) return;
    var isCurrent = song === window.songName;
    heading.text(isCurrent ? "Now playing" : "Song info");
    var fav = !!(window.playlistsUI && playlistsUI.isFavorite(song));
    var html =
      '<button type="button" class="btn btn-success btn-pill siPlay" data-act="play">' +
        lucideIcon(isCurrent && playing ? "pause" : "play", "filled me-1") +
        (isCurrent && playing ? "Pause" : "Play") +
      '</button>' +
      '<button type="button" class="btn btn-icon btn-pill btn-ghost-secondary siFav' + (fav ? " on" : "") + '" data-act="fav" aria-pressed="' + fav + '"' +
        ' title="' + (fav ? "Remove from Favorites" : "Add to Favorites") + '" aria-label="' + (fav ? "Remove from Favorites" : "Add to Favorites") + '">' +
        lucideIcon("heart", fav ? "filled" : "") + '</button>' +
      '<button type="button" class="btn btn-icon btn-pill btn-ghost-secondary" data-act="playlist" aria-haspopup="menu" title="Add to playlist" aria-label="Add to playlist">' + lucideIcon("list-plus") + '</button>' +
      (isCurrent ? '<button type="button" class="btn btn-icon btn-pill btn-ghost-secondary" data-act="lyrics" title="Lyrics (Y)" aria-label="Lyrics">' + lucideIcon("mic-vocal") + '</button>' : "") +
      '<button type="button" class="btn btn-icon btn-pill btn-ghost-secondary" data-act="download" title="Download" aria-label="Download">' + lucideIcon("download") + '</button>' +
      (window.songEditor ? '<button type="button" class="btn btn-icon btn-pill btn-ghost-secondary" data-act="edit" title="Edit info" aria-label="Edit info">' + lucideIcon("pencil") + '</button>' : "");
    body.find(".siActions").html(html);
  }

  // Tags missing from the file, filled in from MusicBrainz (display only).
  var FILLABLE = ["album", "year", "genre", "track"];
  function withOnline(song, d) {
    var o = online[song];
    var filled = {};
    if (!o || !o.found) return { d: d, filled: filled };
    var out = $.extend({}, d);
    var from = {
      album: o.albumOfficial ? o.album : "",   // not a compilation or bootleg
      track: o.albumOfficial ? o.track : "",
      year:  o.year,
      genre: (o.genres || []).slice(0, 3).join(", "),
    };
    FILLABLE.forEach(function (k) {
      if (!out[k] && from[k]) {
        out[k] = from[k];
        filled[k] = true;
      }
    });
    return { d: out, filled: filled };
  }
  var MB_HINT = ' <span class="siFrom" title="Not in the file’s tags: from MusicBrainz">MusicBrainz</span>';
  var EDIT_HINT = ' <span class="siFrom" title="Edited in this app; the file says otherwise">edited</span>';

  // Untagged file: name the song and artist in the hero as MusicBrainz knows them.
  function fillHero(song, local) {
    var o = online[song];
    if (!o || !o.found) return;
    var artistEl = body.find(".siArtist");
    if (artistEl.prop("hidden") && o.artist.name) artistEl.text(o.artist.name).prop("hidden", false);
    if (!local.title && o.recording.title) body.find(".siTitle").text(o.recording.title);
  }

  function renderDetails(song, local) {
    if (song !== shown) return;
    fillHero(song, local);
    var m = withOnline(song, local);
    var d = m.d;
    var album = [d.album, d.year].filter(Boolean).join(" · ");
    body.find(".siAlbum").text(album).prop("hidden", !album);

    var audio = [];
    if (d.bitrate) audio.push(d.bitrate + " kbps" + (d.vbr ? " VBR" : ""));
    if (d.sampleRate) audio.push(fmtRate(d.sampleRate));
    var edited = {};
    (local.edited || []).forEach(function (f) { edited[f] = true; });
    var rows = [
      ["Genre", d.genre, m.filled.genre, edited.genre],
      ["Track", d.track, m.filled.track, edited.track],
      ["Duration", d.duration && d.duration !== "0:00" ? d.duration : ""],
      ["Audio", audio.join(" · ")],
      ["Channels", d.channels],
      ["Format", d.format],
      ["Size", fmtSize(d.size)],
      ["Added", fmtDate(d.modified)],
      ["File", d.file],
    ];
    var html = '<dl class="siList">';
    rows.forEach(function (r) {
      if (!r[1]) return;
      html += '<dt>' + esc(r[0]) + '</dt><dd' + (r[0] === "File" ? ' class="siFile"' : "") + '>' + esc(r[1]) + (r[2] ? MB_HINT : "") + (r[3] ? EDIT_HINT : "") + '</dd>';
    });
    html += '</dl>';
    if (d.comment) html += '<p class="siComment text-secondary small">' + esc(d.comment) + '</p>';
    if (local.edited && local.edited.length) {
      html += '<p class="siEdited small text-secondary">' + lucideIcon("pencil") + 'Some info here was edited in this app: ' + esc(local.edited.join(", ")) + '.</p>';
    }

    html += '<section class="siPlaylists"><h4 class="qpTitle">In playlists</h4>';
    if (d.playlists && d.playlists.length) {
      html += '<div class="siChips">';
      d.playlists.forEach(function (p) {
        var fav = p.id === "favorites";
        html += '<button type="button" class="siChip" data-playlist="' + esc(p.id) + '">' +
          lucideIcon(fav ? "heart" : "list-video", fav ? "filled" : "") + '<span>' + esc(p.name || (fav ? "Favorites" : "")) + '</span></button>';
      });
      html += '</div>';
    } else {
      html += '<div class="text-secondary small">Not in any playlist yet.</div>';
    }
    html += '</section>';
    body.find(".siDetails").html(html);
  }

  function load(song) {
    if (cache[song]) {
      renderDetails(song, cache[song]);
      return;
    }
    if (request) request.abort();
    request = $.getJSON("api.php", { action: "songInfo", file: song });
    request.done(function (res) {
      if (res && res.status === "success") {
        cache[song] = res.data;
        renderDetails(song, res.data);
      } else if (song === shown) {
        body.find(".siDetails").html('<div class="text-secondary small">' + esc((res && res.response) || "Could not load details.") + '</div>');
      }
    }).fail(function (xhr, status) {
      if (status !== "abort" && song === shown) {
        body.find(".siDetails").html('<div class="text-secondary small">Could not reach the server.</div>');
      }
    });
  }

  /* ───────────────────────────── Online metadata ─────────────────────────── */
  function onlineBox(html) {
    body.find(".siOnline").html(html);
  }
  function fmtReleaseDate(date) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date || "")) return date || "";
    var t = new Date(date + "T12:00:00");
    return isNaN(t) ? date : t.toLocaleDateString(undefined, { dateStyle: "long" });
  }
  function link(url, text) {
    return '<a href="' + esc(url) + '" target="_blank" rel="noopener noreferrer">' + esc(text) +
      lucideIcon("external-link", "siExt") + '</a>';
  }

  function renderOnline(song, o) {
    if (song !== shown) return;
    if (cache[song]) renderDetails(song, cache[song]);   // fill in missing tags
    if (!o.found) {
      onlineBox('<div class="siOnlineNote text-secondary small">' + lucideIcon("globe") + 'Not found on MusicBrainz.</div>');
      return;
    }
    var html = "";

    // Release
    var rows = [
      ["Appears on", o.album ? link(o.albumUrl, o.album) + (o.albumType ? ' <span class="text-secondary">· ' + esc(o.albumType) + '</span>' : "") : ""],
      ["Released", esc(fmtReleaseDate(o.date))],
      ["Label", esc(o.label)],
      ["ISRC", esc(o.isrc)],
    ];
    var list = "";
    rows.forEach(function (r) { if (r[1]) list += '<dt>' + esc(r[0]) + '</dt><dd>' + r[1] + '</dd>'; });
    if (list || (o.genres && o.genres.length)) {
      html += '<section class="siSection"><h4 class="qpTitle">' + lucideIcon("disc-3") + 'Release</h4>';
      if (list) html += '<dl class="siList">' + list + '</dl>';
      if (o.genres && o.genres.length) {
        html += '<div class="siTags">' + o.genres.map(function (g) { return '<span class="siTag">' + esc(g) + '</span>'; }).join("") + '</div>';
      }
      html += '</section>';
    }

    // Artist
    if (o.bio) {
      html += '<section class="siSection siBio"><h4 class="qpTitle">' + lucideIcon("user") + 'About ' + esc(o.bio.title || o.artist.name) + '</h4>' +
        (o.bio.thumb ? '<img class="siBioImg" alt="" loading="lazy" referrerpolicy="no-referrer" src="' + esc(o.bio.thumb) + '" onerror="this.remove()">' : "") +
        '<p class="small">' + esc(o.bio.extract) + '</p>' +
        '<div class="small">' + link(o.bio.url, "Read more on Wikipedia") + '</div>' +
      '</section>';
    }

    // Similar artists
    if (o.similar && o.similar.length) {
      html += '<section class="siSection"><h4 class="qpTitle">' + lucideIcon("users") + 'Similar artists</h4><div class="siChips">';
      o.similar.forEach(function (a) {
        html += a.inLibrary
          ? '<button type="button" class="siChip siChipLib" data-artist="' + esc(a.name) + '" title="In your library: show their songs">' + lucideIcon("music") + '<span>' + esc(a.name) + '</span></button>'
          : '<a class="siChip" href="https://musicbrainz.org/artist/' + esc(a.mbid) + '" target="_blank" rel="noopener noreferrer"><span>' + esc(a.name) + '</span></a>';
      });
      html += '</div></section>';
    }

    html += '<p class="siCredits text-secondary">' + lucideIcon("globe") + 'From ' +
      link(o.recording.url, "MusicBrainz") +
      (o.bio ? ', Wikipedia' : "") + (o.similar && o.similar.length ? ', ListenBrainz' : "") +
      (o.source === "acoustid" ? ' · matched by AcoustID' : "") + '</p>';
    onlineBox(html);

    // A cover was found online for a song without one: show it.
    if (o.onlineCover && !o.coverShown) {
      o.coverShown = true;
      var img = body.find(".siCover .coverImg");
      if (img.prop("hidden") || !img[0].naturalWidth) {
        coverChanged(song);
        img.prop("hidden", false).attr("src", coverUrl(song, "lg"));
      }
    }
  }

  function loadOnline(song) {
    if (online[song]) {
      renderOnline(song, online[song]);
      return;
    }
    if (onlineRequest) onlineRequest.abort();
    onlineBox('<div class="siOnlineNote text-secondary small">' + lucideIcon("globe") + 'Looking up online…</div>');
    onlineRequest = $.ajax({ url: "api.php", data: { action: "songOnline", file: song }, dataType: "json", timeout: 90000 });
    onlineRequest.done(function (res) {
      if (res && res.status === "success") {
        online[song] = res.data;
        renderOnline(song, res.data);
      } else if (song === shown) {
        if (res && res.data && res.data.disabled) onlineBox("");
        else onlineBox('<div class="siOnlineNote text-secondary small">' + lucideIcon("globe") + esc((res && res.response) || "Couldn’t look the song up online.") + '</div>');
      }
    }).fail(function (xhr, status) {
      if (status !== "abort" && song === shown) {
        onlineBox('<div class="siOnlineNote text-secondary small">' + lucideIcon("globe") + 'Couldn’t look the song up online.</div>');
      }
    });
  }

  /* ──────────────────────────────── Actions ──────────────────────────────── */
  body.on("click", "[data-act]", function () {
    var song = shown;
    if (!song) return;
    var id = findIndexByFilename(song);
    switch ($(this).data("act")) {
      case "play":
        if (song === window.songName) toggleSong();
        else if (id !== null) { selectSong(id); playSelected(); }
        break;
      case "fav":
        if (window.playlistsUI) playlistsUI.toggleFavorite(song);
        break;
      case "playlist":
        if (window.playlistsUI) playlistsUI.addToPlaylistMenu(this, song);
        break;
      case "lyrics":
        if (window.lyricsPanel) lyricsPanel.open();
        break;
      case "download":
        if (id !== null) downloadSong(id);
        break;
      case "edit":
        if (window.songEditor) songEditor.open(song);
        break;
    }
  });
  body.on("click", ".siChip[data-playlist]", function () {
    if (window.playlistsUI) playlistsUI.showPlaylist(String($(this).data("playlist")));
  });
  body.on("click", ".siChipLib", function () {
    if (window.playlistsUI) playlistsUI.searchLibrary(String($(this).data("artist")));
  });

  /* ──────────────────────────────── Open/close ────────────────────────────── */
  function setOpen(on) {
    if (on) {
      if (window.queuePanel) queuePanel.close();
      if (window.lyricsPanel) lyricsPanel.close();
    } else {
      pinned = null;
      shown = null;
    }
    panel.prop("hidden", !on);
    $("body").toggleClass("songinfo-open", on);
    $(".playerSongInfo").attr("aria-expanded", on ? "true" : "false");
    if (on) {
      render(currentSong());
      // Next frame, so the slide-in transition runs from the hidden state
      requestAnimationFrame(function () { panel.addClass("siIn"); });
    } else {
      panel.removeClass("siIn");
    }
  }
  // From the playbar or the I key: follow the current song.
  function toggle() {
    if (isOpen() && !pinned) {
      setOpen(false);
      return;
    }
    pinned = null;
    if (isOpen()) render(currentSong());
    else setOpen(true);
    panel.find(".songInfoClose").trigger("focus");
  }
  // From a row menu: show this song.
  function openFor(song) {
    pinned = song && song !== window.songName ? song : null;
    if (isOpen()) render(currentSong());
    else setOpen(true);
    panel.find(".songInfoClose").trigger("focus");
  }

  $(".playerSongInfo").on("click", function (e) {
    if ($(e.target).closest(".playerFavBtn, a").length) return;
    if (!window.songName) return;
    toggle();
  }).on("keydown", function (e) {
    if ((e.key === "Enter" || e.key === " ") && e.target === this) {
      e.preventDefault();
      e.stopPropagation();
      if (window.songName) toggle();
    }
  });
  panel.find(".songInfoClose").on("click", function () {
    setOpen(false);
    $(".playerCover.playerSongInfo").trigger("focus");
  });
  panel.on("keydown", function (e) {
    if (e.key === "Escape" && !$(".popMenu:visible").length) {
      setOpen(false);
      $(".playerCover.playerSongInfo").trigger("focus");
    }
  });

  window.songInfoPanel = {
    toggle: toggle,
    openFor: openFor,
    close: function () { if (isOpen()) setOpen(false); },
    // Song changed or play state changed (js.php), or favorites changed (playlists.js).
    refresh: function () {
      if (!isOpen()) return;
      var song = currentSong();
      if (song !== shown) render(song);
      else renderActions();
    },
    // The song's info was edited (songedit.js): look everything up again.
    songEdited: function (song) {
      delete cache[song];
      delete online[song];
      if (isOpen() && shown === song) render(song);
    },
    // A playlist was edited: membership may have changed.
    playlistsChanged: function () {
      cache = {};
      if (isOpen() && shown) load(shown);
    },
  };
})();
