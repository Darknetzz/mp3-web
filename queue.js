/* ──────────────────────────────────────────────────────────────────────────
 * Queue panel: a collapsible side panel with what is playing now, the manual
 * queue (reorder by dragging the grip or with Alt+↑/↓, remove with ×) and the
 * rest of the play context ("Next from: …") in play order.
 *
 * Uses globals from js.php: queue, currentIndex, playContext, contextOrder,
 * shuffle, loop, playSong, getSongByIndex, getSongNameByIndex, songTitle,
 * coverUrl, sessionBlocked, sessionChanged, queueChanged, lucideIcon.
 * js.php calls queuePanel.refresh() (through queueChanged) whenever the queue,
 * the song or the play order changes, including from a listening session.
 * ────────────────────────────────────────────────────────────────────────── */
(function () {
  "use strict";

  var OPEN_KEY = "queuePanelOpen";
  var CONTEXT_LIMIT = 100; // songs listed under "Next from"
  var panel = $("#queuePanel");
  if (!panel.length) return;
  var listNow = $("#qpNow"), listQueue = $("#qpQueue"), listContext = $("#qpContext");
  var renderQueued = false;
  var drag = null;

  function esc(str) {
    return $("<div>").text(str == null ? "" : String(str)).html();
  }
  function isOpen() {
    return !panel.prop("hidden");
  }
  function canEdit() {
    return !(window.sessionSync && sessionSync.blocks());
  }

  /* ──────────────────────────────── Rendering ─────────────────────────────── */
  function contextName() {
    if (playContext.type === "playlist" && window.playlistsUI) {
      return playlistsUI.nameOf(playContext.id) || "Playlist";
    }
    return "All songs";
  }

  // The context's songs after the current one, in the order nextSong plays them.
  function upcomingFromContext() {
    var order = contextOrder();
    var pos = order.indexOf(String(currentIndex));
    if (pos === -1) return order;
    return order.slice(pos + 1).concat(order.slice(0, pos));
  }

  // kind: "now" | "queue" | "context"; pos: index in the queue for "queue"
  function itemHtml(id, kind, pos) {
    var row = getSongByIndex(id);
    if (!row) return "";
    var name = getSongNameByIndex(id);
    var editable = kind === "queue" && canEdit();
    return '<li class="qpItem' + (kind === "now" ? " qpNowItem" : "") + '" tabindex="0" data-id="' + esc(id) + '"' +
             (kind === "queue" ? ' data-pos="' + pos + '"' : "") + ' data-kind="' + kind + '">' +
           (editable ? '<span class="qpGrip" title="Drag to reorder (Alt+↑/↓)" aria-hidden="true">' + lucideIcon("grip-vertical") + '</span>' : "") +
           '<span class="coverThumb qpCover"><img class="coverImg" src="' + esc(coverUrl(name, "sm")) + '" alt="" loading="lazy" decoding="async" onerror="this.hidden=true"></span>' +
           '<span class="qpName">' + esc(songTitle(id)) + '</span>' +
           '<span class="qpDuration text-secondary">' + esc($("<div>").html(String(row.duration || "")).text().trim()) + '</span>' +
           (editable ? '<button type="button" class="btn btn-sm btn-ghost-secondary qpRemove" title="Remove from queue" aria-label="Remove ' + esc(songTitle(id)) + ' from the queue">' + lucideIcon("x") + '</button>' : "") +
           '</li>';
  }

  function render() {
    renderQueued = false;
    if (!isOpen()) return;
    if (drag) { renderQueued = true; return; } // after the drop
    var focused = $(document.activeElement).closest(".qpItem", panel[0]);
    var focusKey = focused.length ? { kind: focused.data("kind"), pos: focused.data("pos"), id: String(focused.data("id")) } : null;

    var hasSong = window.currentIndex !== undefined && getSongByIndex(currentIndex);
    listNow.html(hasSong ? itemHtml(String(currentIndex), "now") : '<li class="qpEmpty text-secondary">Nothing is playing.</li>');

    var qHtml = "";
    queue.forEach(function (id, i) { qHtml += itemHtml(String(id), "queue", i); });
    listQueue.html(qHtml || '<li class="qpEmpty text-secondary">Use “Play next” or “Add to queue” on a song to line it up here.</li>');
    panel.find(".qpClear").toggle(queue.length > 0 && canEdit());

    panel.find(".qpContextName").text(contextName());
    var hint = panel.find(".qpHint");
    var cHtml = "";
    if (shuffle) {
      hint.text("Shuffle is on: songs are picked at random from here.").prop("hidden", false);
    } else {
      hint.text(loop ? "Repeat is on: the current song plays again until you skip it." : "").prop("hidden", !loop);
      var ids = upcomingFromContext();
      ids.slice(0, CONTEXT_LIMIT).forEach(function (id) { cHtml += itemHtml(id, "context"); });
      if (ids.length > CONTEXT_LIMIT) {
        cHtml += '<li class="qpEmpty text-secondary">and ' + (ids.length - CONTEXT_LIMIT) + ' more</li>';
      }
      if (!ids.length) cHtml = '<li class="qpEmpty text-secondary">No more songs.</li>';
    }
    listContext.html(cHtml);

    if (focusKey) {
      var sel = focusKey.kind === "queue"
        ? '.qpItem[data-kind="queue"][data-pos="' + focusKey.pos + '"]'
        : '.qpItem[data-kind="' + focusKey.kind + '"][data-id="' + focusKey.id + '"]';
      var again = panel.find(sel).first();
      if (!again.length && focusKey.kind === "queue") again = listQueue.children(".qpItem").last();
      (again.length ? again : panel.find(".queuePanelClose")).trigger("focus");
    }
  }

  // Coalesce bursts of changes (a song change also re-renders the table).
  function refresh() {
    if (renderQueued || !isOpen()) return;
    renderQueued = true;
    setTimeout(render, 0);
  }

  /* ──────────────────────────────── Open/close ────────────────────────────── */
  function setOpen(on, opts) {
    if (on && window.lyricsPanel) lyricsPanel.close();
    panel.prop("hidden", !on);
    $("body").toggleClass("queue-open", on);
    $(".queueToggleBtn").toggleClass("active", on).attr("aria-expanded", on ? "true" : "false");
    if (!opts || opts.save !== false) {
      try { localStorage.setItem(OPEN_KEY, on ? "1" : "0"); } catch (e) {}
    }
    if (on) {
      renderQueued = false;
      render();
    }
  }
  function toggle() {
    var on = !isOpen();
    setOpen(on);
    if (on) panel.find(".queuePanelClose").trigger("focus");
  }

  /* ──────────────────────────────── Queue edits ───────────────────────────── */
  function commit() {
    sessionChanged();
    queueChanged();
  }
  function removeAt(pos) {
    if (sessionBlocked() || pos < 0 || pos >= queue.length) return;
    queue.splice(pos, 1);
    commit();
  }
  function moveItem(from, to) {
    if (sessionBlocked()) return;
    if (from === to || from < 0 || to < 0 || from >= queue.length || to >= queue.length) return;
    var id = queue.splice(from, 1)[0];
    queue.splice(to, 0, id);
    commit();
  }
  function clearQueue() {
    if (sessionBlocked() || !queue.length) return;
    queue.splice(0, queue.length);
    commit();
  }
  // Play a queued song now; it leaves the queue like it would when reached.
  function playQueued(pos) {
    if (sessionBlocked() || pos < 0 || pos >= queue.length) return;
    var id = queue.splice(pos, 1)[0];
    playSong(id);
  }
  function playItem(li) {
    var kind = li.data("kind");
    if (kind === "queue") playQueued(+li.data("pos"));
    else if (kind === "context") playSong(String(li.data("id")));
  }

  /* ─────────────────────────── Drag to reorder ───────────────────────────── */
  // Pointer events on the grip, so it works the same with a mouse and on touch.
  // Moves and the drop are tracked on the document: moving the row in the DOM
  // can drop pointer capture.
  function dragMove(e) {
    if (!drag || e.pointerId !== drag.pointerId) return;
    var y = e.clientY;
    var over = $(document.elementFromPoint(e.clientX, y)).closest("#qpQueue > .qpItem");
    if (over.length && over[0] !== drag.li[0]) {
      var r = over[0].getBoundingClientRect();
      if (y < r.top + r.height / 2) over.before(drag.li); else over.after(drag.li);
    }
    // Scroll the panel when dragging near its edges
    var body = panel.find(".queuePanelBody")[0];
    var b = body.getBoundingClientRect();
    if (y < b.top + 32) body.scrollTop -= 8;
    else if (y > b.bottom - 32) body.scrollTop += 8;
    e.preventDefault();
  }
  function dragEnd(e) {
    if (!drag || e.pointerId !== drag.pointerId) return;
    var d = drag;
    drag = null;
    document.removeEventListener("pointermove", dragMove);
    document.removeEventListener("pointerup", dragEnd);
    document.removeEventListener("pointercancel", dragEnd);
    d.li.removeClass("qpDragging");
    var to = listQueue.children(".qpItem").index(d.li);
    var same = d.snapshot.length === queue.length && d.snapshot.every(function (id, i) { return String(id) === String(queue[i]); });
    if (e.type === "pointerup" && same && to !== -1 && to !== d.from) {
      moveItem(d.from, to); // re-renders
    } else {
      render(); // put the list back (cancelled, or the queue changed meanwhile)
    }
  }
  listQueue.on("pointerdown", ".qpGrip", function (e) {
    if (e.button !== undefined && e.button !== 0) return;
    if (drag || !canEdit()) return;
    var li = $(this).closest(".qpItem");
    e.preventDefault();
    try { this.setPointerCapture(e.pointerId); } catch (err) {}
    drag = { li: li, from: +li.data("pos"), snapshot: queue.slice(), pointerId: e.pointerId };
    li.addClass("qpDragging");
    document.addEventListener("pointermove", dragMove);
    document.addEventListener("pointerup", dragEnd);
    document.addEventListener("pointercancel", dragEnd);
  });

  /* ──────────────────────────────── Events ───────────────────────────────── */
  $(".queueToggleBtn").on("click", toggle);
  panel.find(".queuePanelClose").on("click", function () {
    setOpen(false);
    $(".queueToggleBtn:visible").first().trigger("focus");
  });
  panel.find(".qpClear").on("click", clearQueue);

  panel.on("click", ".qpRemove", function (e) {
    e.stopPropagation();
    removeAt(+$(this).closest(".qpItem").data("pos"));
  });
  panel.on("click", ".qpItem", function (e) {
    if ($(e.target).closest(".qpGrip, .qpRemove").length) return;
    if ($(this).data("kind") === "now") return;
    playItem($(this));
  });
  panel.on("contextmenu", ".qpItem", function (e) {
    if (!window.playlistsUI) return;
    e.preventDefault();
    playlistsUI.songMenu(this, String($(this).data("id")), { x: e.clientX, y: e.clientY });
  });
  panel.on("keydown", ".qpItem", function (e) {
    var li = $(this);
    var kind = li.data("kind");
    var pos = +li.data("pos");
    if (e.altKey && kind === "queue" && (e.key === "ArrowUp" || e.key === "ArrowDown")) {
      e.preventDefault();
      e.stopPropagation();
      var to = pos + (e.key === "ArrowUp" ? -1 : 1);
      if (to < 0 || to >= queue.length) return;
      moveItem(pos, to);
      listQueue.children('.qpItem[data-pos="' + to + '"]').trigger("focus");
      return;
    }
    if (e.altKey || e.ctrlKey || e.metaKey) return;
    switch (e.key) {
      case "ArrowUp":
      case "ArrowDown": {
        var items = panel.find(".qpItem");
        var next = items.eq(items.index(this) + (e.key === "ArrowUp" ? -1 : 1));
        if (next.length && next[0] !== this) next.trigger("focus");
        e.preventDefault();
        e.stopPropagation(); // not the volume
        return;
      }
      case "Enter":
        if (kind !== "now") playItem(li);
        e.preventDefault();
        return;
      case "Delete":
      case "Backspace":
        if (kind === "queue") { e.preventDefault(); removeAt(pos); }
        return;
      case "ContextMenu":
        if (window.playlistsUI) { e.preventDefault(); playlistsUI.songMenu(this, String(li.data("id"))); }
        return;
    }
    if (e.key === "F10" && e.shiftKey && window.playlistsUI) {
      e.preventDefault();
      playlistsUI.songMenu(this, String(li.data("id")));
    }
  });
  panel.on("keydown", function (e) {
    if (e.key === "Escape" && !$(".popMenu:visible").length) {
      setOpen(false);
      $(".queueToggleBtn:visible").first().trigger("focus");
    }
  });

  // The "Next from" list follows the table when the context is the shown view
  // (sorting, searching, switching playlists).
  $("#playlistTable").on("post-body.bs.table", refresh);

  /* ──────────────────────────────── Startup ──────────────────────────────── */
  window.queuePanel = { refresh: refresh, toggle: toggle, open: function () { setOpen(true); }, close: function () { if (isOpen()) setOpen(false); } };
  // After the song table is set up (the library is read from it).
  $(function () {
    var saved = false;
    try { saved = localStorage.getItem(OPEN_KEY) === "1"; } catch (e) {}
    if (saved) setOpen(true, { save: false });
  });
})();
