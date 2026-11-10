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
  var dragId    = null;

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
    }
    if (p.id === FAVORITES) {
      favorites = new Set(p.songs);
      refreshHearts();
    }
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
    lists.forEach(function (p) {
      var active = view.type === "playlist" && view.id === p.id;
      var icon = p.id === FAVORITES ? lucideIcon("heart", "filled") : lucideIcon("list-video");
      html += '<button type="button" class="plPill' + (active ? " active" : "") + (p.id === FAVORITES ? " plFav" : "") + '" data-view="' + esc(p.id) + '">' +
              icon + '<span>' + esc(p.name) + '</span><span class="plCount">' + p.count + '</span></button>';
    });
    html += '<button type="button" class="plPill plAdd" data-action="add-menu" aria-haspopup="menu" title="New or import playlist">' +
            lucideIcon('plus') + '<span>New playlist</span></button>';
    $("#playlistBar").html(html);
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
    var canDrag = p.count > 1;
    header.html(
      '<div class="plHeaderText">' +
        '<h3 class="plTitle">' + (isFav ? lucideIcon('heart', 'filled text-danger me-2') : "") + esc(p.name) + '</h3>' +
        '<div class="text-secondary small">' + count + missing + (canDrag ? ' · drag songs to reorder' : '') + '</div>' +
      '</div>' +
      '<div class="plHeaderActions">' +
        '<button type="button" class="btn btn-success btn-pill" data-action="play"' + (p.count ? "" : " disabled") + '>' + lucideIcon('play', 'filled me-1') + 'Play</button>' +
        '<button type="button" class="btn btn-outline-secondary btn-pill" data-action="shuffle"' + (p.count ? "" : " disabled") + '>' + lucideIcon('shuffle', 'me-1') + 'Shuffle</button>' +
        '<button type="button" class="btn btn-outline-secondary btn-pill" data-action="queue-all"' + (p.count ? "" : " disabled") + '>' + lucideIcon('list-ordered', 'me-1') + 'Add to queue</button>' +
        '<button type="button" class="btn btn-outline-secondary btn-icon btn-pill" data-action="playlist-menu" aria-haspopup="menu" title="More" aria-label="Playlist actions">' + lucideIcon('ellipsis') + '</button>' +
      '</div>'
    ).show();
  }

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

  function createPlaylist(name, songs) {
    return request("POST", "createPlaylist", { name: name, songs: songs || [] }).then(function (res) {
      if (res.status !== "success") return failed(res);
      showToast(res.response, "success");
      cache[res.data.id] = res.data;
      return loadLists().then(function () { return res.data; });
    });
  }

  function renamePlaylist(id, name) {
    return request("POST", "renamePlaylist", { id: id, name: name }).then(function (res) {
      if (res.status !== "success") return failed(res);
      storePlaylist(res.data);
      rerenderView();
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
      if (p.id === FAVORITES) return;
      items.push({ label: p.name, icon: "list-video", run: function () { addSongs(p.id, songs); } });
    });
    if (items.length) items.push("-");
    items.push({ label: "New playlist…", icon: "plus", run: function () {
      askName("New playlist", "", "Create", function (name) {
        createPlaylist(name, songs).then(function (p) { if (p) renderBar(); });
      });
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
      { label: "Download", icon: "download", run: function () { downloadSong(id); } },
      { label: "Copy song name", icon: "copy", run: function () { copyText(songTitle(id)); } }
    );

    if (canEdit && view.type === "playlist" && cache[view.id]) {
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
    if (canEdit) {
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

  function playlistMenu(anchor) {
    var p = cache[view.id];
    if (!p) return;
    var items = [
      { label: "Export .m3u", icon: "download", disabled: !p.count, run: function () {
        window.location.href = apiURL + "?action=exportPlaylist&id=" + encodeURIComponent(p.id);
      } },
    ];
    if (p.id !== FAVORITES) {
      items.unshift({ label: "Rename", icon: "pencil", run: function () {
        askName("Rename playlist", p.name, "Rename", function (name) { renamePlaylist(p.id, name); });
      } });
      items.push("-", { label: "Delete playlist", icon: "trash-2", danger: true, run: function () {
        confirmDialog("Delete “" + p.name + "”?", "The songs stay in your library. This can't be undone.", "Delete", function () {
          deletePlaylist(p.id);
        });
      } });
    }
    openMenu(anchor, items);
  }

  function addMenu(anchor) {
    openMenu(anchor, [
      { label: "New playlist…", icon: "plus", run: function () {
        askName("New playlist", "", "Create", function (name) {
          createPlaylist(name).then(function (p) { if (p) showPlaylist(p.id); });
        });
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
  var dialogSubmit = null;
  var dialogReturnFocus = null;

  function openDialog(html, onSubmit) {
    dialogReturnFocus = document.activeElement;
    dialogSubmit = onSubmit;
    dialog.find(".plDialog").html(html);
    dialog.show();
    var input = dialog.find("input").first();
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
    var value = dialog.find("input").val();
    var submit = dialogSubmit;
    if (dialog.find("input").length && !String(value).trim()) {
      dialog.find("input").trigger("focus");
      return;
    }
    closeDialog();
    if (submit) submit(String(value || "").trim());
  });
  dialog.on("click", "[data-dismiss]", closeDialog);
  dialog.on("mousedown", function (e) { if (e.target === this) closeDialog(); });

  function askName(title, value, confirmLabel, onSubmit) {
    openDialog(
      '<h3 id="plDialogTitle">' + esc(title) + '</h3>' +
      '<input type="text" class="form-control" maxlength="60" placeholder="Playlist name" value="' + esc(value) + '" aria-label="Playlist name">' +
      '<div class="plDialogButtons"><button type="button" class="btn btn-outline-secondary btn-pill" data-dismiss>Cancel</button>' +
      '<button type="submit" class="btn btn-success btn-pill">' + esc(confirmLabel) + '</button></div>',
      onSubmit
    );
  }
  function confirmDialog(title, text, confirmLabel, onConfirm) {
    openDialog(
      '<h3 id="plDialogTitle">' + esc(title) + '</h3><p class="text-secondary">' + esc(text) + '</p>' +
      '<div class="plDialogButtons"><button type="button" class="btn btn-outline-secondary btn-pill" data-dismiss>Cancel</button>' +
      '<button type="submit" class="btn btn-danger btn-pill">' + esc(confirmLabel) + '</button></div>',
      function () { onConfirm(); }
    );
  }

  /* ───────────────────────────── Drag to reorder ─────────────────────────── */
  function dragEnabled() {
    if (view.type !== "playlist" || !cache[view.id]) return false;
    var opts = playlistTable.bootstrapTable("getOptions");
    return !opts.sortName && !opts.searchText;
  }

  function setupRows() {
    var rows = $("#playlistTable > tbody > tr[data-uniqueid]");
    var enabled = dragEnabled();
    rows.attr("draggable", enabled ? "true" : null).toggleClass("plDraggable", enabled);
    // keep the playing row highlighted after re-renders
    if (window.currentIndex !== undefined) {
      rows.filter("[data-uniqueid='" + window.currentIndex + "']").addClass(activeClass).children().addClass(activeClass);
    }
  }

  $("#playlistTable")
    .on("dragstart", "tbody > tr[draggable=true]", function (e) {
      dragId = $(this).attr("data-uniqueid");
      e.originalEvent.dataTransfer.effectAllowed = "move";
      e.originalEvent.dataTransfer.setData("text/plain", dragId);
      $(this).addClass("plDragging");
    })
    .on("dragover", "tbody > tr[draggable=true]", function (e) {
      if (!dragId) return;
      e.preventDefault();
      var rect = this.getBoundingClientRect();
      var after = e.originalEvent.clientY > rect.top + rect.height / 2;
      $("#playlistTable tbody tr").removeClass("plDropBefore plDropAfter");
      $(this).addClass(after ? "plDropAfter" : "plDropBefore");
    })
    .on("drop", "tbody > tr[draggable=true]", function (e) {
      e.preventDefault();
      var targetId = $(this).attr("data-uniqueid");
      var after = $(this).hasClass("plDropAfter");
      $("#playlistTable tbody tr").removeClass("plDropBefore plDropAfter plDragging");
      if (!dragId || dragId === targetId || !cache[view.id]) return;
      var p = cache[view.id];
      var moving = songOf(dragId), target = songOf(targetId);
      var songs = p.songs.filter(function (s) { return s !== moving; });
      var at = songs.indexOf(target);
      if (at === -1 || !moving) return;
      songs.splice(after ? at + 1 : at, 0, moving);
      dragId = null;
      reorder(p.id, songs);
    })
    .on("dragend", "tbody > tr", function () {
      dragId = null;
      $("#playlistTable tbody tr").removeClass("plDropBefore plDropAfter plDragging");
    })
    .on("post-body.bs.table", setupRows);

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
    songOf: songOf,
    songChanged: updatePlayerHeart,
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
