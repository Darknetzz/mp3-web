/* ──────────────────────────────────────────────────────────────────────────
 * Shared drag and drop (native HTML5 DnD) for every list that can be dragged:
 * song table rows, queue panel items and the playlist pills in the sidebar.
 *
 * dnd.sortable(root, itemSelector, opts) makes the matching items draggable
 * (delegated, so re-rendered items keep working) and shows a drop line
 * before/after the item under the pointer. opts:
 *   song(el)         → song id when the item is a song: while it is dragged,
 *                      dnd.song() returns it and body gets .plDraggingSong so
 *                      playlist pills can take it (see dnd.songDropTarget).
 *   text(el)         → text put on the clipboard-ish dataTransfer (Firefox
 *                      needs some data to start a drag); defaults to the song.
 *   canDrag(el)      → false to refuse the drag.
 *   canDrop(from,to) → false to refuse reordering onto `to`.
 *   horizontal()     → true when the items are laid out left to right.
 *   onStart(el) / onEnd() → around the drag (e.g. to hold re-renders).
 *   onDrop(from, to, after) → reorder: put `from` before/after `to`.
 *
 * dnd.songDropTarget(root, selector, onDrop(id, el)) makes elements accept
 * a song dragged from any sortable list.
 * ────────────────────────────────────────────────────────────────────────── */
window.dnd = (function () {
  "use strict";

  var DRAGGING = "plDragging", BEFORE = "plDropBefore", AFTER = "plDropAfter", TARGET = "plDropTarget";
  var songId = null;

  function dt(e) { return e.originalEvent.dataTransfer; }
  // The pointer moved off `el` itself (not just onto one of its children)
  function left(el, e) { return !el.contains(e.originalEvent.relatedTarget); }

  function sortable(root, sel, o) {
    o = o || {};
    var cur = null;
    root = $(root);

    function clearLine() { root.find("." + BEFORE + ", ." + AFTER).removeClass(BEFORE + " " + AFTER); }
    function end() {
      if (!cur) return;
      $(cur).removeClass(DRAGGING);
      cur = null;
      clearLine();
      if (songId !== null) {
        songId = null;
        $("body").removeClass("plDraggingSong");
        $("." + TARGET).removeClass(TARGET);
      }
      if (o.onEnd) o.onEnd();
    }

    root
      .on("dragstart", sel, function (e) {
        if (cur || (o.canDrag && !o.canDrag(this))) { e.preventDefault(); return; }
        cur = this;
        var id = o.song ? o.song(this) : null;
        if (id != null) {
          songId = String(id);
          $("body").addClass("plDraggingSong");
        }
        dt(e).effectAllowed = id != null ? "copyMove" : "move";
        dt(e).setData("text/plain", o.text ? o.text(this) : (songId || ""));
        $(this).addClass(DRAGGING);
        if (o.onStart) o.onStart(this);
      })
      .on("dragover", sel, function (e) {
        if (!cur || cur === this || !o.onDrop || (o.canDrop && !o.canDrop(cur, this))) return;
        e.preventDefault();
        dt(e).dropEffect = "move";
        var r = this.getBoundingClientRect(), ev = e.originalEvent;
        var after = o.horizontal && o.horizontal()
          ? ev.clientX > r.left + r.width / 2
          : ev.clientY > r.top + r.height / 2;
        root.find(sel).not(this).removeClass(BEFORE + " " + AFTER);
        $(this).toggleClass(AFTER, after).toggleClass(BEFORE, !after);
      })
      .on("dragleave", sel, function (e) {
        if (left(this, e)) $(this).removeClass(BEFORE + " " + AFTER);
      })
      .on("drop", sel, function (e) {
        if (!cur) return;
        e.preventDefault();
        var from = cur, to = this, after = $(this).hasClass(AFTER), ok = $(this).is("." + BEFORE + ", ." + AFTER);
        end();
        if (ok && from !== to) o.onDrop(from, to, after);
      })
      .on("dragend", sel, end);
  }

  function songDropTarget(root, sel, onDrop) {
    $(root)
      .on("dragover", sel, function (e) {
        if (songId === null) return;
        e.preventDefault();
        dt(e).dropEffect = "copy";
        $("." + TARGET).not(this).removeClass(TARGET);
        $(this).addClass(TARGET);
      })
      .on("dragleave", sel, function (e) {
        if (left(this, e)) $(this).removeClass(TARGET);
      })
      .on("drop", sel, function (e) {
        if (songId === null) return;
        e.preventDefault();
        var id = songId;
        $(this).removeClass(TARGET);
        onDrop(id, this);
        // dragend on the source cleans up the rest
      });
  }

  return {
    sortable: sortable,
    songDropTarget: songDropTarget,
    song: function () { return songId; }
  };
})();
