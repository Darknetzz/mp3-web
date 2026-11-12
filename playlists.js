/* ────────────────────────────────────────────────────────────────────────── */
/*                                  Playlists                                 */
/* ────────────────────────────────────────────────────────────────────────── */
// Playlist bar, playlist views in the song table, favorites, the ⋯ row menu,
// drag-to-reorder and M3U import/export. Playlists live on the server
// (playlists.php); this file keeps a cache and renders the UI.
// Relies on globals from js.php: playlistTable, library helpers, playSong,
// queueSong, contextOrder, apiURL, csrfToken, showToast, songName, currentIndex.

/* Column formatters (referenced by name from index.php, must be global) */
function favoriteFormatter(value, row) {
  var id = String(row.id).trim();
  var song = window.playlistsUI ? playlistsUI.songOf(id) : null;
  var on = !!(song && window.playlistsUI && playlistsUI.isFavorite(song));
  return '<button type="button" class="favBtn' + (on ? ' on' : '') + '" data-song-id="' + id + '" aria-pressed="' + on + '" ' +
         'title="' + (on ? 'Remove from Favorites' : 'Add to Favorites') + '" aria-label="' + (on ? 'Remove from Favorites' : 'Add to Favorites') + '">' +
         lucideIcon('heart', on ? 'filled' : '') + '</button>';
}
// Song names wrap to at most two lines and are trimmed with "…"; the full name is the tooltip.
// value is the server-rendered cell, already HTML-escaped.
function nameFormatter(value, row) {
  var html = String(value == null ? "" : value).trim();
  if (isCardView()) return songCardText(html, row);
  return '<span class="songName" title="' + html.replace(/"/g, "&quot;") + '">' + html + '</span>';
}
// Grid view (bootstrap-table's card view, styled as cards in style.css)
function isCardView() {
  var t = $("#playlistTable").data("bootstrap.table");
  return !!(t && t.options.cardView);
}
// Card text: ID3 title + artist when tagged, else the file name. Values are
// server-rendered cells, already HTML-escaped.
function songCardText(name, row) {
  var tag = function (v) { return String(v == null ? "" : v).trim(); };
  var title  = tag(row.title) || name;
  var artist = tag(row.artist);
  var sub    = [tag(row.album), tag(row.year)].filter(Boolean).join(" · ");
  return '<span class="songCardText">' +
           '<span class="cardTitle" title="' + title.replace(/"/g, "&quot;") + '">' + title + '</span>' +
           '<span class="cardArtist">' + (artist || "&nbsp;") + '</span>' +
           (sub ? '<span class="cardSub">' + sub + '</span>' : '') +
         '</span>';
}
// Grid cards are ~180px wide, so they load the 512px cover instead of the 96px one.
function coverFormatter(value) {
  var html = String(value == null ? "" : value);
  return isCardView() ? html.replace(/([?&](?:amp;)?size=)sm\b/, "$1lg") : html;
}
function moreFormatter(value, row) {
  return '<button type="button" class="moreBtn" data-song-id="' + String(row.id).trim() + '" title="More" aria-label="More actions" aria-haspopup="menu">' +
         lucideIcon('ellipsis') + '</button>';
}

(function () {
  "use strict";

  var FAVORITES = "favorites";
  var VIEW_KEY  = "playlistView";

  var lists     = [];          // summaries from listPlaylists
  var cache     = {};          // id -> full playlist (songs, version, missing)
  var favorites = new Set();   // song file names
  var view      = { type: "library", id: null };

  function esc(str) {
    return $("<div>").text(str == null ? "" : String(str)).html();
  }

  function request(method, action, data) {
    var payload = $.extend({ action: action }, data || {});
    if (method === "POST") payload.csrf_token = csrfToken;
    return $.ajax({ url: apiURL, type: method, data: payload, dataType: "json", cache: false });
  }

  function failed(res) {
    showToast((res && res.response) || "Something went wrong.", "danger");
  }

  function songOf(id) {
    var row = getSongByIndex(id);
    return row ? row._song : null;
  }

  function idsFor(songs) {
    var ids = [];
    (songs || []).forEach(function (song) {
      var id = findIndexByFilename(song);
      if (id !== null) ids.push(id);
    });
    return ids;
  }

  // URL of a playlist's own image; p.image is its cache-busting token (or null).
  function imageURL(p) {
    return apiURL + "?action=playlistImage&id=" + encodeURIComponent(p.id) + "&v=" + encodeURIComponent(p.image);
  }

  function coverURL(song) {
    return "cover.php?file=" + encodeURIComponent(song) + "&size=sm";
  }

  function listById(id) {
    for (var i = 0; i < lists.length; i++) {
      if (lists[i].id === id) return lists[i];
    }
    return null;
  }

  /* ───────────────────────────── Data loading ───────────────────────────── */
  function loadLists() {
    return request("GET", "listPlaylists").then(function (res) {
      if (res.status !== "success") return failed(res);
      lists = res.data.playlists || [];
      renderBar();
    });
  }

  function loadPlaylist(id) {
    return request("GET", "getPlaylist", { id: id }).then(function (res) {
      if (res.status !== "success") {
        failed(res);
        return null;
      }
      storePlaylist(res.data);
      return res.data;
    });
  }

  // Keep the cache, summary and favorites set in step with a server response.
  function storePlaylist(p) {
    cache[p.id] = p;
    var summary = listById(p.id);
    if (summary) {
      summary.name    = p.name;
      summary.count   = p.count;
      summary.missing = p.missing.length;
      summary.version = p.version;
      summary.description = p.description || "";
      summary.image   = p.image || null;
      summary.shared  = p.shared;
      summary.collab  = p.collab;
      summary.can_edit = p.can_edit;
    }
    if (p.id === FAVORITES) {
      favorites = new Set(p.songs);
      refreshHearts();
    }
    if (window.queuePanel) queuePanel.refresh(); // "Next from" follows playlist edits
    if (window.songInfoPanel) songInfoPanel.playlistsChanged();
  }

  /* ──────────────────────────────── Views ───────────────────────────────── */
  function rememberView() {
    try {
      localStorage.setItem(VIEW_KEY, view.type === "playlist" ? view.id : "library");
    } catch (e) {}
  }

  function showLibrary() {
    view = { type: "library", id: null };
    rememberView();
    playlistTable.bootstrapTable("load", library);
    var sortName = playlistTable.attr("data-sort-name");
    if (sortName) {
      playlistTable.bootstrapTable("sortBy", { field: sortName, sortOrder: playlistTable.attr("data-sort-order") || "asc" });
    }
    renderBar();
    renderHeader();
  }

  function showPlaylist(id) {
    var open = function (p) {
      if (!p) return showLibrary();
      view = { type: "playlist", id: id };
      rememberView();
      var rows = idsFor(p.songs).map(function (sid) { return getSongByIndex(sid); });
      playlistTable.bootstrapTable("load", rows);
      playlistTable.bootstrapTable("sortReset");
      renderBar();
      renderHeader();
    };
    if (cache[id]) {
      open(cache[id]);
      // refresh in the background in case someone else changed it
      loadPlaylist(id).then(function (p) {
        if (p && view.type === "playlist" && view.id === id && p.version !== (cache[id] || {}).version) open(p);
      });
      return;
    }
    loadPlaylist(id).then(open);
  }

  // Re-render the current playlist view from the cache (after an edit).
  function rerenderView() {
    if (view.type === "playlist" && cache[view.id]) {
      var scrollY = window.scrollY;
      var rows = idsFor(cache[view.id].songs).map(function (sid) { return getSongByIndex(sid); });
      playlistTable.bootstrapTable("load", rows);
      playlistTable.bootstrapTable("sortReset");
      window.scrollTo(0, scrollY);
    }
    renderBar();
    renderHeader();
  }

  /* ──────────────────────────────── Bar ──────────────────────────────────── */
  function renderBar() {
    var html = '<button type="button" class="plPill' + (view.type === "library" ? " active" : "") + '" data-view="library">' +
               lucideIcon('list-music') + '<span>All songs</span><span class="plCount">' + (library ? library.length : "") + '</span></button>';
    var hasPinned = lists.some(function (p) { return p.pinned; });
    var divided = false;
    lists.forEach(function (p) {
      var isFav = p.id === FAVORITES;
      if (hasPinned && !isFav && !p.pinned && !divided) {
        html += '<span class="plDivider" role="separator"></span>';
        divided = true;
      }
      var active = view.type === "playlist" && view.id === p.id;
      var icon = p.image ? '<img class="plThumb" src="' + esc(imageURL(p)) + '" alt="" loading="lazy">'
               : isFav ? lucideIcon("heart", "filled") : lucideIcon("list-video");
      var tip = p.name + (p.mine === false ? "\nBy " + (p.owner_name || "someone else") + (p.can_edit ? " · you can edit" : "") : p.shared ? "\nShared with everyone" : "") +
                (p.description ? "\n" + p.description : "");
      html += '<button type="button" class="plPill' + (active ? " active" : "") + (isFav ? " plFav" : "") + (p.pinned ? " plPinned" : "") + '"' +
              ' data-view="' + esc(p.id) + '"' + (isFav ? "" : ' draggable="true"') + ' title="' + esc(tip) + '">' +
              icon + '<span>' + esc(p.name) + '</span>' +
              (p.pinned ? lucideIcon("pin", "plPinIcon") : "") +
              (p.shared || p.mine === false ? lucideIcon("share-2", "plShareIcon") : "") +
              '<span class="plCount">' + p.count + '</span></button>';
    });
    html += '<button type="button" class="plPill plAdd" data-action="add-menu" aria-haspopup="menu" title="New or import playlist">' +
            lucideIcon('plus') + '<span>New playlist</span></button>';
    $("#playlistBar").html(html);
    if (window.queuePanel) queuePanel.refresh(); // playlist names may have changed
  }

  function renderHeader() {
    var header = $("#playlistHeader");
    if (view.type !== "playlist" || !cache[view.id]) {
      header.hide().empty();
      return;
    }
    var p = cache[view.id];
    var isFav = p.id === FAVORITES;
    var count = p.count + " song" + (p.count === 1 ? "" : "s");
    var missing = p.missing.length ? ' · <span class="text-warning">' + p.missing.length + ' not in library</span>' : "";
    var editable = canEditList(p);
    var canDrag = p.count > 1 && editable;
    var pinned = !!(listById(p.id) || {}).pinned;
    var sharing = p.mine === false
      ? ' · ' + lucideIcon('share-2', 'me-1') + 'By ' + esc(p.owner_name || "someone else") + (editable ? ' · you can edit' : ' · read-only')
      : p.shared ? ' · ' + lucideIcon('share-2', 'me-1') + (p.collab ? 'Shared, everyone can edit' : 'Shared with everyone') : '';
    header.html(
      (editable
        ? '<button type="button" class="plCover" data-action="edit" title="Edit details" aria-label="Edit playlist details">' + coverHtml(p) +
          '<span class="plCoverEdit">' + lucideIcon('pencil') + '</span></button>'
        : '<span class="plCover">' + coverHtml(p) + '</span>') +
      '<div class="plHeaderText">' +
        '<h3 class="plTitle">' + (isFav ? lucideIcon('heart', 'filled text-danger me-2') : "") + esc(p.name) +
          (pinned ? lucideIcon('pin', 'plPinIcon ms-2') : "") + '</h3>' +
        (p.description ? '<p class="plDesc">' + esc(p.description) + '</p>' : "") +
        '<div class="text-secondary small">' + count + missing + sharing + (canDrag ? ' · drag songs to reorder' : '') + '</div>' +
      '</div>' +
      '<div class="plHeaderActions">' +
        '<button type="button" class="btn btn-success btn-pill" data-action="play"' + (p.count ? "" : " disabled") + '>' + lucideIcon('play', 'filled me-1') + 'Play</button>' +
        '<button type="button" class="btn btn-outline-secondary btn-pill" data-action="shuffle"' + (p.count ? "" : " disabled") + '>' + lucideIcon('shuffle', 'me-1') + 'Shuffle</button>' +
        '<button type="button" class="btn btn-outline-secondary btn-pill" data-action="queue-all"' + (p.count ? "" : " disabled") + '>' + lucideIcon('list-ordered', 'me-1') + 'Add to queue</button>' +
        '<button type="button" class="btn btn-outline-secondary btn-icon btn-pill" data-action="playlist-menu" aria-haspopup="menu" title="More" aria-label="Playlist actions">' + lucideIcon('ellipsis') + '</button>' +
      '</div>'
    ).show();
  }

  // The playlist's image, else a 2×2 mosaic of its first song covers, else an icon.
  function coverHtml(p) {
    if (p.image) return '<img src="' + esc(imageURL(p)) + '" alt="">';
    var ids = idsFor(p.songs).slice(0, 4);
    var icon = '<span class="plCoverIcon">' + lucideIcon(p.id === FAVORITES ? 'heart' : 'list-music', p.id === FAVORITES ? 'filled' : '') + '</span>';
    if (!ids.length) return icon;
    var names = ids.map(function (sid) { return songOf(sid); }).filter(Boolean);
    if (names.length < 4) return icon + '<img src="' + esc(coverURL(names[0])) + '" alt="">';
    return icon + '<span class="plMosaic">' + names.map(function (n) { return '<img src="' + esc(coverURL(n)) + '" alt="">'; }).join("") + '</span>';
  }
  // Song covers that don't exist answer 204: hide them so the icon behind shows.
  function hideBrokenCover(e) {
    if (e.target.tagName === "IMG") e.target.style.visibility = "hidden";
  }
  document.getElementById("playlistHeader").addEventListener("error", hideBrokenCover, true);

  /* ─────────────────────────────── Hearts ────────────────────────────────── */
  function refreshHearts() {
    $("#playlistTable .favBtn").each(function () {
      var song = songOf($(this).data("song-id"));
      var on = !!song && favorites.has(song);
      $(this).toggleClass("on", on).attr("aria-pressed", on)
        .attr("title", on ? "Remove from Favorites" : "Add to Favorites")
        .find("svg").toggleClass("filled", on);
    });
    updatePlayerHeart();
    if (window.songInfoPanel) songInfoPanel.refresh();
  }

  function updatePlayerHeart() {
    var btn = $(".playerFavBtn");
    if (!songName) {
      btn.hide();
      return;
    }
    var on = favorites.has(songName);
    btn.show().toggleClass("on", on).attr("aria-pressed", on)
      .attr("title", on ? "Remove from Favorites" : "Add to Favorites")
      .find("svg").toggleClass("filled", on);
  }

  function toggleFavorite(song) {
    if (!song) return;
    // optimistic update
    var wasOn = favorites.has(song);
    if (wasOn) favorites.delete(song); else favorites.add(song);
    refreshHearts();
    request("POST", "toggleFavorite", { song: song }).then(function (res) {
      if (res.status !== "success") {
        if (wasOn) favorites.add(song); else favorites.delete(song);
        refreshHearts();
        return failed(res);
      }
      storePlaylist(res.data);
      if (view.type === "playlist" && view.id === FAVORITES) rerenderView();
      else renderBar();
    }, function () {
      if (wasOn) favorites.add(song); else favorites.delete(song);
      refreshHearts();
      showToast("Could not reach the server.", "danger");
    });
  }

  /* ───────────────────────────── Edit actions ────────────────────────────── */
  function addSongs(id, songs) {
    return request("POST", "addToPlaylist", { id: id, songs: songs }).then(function (res) {
      if (res.status !== "success") return failed(res);
      storePlaylist(res.data);
      showToast(res.response, "success");
      if (view.type === "playlist" && view.id === id) rerenderView(); else renderBar();
    });
  }

  function removeSong(id, song) {
    return request("POST", "removeFromPlaylist", { id: id, song: song }).then(function (res) {
      if (res.status !== "success") return failed(res);
      storePlaylist(res.data);
      rerenderView();
    });
  }

  function reorder(id, songs) {
    var p = cache[id];
    if (!p) return;
    return request("POST", "reorderPlaylist", { id: id, songs: songs, if_version: p.version }).then(function (res) {
      if (res.data && res.data.id) storePlaylist(res.data);
      if (res.status === "conflict") showToast(res.response, "warning");
      else if (res.status !== "success") failed(res);
      rerenderView();
    });
  }

  function move(id, song, delta) {
    var p = cache[id];
    if (!p) return;
    var songs = p.songs.slice();
    var from = songs.indexOf(song);
    var to = from + delta;
    if (from === -1 || to < 0 || to >= songs.length) return;
    songs.splice(to, 0, songs.splice(from, 1)[0]);
    reorder(id, songs);
  }

  function createPlaylist(name, songs, description) {
    return request("POST", "createPlaylist", { name: name, songs: songs || [], description: description || "" }).then(function (res) {
      if (res.status !== "success") return failed(res);
      showToast(res.response, "success");
      cache[res.data.id] = res.data;
      return loadLists().then(function () { return res.data; });
    });
  }

  function deletePlaylist(id) {
    return request("POST", "deletePlaylist", { id: id }).then(function (res) {
      if (res.status !== "success") return failed(res);
      showToast(res.response, "success");
      delete cache[id];
      lists = lists.filter(function (p) { return p.id !== id; });
      if (view.type === "playlist" && view.id === id) showLibrary(); else renderBar();
    });
  }

  // Name + description from the Edit details dialog.
  function saveDetails(id, name, description) {
    return request("POST", "updatePlaylistDetails", { id: id, name: name, description: description }).then(function (res) {
      if (res.status !== "success") return failed(res);
      storePlaylist(res.data);
      renderBar();
      renderHeader();
    });
  }

  // Image changes apply right away (not on Save), so the dialog preview follows them.
  function afterImageChange(res) {
    if (res.status !== "success") return failed(res);
    storePlaylist(res.data);
    renderBar();
    renderHeader();
    dialog.find(".plEditPreview").html(coverHtml(res.data));
    dialog.find("[data-image=remove]").toggle(!!res.data.image);
  }

  function uploadImage(id, file) {
    var form = new FormData();
    form.append("action", "setPlaylistImage");
    form.append("csrf_token", csrfToken);
    form.append("id", id);
    form.append("image", file);
    dialog.find(".plEditPreview").addClass("plBusy");
    $.ajax({ url: apiURL, type: "POST", data: form, processData: false, contentType: false, dataType: "json" })
      .then(afterImageChange, function () { showToast("Could not upload the image.", "danger"); })
      .always(function () { dialog.find(".plEditPreview").removeClass("plBusy"); });
  }

  function removeImage(id) {
    request("POST", "removePlaylistImage", { id: id }).then(afterImageChange, failed);
  }

  function setPinned(id, pinned) {
    var p = listById(id);
    if (p) p.pinned = pinned;
    request("POST", "setPlaylistPinned", { id: id, pinned: pinned ? 1 : 0 }).then(function (res) {
      if (res.status !== "success") failed(res);
      loadLists().then(renderHeader); // the server sorts pinned ones first
    });
  }

  function setSharing(id, shared, collab) {
    request("POST", "setPlaylistSharing", { id: id, shared: shared ? 1 : 0, collab: collab ? 1 : 0 }).then(function (res) {
      if (res.status !== "success") return failed(res);
      showToast(res.response, "success");
      storePlaylist(res.data);
      loadLists().then(renderHeader);
    });
  }

  // Move playlist `moving` before/after `target` in the bar. Dropping into the
  // other group (pinned / not pinned) pins or unpins it.
  function movePlaylist(moving, target, after) {
    var m = listById(moving), t = listById(target);
    if (!m || !t || moving === target) return;
    var pinChange = !!m.pinned !== !!t.pinned;
    m.pinned = !!t.pinned;
    var rest = lists.filter(function (p) { return p.id !== moving; });
    rest.splice(rest.indexOf(t) + (after ? 1 : 0), 0, m);
    lists = rest;
    renderBar();
    var ids = lists.filter(function (p) { return p.id !== FAVORITES; }).map(function (p) { return p.id; });
    var calls = [request("POST", "setPlaylistOrder", { ids: JSON.stringify(ids) })];
    if (pinChange) calls.push(request("POST", "setPlaylistPinned", { id: moving, pinned: m.pinned ? 1 : 0 }));
    Promise.all(calls).then(function (results) {
      var bad = results.filter(function (res) { return !res || res.status !== "success"; })[0];
      if (bad) { failed(bad); return loadLists(); }
      if (pinChange) renderHeader();
    }, function () {
      showToast("Could not save the playlist order.", "danger");
      loadLists();
    });
  }

  function importFile(file) {
    var form = new FormData();
    form.append("action", "importPlaylist");
    form.append("csrf_token", csrfToken);
    form.append("playlist", file);
    $.ajax({ url: apiURL, type: "POST", data: form, processData: false, contentType: false, dataType: "json" })
      .then(function (res) {
        if (res.status !== "success") return failed(res);
        showToast(res.response, res.data.not_found && res.data.not_found.length ? "warning" : "success");
        cache[res.data.id] = res.data;
        loadLists().then(function () { showPlaylist(res.data.id); });
      }, function () {
        showToast("Could not import the playlist.", "danger");
      });
  }

  /* ──────────────────────────────── Playback ─────────────────────────────── */
  function playPlaylist(id, shuffled) {
    var p = cache[id];
    if (!p) return;
    var ids = idsFor(p.songs);
    if (!ids.length) return;
    if (window.sessionSync && sessionSync.blocks()) return sessionSync.denied();
    playContext = { type: "playlist", id: id };
    if (shuffled && !shuffle) toggleShuffle();
    if (!shuffled && shuffle) toggleShuffle();
    playSong(shuffled ? ids[Math.floor(Math.random() * ids.length)] : ids[0]);
  }

  /* ─────────────────────────────── Popup menu ────────────────────────────── */
  // One menu element serves the ⋯ buttons, the playlist menus and the song
  // context menu (right-click, long-press, context-menu key / Shift+F10).
  // Items: "-" (divider), { header }, or { label, icon, filled, danger,
  // disabled, run } or { label, icon, submenu: fn → items } (a second panel).
  var menu = $('<div class="popMenu" role="menu" tabindex="-1" style="display:none;"></div>').appendTo(document.body);
  var menuItems = [];       // actionable items of the shown panel, by data-i
  var menuAnchor = null;    // element the menu belongs to; focus returns here
  var menuAt = null;        // {x, y} for a menu opened at a point, else null
  var menuPanel = [];       // items of the shown panel
  var menuStack = [];       // parent panels while a submenu is shown
  var menuOpenedAt = 0;
  var ignoreScrollUntil = 0;

  function menuItemHtml(item, i) {
    var cls = "popItem" + (item.danger ? " text-danger" : "") + (item.back ? " popBack" : "");
    return '<button type="button" class="' + cls + '" role="menuitem" tabindex="-1" data-i="' + i + '"' +
           (item.submenu ? ' aria-haspopup="menu"' : "") + (item.disabled ? ' disabled aria-disabled="true"' : "") + '>' +
           (item.icon ? lucideIcon(item.icon, item.filled ? 'filled' : '') : '<span class="lucide"></span>') +
           '<span class="popLabel">' + esc(item.label) + '</span>' +
           (item.submenu ? lucideIcon('chevron-right', 'popChevron') : "") + '</button>';
  }

  function renderMenu(items, focusIndex) {
    menuPanel = items;
    menuItems = [];
    var html = "";
    items.forEach(function (item) {
      if (item === "-") {
        html += '<div class="popDivider" role="separator"></div>';
      } else if (item.header) {
        html += '<div class="popHeader" role="presentation">' + esc(item.header) + '</div>';
      } else {
        menuItems.push(item);
        html += menuItemHtml(item, menuItems.length - 1);
      }
    });
    menu.html(html).show();
    placeMenu();
    var enabled = menu.find(".popItem:not([disabled])");
    var target = focusIndex != null ? menu.find('.popItem[data-i="' + focusIndex + '"]') : enabled.first();
    (target.length ? target : enabled.first()).trigger("focus");
  }

  // The visible area the menu may use: the viewport minus the fixed playbar.
  function menuBounds() {
    var b = { left: 8, top: 8, right: window.innerWidth - 8, bottom: window.innerHeight - 8 };
    var bar = $(".audio-player-container:visible")[0];
    if (bar) {
      var r = bar.getBoundingClientRect();
      if (r.top <= 1) b.top = Math.max(b.top, r.bottom + 8); else b.bottom = Math.min(b.bottom, r.top - 8);
    }
    return b;
  }

  function placeMenu() {
    var b = menuBounds();
    menu.css({ left: 0, top: 0, maxHeight: Math.max(120, Math.min(420, b.bottom - b.top)) + "px" });
    var mw = menu.outerWidth(), mh = menu.outerHeight();
    var left, top;
    if (menuAt) {
      // Open down-right of the point; flip at the right and bottom edges.
      left = menuAt.x + 2;
      top = menuAt.y + 2;
      if (left + mw > b.right) left = menuAt.x - mw - 2;
      if (top + mh > b.bottom) top = menuAt.y - mh - 2;
    } else {
      var rect = menuAnchor.getBoundingClientRect();
      left = rect.right - mw;
      top = rect.bottom + 4;
      if (top + mh > b.bottom) top = rect.top - mh - 4;
    }
    left = Math.max(b.left, Math.min(left, b.right - mw));
    top = Math.max(b.top, Math.min(top, b.bottom - mh));
    menu.css({ left: left, top: top });
  }

  // opts.at: {x, y} viewport point (context menu); opts.label: aria-label
  function openMenu(anchor, items, opts) {
    opts = opts || {};
    if (!items || !items.length) return;
    menuAnchor = anchor;
    menuAt = opts.at || null;
    menuStack = [];
    menuOpenedAt = Date.now();
    menu.attr("aria-label", opts.label || null);
    renderMenu(items);
  }

  function openSubmenu(i) {
    var item = menuItems[i];
    if (!item || !item.submenu) return;
    menuStack.push({ items: menuPanel, focus: i });
    var back = { label: item.label, icon: "chevron-left", back: true };
    renderMenu([back, "-"].concat(item.submenu()));
  }

  function menuBack() {
    var prev = menuStack.pop();
    if (prev) renderMenu(prev.items, prev.focus);
  }

  function closeMenu(refocus) {
    if (!menu.is(":visible")) return;
    menu.hide().empty();
    menuItems = [];
    menuStack = [];
    if (refocus !== false && menuAnchor && document.body.contains(menuAnchor)) menuAnchor.focus({ preventScroll: true });
    menuAnchor = null;
  }

  function activate(i) {
    var item = menuItems[i];
    if (!item || item.disabled) return;
    if (item.submenu) return openSubmenu(i);
    if (item.back) return menuBack();
    closeMenu();
    if (item.run) item.run();
  }

  menu.on("click", ".popItem", function () {
    activate($(this).data("i"));
  });
  menu.on("mousemove", ".popItem:not([disabled])", function () {
    if (document.activeElement !== this) this.focus({ preventScroll: true });
  });
  menu.on("contextmenu", function (e) { e.preventDefault(); });
  menu.on("keydown", function (e) {
    // Keys pressed in the menu belong to it, not to the player shortcuts.
    e.stopPropagation();
    var items = menu.find(".popItem:not([disabled])");
    var i = items.index(document.activeElement);
    var current = i >= 0 ? $(items[i]).data("i") : null;
    switch (e.key) {
      case "ArrowDown": e.preventDefault(); items.eq((i + 1) % items.length).trigger("focus"); break;
      case "ArrowUp":   e.preventDefault(); items.eq((i - 1 + items.length) % items.length).trigger("focus"); break;
      case "Home":      e.preventDefault(); items.first().trigger("focus"); break;
      case "End":       e.preventDefault(); items.last().trigger("focus"); break;
      case "ArrowRight":
        if (current !== null && menuItems[current].submenu) { e.preventDefault(); openSubmenu(current); }
        break;
      case "ArrowLeft":
      case "Backspace":
        if (menuStack.length) { e.preventDefault(); menuBack(); }
        break;
      case "Enter":
      case " ":
        e.preventDefault();
        if (current !== null) activate(current);
        break;
      case "Escape":    e.preventDefault(); closeMenu(); break;
      case "Tab":       e.preventDefault(); closeMenu(); break;
    }
  });
  $(document).on("mousedown", function (e) {
    if (!menu.is(":visible") || $(e.target).closest(".popMenu").length) return;
    // A ⋯ button handles clicks on itself; a context menu closes on any outside click
    if (!menuAt && (e.target === menuAnchor || $(e.target).closest(menuAnchor).length)) return;
    closeMenu(false);
  });
  $(document).on("keydown", function (e) {
    if (e.key === "Escape") {
      closeMenu();
      closeDialog();
    }
  });
  $(window).on("resize", function () { closeMenu(false); });
  window.addEventListener("scroll", function (e) {
    if (!menu.is(":visible") || Date.now() < ignoreScrollUntil) return;
    if (e.target !== document && menu[0].contains(e.target)) return; // the menu's own scrolling
    closeMenu(false);
  }, true);

  function addToPlaylistItems(songs) {
    var items = [];
    lists.forEach(function (p) {
      if (p.id === FAVORITES || !canEditList(p)) return;
      items.push({ label: p.name, icon: "list-video", run: function () { addSongs(p.id, songs); } });
    });
    if (items.length) items.push("-");
    items.push({ label: "New playlist…", icon: "plus", run: function () {
      newPlaylist(songs);
    } });
    return items;
  }

  function copyText(text) {
    var done = function () { showToast("Copied: " + esc(text), "success"); };
    var fallback = function () {
      var ta = $("<textarea readonly>").val(text).css({ position: "fixed", top: 0, left: "-9999px" }).appendTo(document.body);
      ta[0].select();
      var ok = false;
      try { ok = document.execCommand("copy"); } catch (e) {}
      ta.remove();
      if (ok) done(); else showToast("Could not copy to the clipboard.", "danger");
    };
    if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(text).then(done, fallback);
    else fallback();
  }

  // Everything you can do with one song. Shared by the ⋯ button and the
  // context menu so they always match. Items the user can't use are left out:
  // playback in a listening session they don't control, edits in demo mode.
  function songMenuItems(id) {
    var song = songOf(id);
    if (!song) return null;
    var canPlay = !(window.sessionSync && sessionSync.blocks());
    var canEdit = !window.demoMode;
    var items = [];
    if (canPlay) {
      items.push(
        { label: "Play", icon: "play", run: function () { selectSong(id); playSelected(); } },
        { label: "Play next", icon: "list-start", run: function () { queueSong(id, { next: true }); } },
        { label: "Add to queue", icon: "list-ordered", run: function () { queueSong(id); } },
        "-"
      );
    }
    if (canEdit) {
      var fav = favorites.has(song);
      items.push(
        { label: "Add to playlist", icon: "list-plus", submenu: function () { return addToPlaylistItems([song]); } },
        { label: fav ? "Remove from Favorites" : "Add to Favorites", icon: "heart", filled: fav, run: function () { toggleFavorite(song); } },
        "-"
      );
    }
    items.push(
      { label: "Song info", icon: "info", run: function () { if (window.songInfoPanel) songInfoPanel.openFor(song); } },
    );
    if (window.songEditor) items.push({ label: "Edit info", icon: "pencil", run: function () { songEditor.open(song); } });
    items.push(
      { label: "Download", icon: "download", run: function () { downloadSong(id); } },
      { label: "Copy song name", icon: "copy", run: function () { copyText(songTitle(id)); } }
    );

    if (canEdit && view.type === "playlist" && cache[view.id] && canEditList(cache[view.id])) {
      var p = cache[view.id];
      var pos = p.songs.indexOf(song);
      var sorted = !!playlistTable.bootstrapTable("getOptions").sortName;
      items.push("-", { header: "This playlist" });
      items.push({ label: "Move up", icon: "arrow-up", disabled: pos <= 0 || sorted, run: function () { move(p.id, song, -1); } });
      items.push({ label: "Move down", icon: "arrow-down", disabled: pos === -1 || pos >= p.songs.length - 1 || sorted, run: function () { move(p.id, song, 1); } });
      if (p.id !== FAVORITES) {
        items.push({ label: "Remove from this playlist", icon: "circle-minus", run: function () { removeSong(p.id, song); } });
      }
    }
    if (canEdit && window.isAdminUser) {
      items.push("-", { label: "Delete…", icon: "trash-2", danger: true, run: function () {
        confirmDialog("Delete “" + songTitle(id) + "”?", "The file is removed from the music folder for everyone.", "Delete", function () {
          deleteSong(id);
        });
      } });
    }
    return items;
  }

  function rowMenu(anchor, id) {
    openMenu(anchor, songMenuItems(id), { label: "Actions for " + songTitle(id) });
  }

  // Open the song menu at a viewport point, selecting the row first.
  function contextMenuAt(id, x, y) {
    if (!songOf(id)) return;
    selectSong(id);
    openMenu(playlistTable[0], songMenuItems(id), { at: { x: x, y: y }, label: "Actions for " + songTitle(id) });
  }

  // Keyboard: open for the selected row, just below its title.
  function contextMenuForSelected() {
    var row = $("#playlistTable > tbody > tr.songSelected[data-uniqueid]")[0];
    if (!row) return false;
    var before = window.scrollY;
    ensureRowVisible(row);
    var open = function () {
      var cell = $(row).children('td[data-field="name"], td.cursor-pointer')[0] || row;
      var r = cell.getBoundingClientRect();
      contextMenuAt($(row).attr("data-uniqueid"), r.left + 16, r.bottom - 4);
    };
    if (window.scrollY !== before) {
      ignoreScrollUntil = Date.now() + 700; // let a smooth scroll finish first
      setTimeout(open, 350);
    } else {
      open();
    }
    return true;
  }

  var rowSelector = "tbody > tr[data-uniqueid]";
  var press = null; // touch long-press in progress

  function cancelPress() {
    if (press) clearTimeout(press.timer);
    press = null;
  }

  $("#playlistTable")
    .on("contextmenu", function (e) {
      var row = $(e.target).closest("#playlistTable > " + rowSelector);
      cancelPress();
      // Already opened by a long-press or the keyboard for this same gesture
      if (menu.is(":visible") && Date.now() - menuOpenedAt < 700) return e.preventDefault();
      if (!row.length) {
        // Context-menu key / Shift+F10 while the table itself has focus
        if (e.target === playlistTable[0] && contextMenuForSelected()) e.preventDefault();
        return; // elsewhere: the browser's own menu
      }
      e.preventDefault();
      var x = e.clientX, y = e.clientY;
      if (!x && !y) { // keyboard-invoked on a focused cell
        var r = row[0].getBoundingClientRect();
        x = r.left + 16; y = r.bottom - 4;
      }
      contextMenuAt(row.attr("data-uniqueid"), x, y);
    })
    .on("keydown", function (e) {
      if (e.key !== "ContextMenu" && !(e.shiftKey && e.key === "F10")) return;
      var row = $(e.target).closest(rowSelector);
      if (row.length) selectSong(row.attr("data-uniqueid"));
      if (contextMenuForSelected()) { e.preventDefault(); e.stopPropagation(); }
    })
    .on("touchstart", rowSelector, function (e) {
      var touches = e.originalEvent.touches;
      cancelPress();
      if (touches.length !== 1) return;
      var id = $(this).attr("data-uniqueid");
      var p = press = { x: touches[0].clientX, y: touches[0].clientY, fired: false };
      p.timer = setTimeout(function () {
        p.fired = true;
        contextMenuAt(id, p.x, p.y);
        try { if (navigator.vibrate) navigator.vibrate(10); } catch (err) {}
      }, 500);
    })
    .on("touchmove", rowSelector, function (e) {
      var t = e.originalEvent.touches[0];
      if (press && !press.fired && t && Math.hypot(t.clientX - press.x, t.clientY - press.y) > 10) cancelPress();
    })
    .on("touchend touchcancel", rowSelector, function (e) {
      // After a long-press, don't let the lift turn into a click (which plays from the cover)
      if (press && press.fired && e.cancelable) e.preventDefault();
      cancelPress();
    });

  // Actions for one playlist: from the header's ⋯ (the open one) or a right-click on its pill.
  function playlistMenu(anchor, id, at) {
    id = id || view.id;
    var p = listById(id) || cache[id];
    if (!p) return;
    var isFav = id === FAVORITES;
    var items = [];
    if (view.type !== "playlist" || view.id !== id) {
      items.push({ label: "Open", icon: "list-video", run: function () { showPlaylist(id); } });
      items.push({ label: "Play", icon: "play", disabled: !p.count, run: function () { playPlaylist(id, false); } });
      items.push("-");
    }
    if (canEditList(p)) {
      items.push({ label: "Edit details…", icon: "pencil", run: function () { editDetails(id); } });
      items.push({ label: p.image ? "Change image…" : "Add image…", icon: "image", run: function () { chooseImage(id); } });
      if (p.image) items.push({ label: "Remove image", icon: "image-off", run: function () { removeImage(id); } });
    }
    // Sharing: only the owner, only with accounts
    if (!isFav && p.mine !== false && window.currentUser && currentUser.id > 0) {
      items.push({ label: "Sharing", icon: "share-2", submenu: function () {
        return [
          { label: "Only me", icon: !p.shared ? "circle-check" : "lock", run: function () { setSharing(id, false, false); } },
          { label: "Everyone can listen", icon: p.shared && !p.collab ? "circle-check" : "share-2", run: function () { setSharing(id, true, false); } },
          { label: "Everyone can edit", icon: p.shared && p.collab ? "circle-check" : "users", run: function () { setSharing(id, true, true); } }
        ];
      } });
    }
    if (!isFav) {
      items.push(p.pinned
        ? { label: "Unpin", icon: "pin-off", run: function () { setPinned(id, false); } }
        : { label: "Pin to top", icon: "pin", run: function () { setPinned(id, true); } });
    }
    items.push({ label: "Export .m3u", icon: "download", disabled: !p.count, run: function () {
      window.location.href = apiURL + "?action=exportPlaylist&id=" + encodeURIComponent(id);
    } });
    if (!isFav && p.mine !== false) {
      items.push("-", { label: "Delete playlist", icon: "trash-2", danger: true, run: function () {
        confirmDialog("Delete “" + p.name + "”?", "The songs stay in your library. This can't be undone.", "Delete", function () {
          deletePlaylist(id);
        });
      } });
    }
    openMenu(anchor, items, { at: at || null, label: "Actions for " + p.name });
  }

  var imageInput = $('<input type="file" accept="image/jpeg,image/png,image/gif,image/webp" hidden>').appendTo(document.body);
  var imageFor = null;
  function chooseImage(id) {
    imageFor = id;
    imageInput.val("").trigger("click");
  }
  imageInput.on("change", function () {
    if (this.files && this.files[0] && imageFor) uploadImage(imageFor, this.files[0]);
  });

  function editDetails(id) {
    var p = listById(id) || cache[id];
    if (!p) return;
    var isFav = id === FAVORITES;
    openDialog(
      '<h3 id="plDialogTitle">Edit details</h3>' +
      '<div class="plEdit">' +
        '<div class="plEditImage">' +
          '<button type="button" class="plEditPreview plCover" data-image="choose" title="Choose image" aria-label="Choose image">' + coverHtml(p) +
            '<span class="plCoverEdit">' + lucideIcon('image') + '</span></button>' +
          '<button type="button" class="btn btn-link btn-sm p-0" data-image="remove"' + (p.image ? "" : ' style="display:none"') + '>Remove image</button>' +
        '</div>' +
        '<div class="plEditFields">' +
          (isFav ? '' : '<label class="form-label small mb-1" for="plEditName">Name</label>' +
            '<input type="text" id="plEditName" class="form-control mb-2" maxlength="60" placeholder="Playlist name" value="' + esc(p.name) + '">') +
          '<label class="form-label small mb-1" for="plEditDesc">Description</label>' +
          '<textarea id="plEditDesc" class="form-control" rows="4" maxlength="300" placeholder="Add an optional description">' + esc(p.description || "") + '</textarea>' +
        '</div>' +
      '</div>' +
      '<div class="plDialogButtons"><button type="button" class="btn btn-outline-secondary btn-pill" data-dismiss>Cancel</button>' +
      '<button type="submit" class="btn btn-success btn-pill">Save</button></div>',
      function (name, form) {
        var desc = form.find("textarea").val();
        if ((isFav || name === p.name) && desc === (p.description || "")) return;
        saveDetails(id, isFav ? "" : name, desc);
      }
    );
    dialog.addClass("plDialogWide");
    dialog.find("[data-image=choose]").on("click", function () { chooseImage(id); });
    dialog.find("[data-image=remove]").on("click", function () { removeImage(id); });
  }

  // "New playlist" with the same fields as Edit details. The image is held
  // locally (previewed from the file) and uploaded once the playlist exists.
  function newPlaylist(songs, onCreated) {
    var pending = null, previewURL = null;
    var blank = coverHtml({ id: "", songs: songs || [] });
    function setPreview(file) {
      if (previewURL) URL.revokeObjectURL(previewURL);
      pending = file;
      previewURL = file ? URL.createObjectURL(file) : null;
      dialog.find(".plEditPreview").html((file ? '<img src="' + esc(previewURL) + '" alt="">' : blank) +
        '<span class="plCoverEdit">' + lucideIcon('image') + '</span>');
      dialog.find("[data-image=remove]").toggle(!!file);
    }
    openDialog(
      '<h3 id="plDialogTitle">New playlist</h3>' +
      '<div class="plEdit">' +
        '<div class="plEditImage">' +
          '<button type="button" class="plEditPreview plCover" data-image="choose" title="Choose image" aria-label="Choose image"></button>' +
          '<button type="button" class="btn btn-link btn-sm p-0" data-image="remove" style="display:none">Remove image</button>' +
        '</div>' +
        '<div class="plEditFields">' +
          '<label class="form-label small mb-1" for="plEditName">Name</label>' +
          '<input type="text" id="plEditName" class="form-control mb-2" maxlength="60" placeholder="Playlist name">' +
          '<label class="form-label small mb-1" for="plEditDesc">Description</label>' +
          '<textarea id="plEditDesc" class="form-control" rows="4" maxlength="300" placeholder="Add an optional description"></textarea>' +
        '</div>' +
      '</div>' +
      '<div class="plDialogButtons"><button type="button" class="btn btn-outline-secondary btn-pill" data-dismiss>Cancel</button>' +
      '<button type="submit" class="btn btn-success btn-pill">Create</button></div>',
      function (name, form) {
        var file = pending;
        createPlaylist(name, songs, form.find("textarea").val()).then(function (p) {
          if (!p) return;
          if (file) uploadImage(p.id, file);
          else renderBar();
          if (onCreated) onCreated(p);
        });
      }
    );
    dialog.addClass("plDialogWide");
    setPreview(null);
    var picker = $('<input type="file" accept="image/jpeg,image/png,image/gif,image/webp" hidden>').appendTo(dialog.find(".plDialog"));
    picker.on("change", function () { if (this.files && this.files[0]) setPreview(this.files[0]); });
    dialog.find("[data-image=choose]").on("click", function () { picker.val("").trigger("click"); });
    dialog.find("[data-image=remove]").on("click", function () { setPreview(null); });
  }

  function addMenu(anchor) {
    openMenu(anchor, [
      { label: "New playlist…", icon: "plus", run: function () {
        newPlaylist([], function (p) { showPlaylist(p.id); });
      } },
      { label: "Import .m3u…", icon: "upload", run: function () { fileInput.val("").trigger("click"); } },
    ]);
  }

  var fileInput = $('<input type="file" accept=".m3u,.m3u8,audio/x-mpegurl" hidden>').appendTo(document.body);
  fileInput.on("change", function () {
    if (this.files && this.files[0]) importFile(this.files[0]);
  });

  /* ──────────────────────────────── Dialogs ──────────────────────────────── */
  var dialog = $('<div class="plDialogBackdrop" style="display:none;"><form class="plDialog" role="dialog" aria-modal="true" aria-labelledby="plDialogTitle"></form></div>').appendTo(document.body);
  dialog[0].addEventListener("error", hideBrokenCover, true);
  var dialogSubmit = null;
  var dialogReturnFocus = null;

  function openDialog(html, onSubmit) {
    dialogReturnFocus = document.activeElement;
    dialogSubmit = onSubmit;
    dialog.removeClass("plDialogWide");
    dialog.find(".plDialog").html(html);
    dialog.show();
    var input = dialog.find("input[type=text], textarea").first();
    (input.length ? input : dialog.find("button[type=submit]")).trigger("focus").trigger("select");
  }
  function closeDialog() {
    if (!dialog.is(":visible")) return;
    dialog.hide();
    dialogSubmit = null;
    if (dialogReturnFocus && document.body.contains(dialogReturnFocus)) dialogReturnFocus.focus({ preventScroll: true });
  }
  dialog.on("submit", ".plDialog", function (e) {
    e.preventDefault();
    var form = $(this);
    var text = dialog.find("input[type=text]");
    var value = text.val();
    var submit = dialogSubmit;
    if (text.length && !String(value).trim()) {
      text.trigger("focus");
      return;
    }
    closeDialog();
    if (submit) submit(String(value || "").trim(), form);
  });
  dialog.on("click", "[data-dismiss]", closeDialog);
  dialog.on("mousedown", function (e) { if (e.target === this) closeDialog(); });

  function confirmDialog(title, text, confirmLabel, onConfirm) {
    openDialog(
      '<h3 id="plDialogTitle">' + esc(title) + '</h3><p class="text-secondary">' + esc(text) + '</p>' +
      '<div class="plDialogButtons"><button type="button" class="btn btn-outline-secondary btn-pill" data-dismiss>Cancel</button>' +
      '<button type="submit" class="btn btn-danger btn-pill">' + esc(confirmLabel) + '</button></div>',
      function () { onConfirm(); }
    );
  }

  /* ──────────────────── Drag to reorder / onto a playlist ─────────────────── */
  // Every song row can be dragged onto a playlist in #playlistBar. Dropping
  // between rows reorders, but only in an unsorted, unfiltered playlist view.
  // Others' playlists are read-only unless shared with "others can edit".
  function canEditList(p) {
    return !!p && p.can_edit !== false;
  }
  function reorderEnabled() {
    if (view.type !== "playlist" || !cache[view.id] || !canEditList(cache[view.id])) return false;
    var opts = playlistTable.bootstrapTable("getOptions");
    return !opts.sortName && !opts.searchText;
  }

  function setupRows() {
    var rows = $("#playlistTable > tbody > tr[data-uniqueid]");
    rows.attr("draggable", "true").toggleClass("plDraggable", reorderEnabled());
    // keep the playing row highlighted after re-renders
    if (window.currentIndex !== undefined) {
      rows.filter("[data-uniqueid='" + window.currentIndex + "']").addClass(activeClass).children().addClass(activeClass);
    }
  }

  // Rows: drag onto a playlist pill (any view) or between rows (reorder).
  dnd.sortable("#playlistTable", "tbody > tr[draggable=true]", {
    song: function (tr) { return $(tr).attr("data-uniqueid"); },
    canDrop: reorderEnabled,
    onDrop: function (from, to, after) {
      if (!reorderEnabled()) return;
      var p = cache[view.id];
      var moving = songOf($(from).attr("data-uniqueid")), target = songOf($(to).attr("data-uniqueid"));
      var songs = p.songs.filter(function (s) { return s !== moving; });
      var at = songs.indexOf(target);
      if (at === -1 || !moving) return;
      songs.splice(after ? at + 1 : at, 0, moving);
      reorder(p.id, songs);
    }
  });
  $("#playlistTable").on("post-body.bs.table", setupRows);

  // Playlist pills (and "New playlist") accept songs dragged from the table
  // or the queue panel.
  dnd.songDropTarget("#playlistBar", '.plPill[data-view]:not([data-view="library"]), .plPill.plAdd', function (id, el) {
    var song = songOf(id);
    if (!song) return;
    if ($(el).hasClass("plAdd")) {
      newPlaylist([song]);
    } else {
      addSongs(String($(el).data("view")), [song]);
    }
  });

  // Drag playlist pills to reorder them (Favorites stays first).
  dnd.sortable("#playlistBar", ".plPill[draggable=true]", {
    text: function (el) { return $(el).text(); },
    horizontal: function () { return $("#playlistBar").css("flex-direction") !== "column"; },
    onDrop: function (from, to, after) {
      movePlaylist(String($(from).data("view")), String($(to).data("view")), after);
    }
  });
  $("#playlistBar")
    .on("contextmenu", '.plPill[data-view]:not([data-view="library"])', function (e) {
      e.preventDefault();
      playlistMenu(this, String($(this).data("view")), { x: e.clientX, y: e.clientY });
    });

  /* ───────────────────────────── Event wiring ────────────────────────────── */
  $("#playlistBar").on("click", ".plPill", function () {
    var target = $(this).data("view");
    if ($(this).data("action") === "add-menu") return addMenu(this);
    if (target === "library") showLibrary(); else showPlaylist(String(target));
  });

  $("#playlistHeader").on("click", "[data-action]", function () {
    var action = $(this).data("action");
    if (!cache[view.id]) return;
    if (action === "play") playPlaylist(view.id, false);
    if (action === "shuffle") playPlaylist(view.id, true);
    if (action === "queue-all") queueSong(idsFor(cache[view.id].songs));
    if (action === "playlist-menu") playlistMenu(this);
    if (action === "edit" && canEditList(cache[view.id])) editDetails(view.id);
  });

  $(".playerFavBtn").on("click", function () {
    toggleFavorite(songName);
  });

  /* ───────────────────────────── Public API ──────────────────────────────── */
  window.playlistsUI = {
    currentView: function () { return { type: view.type, id: view.id }; },
    // Song ids of a playlist in order, or null if it isn't loaded yet.
    orderFor: function (id) {
      if (!cache[id]) {
        loadPlaylist(id);
        return null;
      }
      return idsFor(cache[id].songs);
    },
    isFavorite: function (song) { return favorites.has(song); },
    toggleFavorite: toggleFavorite,
    // The "Add to playlist" menu for one song, under anchor (the song info card uses it).
    addToPlaylistMenu: function (anchor, song) {
      openMenu(anchor, addToPlaylistItems([song]), { label: "Add to playlist" });
    },
    showPlaylist: function (id) {
      if (id === FAVORITES || listById(id)) showPlaylist(id);
    },
    // Show the whole library filtered by a search (the song info card's similar artists).
    searchLibrary: function (q) {
      if (view.type !== "library") showLibrary();
      playlistTable.bootstrapTable("resetSearch", q);
    },
    songOf: songOf,
    songChanged: updatePlayerHeart,
    // Display name of a playlist, or null if it's unknown.
    nameOf: function (id) {
      var p = listById(id) || cache[id];
      return p ? p.name : null;
    },
    // The song menu for any element showing a song (the queue panel uses it).
    songMenu: function (anchor, id, at) {
      if (!songOf(id)) return;
      openMenu(anchor, songMenuItems(id), { at: at || null, label: "Actions for " + songTitle(id) });
    },
    cellClicked: function (field, id, $cell, e) {
      if (field === "favorite") toggleFavorite(songOf(id));
      if (field === "more") rowMenu($cell.find(".moreBtn")[0] || $cell[0], id);
    },
  };

  /* ──────────────────────────────── Startup ──────────────────────────────── */
  $(function () {
    renderBar();
    var saved = null;
    try { saved = localStorage.getItem(VIEW_KEY); } catch (e) {}
    $.when(loadLists(), loadPlaylist(FAVORITES)).always(function () {
      if (saved && saved !== "library" && listById(saved)) {
        showPlaylist(saved);
      }
    });
  });
})();
