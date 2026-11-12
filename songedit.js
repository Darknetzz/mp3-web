/* ──────────────────────────────────────────────────────────────────────────
 * Song editor and the "Fetch metadata" job (songedit.php, metajob.php).
 *
 * The editor (#songEditModal) is opened from the song info card, a song's ⋯
 * menu, or the "Songs without info" list in Settings → Song info. Save keeps
 * the edit in the app (an override); Save to file writes the MP3's tags.
 * A cover chosen here is saved right away, as an override.
 *
 * Uses globals from js.php: apiURL, csrfToken, showToast, findIndexByFilename,
 * getSongByIndex, coverUrl, coverChanged, updateTitle, songName, lucideIcon.
 * ────────────────────────────────────────────────────────────────────────── */
(function () {
  "use strict";

  var FIELDS = ["title", "artist", "album", "year", "genre", "track", "comment"];

  function esc(str) {
    return $("<div>").text(str == null ? "" : String(str)).html();
  }
  function post(action, data) {
    return $.ajax({ url: apiURL, type: "POST", dataType: "json", data: $.extend({ action: action, csrf_token: csrfToken }, data) });
  }
  function postForm(action, form) {
    form.append("action", action);
    form.append("csrf_token", csrfToken);
    return $.ajax({ url: apiURL, type: "POST", data: form, processData: false, contentType: false, dataType: "json" });
  }
  // Tabler bundles Bootstrap's Modal as tabler.Modal (no global bootstrap).
  function modalOf(el) {
    return (window.bootstrap || window.tabler).Modal.getOrCreateInstance(el);
  }
  function failed(res) {
    showToast((res && res.response) || "Something went wrong.", "danger");
  }

  /* ───────────────────────── Refresh a song everywhere ───────────────────── */
  // After an edit: the table row, the playbar, the media session and the card.
  function applyResult(d) {
    var song = d.file;
    var id = findIndexByFilename(song);
    var row = id !== null ? getSongByIndex(id) : null;
    if (row) {
      var update = { title: esc(d.title), artist: esc(d.artist), album: esc(d.album), year: esc(d.year) };
      if (row.cover) update.cover = String(row.cover).replace(/src="[^"]*"/, 'src="' + esc(d.cover) + '"');
      $.extend(row, update);
      try {
        $("#playlistTable").bootstrapTable("updateByUniqueId", { id: row.id, row: update });
      } catch (e) {}
    }
    coverChanged(song);
    if (song === window.songName) updateTitle(song);
    if (window.songInfoPanel && songInfoPanel.songEdited) songInfoPanel.songEdited(song);
  }

  /* ──────────────────────────────── Editor ──────────────────────────────── */
  var modalEl = document.getElementById("songEditModal");
  if (!modalEl) return;
  var modal = $(modalEl);
  var form = modal.find(".songEditForm");
  var current = null;   // song file being edited
  var info = null;      // songEditInfo result
  var busy = false;

  function setBusy(on) {
    busy = on;
    modal.find(".modal-footer button, .songEditCoverButtons button").prop("disabled", on);
    if (!on) updateButtons();
  }

  function renderCover(edit) {
    var img = modal.find(".songEditCover .coverImg");
    var src = coverUrl(current, "lg");
    img.prop("hidden", true).off("load error")
      .on("load", function () { img.prop("hidden", false); })
      .on("error", function () { img.prop("hidden", true); })
      .attr("src", src);
    var notes = {
      edited: "Your cover (kept in this app).",
      file:   "From the file.",
      online: "Found online (Cover Art Archive).",
      none:   "No cover yet.",
    };
    modal.find(".songEditCoverNote").text(notes[edit.coverSource] || "");
    modal.find("[data-cover=remove]").prop("hidden", !edit.coverEdited);
  }

  function fill(edit) {
    info = edit;
    FIELDS.forEach(function (f) {
      var input = form.find("[name=" + f + "]");
      input.val(edit.override[f] || "").attr("placeholder", edit.tagged[f] || "");
    });
    form.find("[name=mbid]").val(edit.override.mbid ? "https://musicbrainz.org/recording/" + edit.override.mbid : "");
    renderCover(edit);
    updateButtons();
  }

  function updateButtons() {
    if (!info) return;
    var toFile = modal.find(".songEditToFile");
    toFile.prop("disabled", busy || !!info.writeBlocker).attr("title", info.writeBlocker || "Write these into the song’s tags");
    var hasOverride = Object.keys(info.override).length > 0 || info.coverEdited;
    modal.find(".songEditReset").prop("hidden", !hasOverride);
  }

  function open(song) {
    if (!song) return;
    current = song;
    info = null;
    form[0].reset();
    modal.find(".songEditFile").text(song);
    modal.find(".songEditCover .coverImg").prop("hidden", true);
    setBusy(true);
    modalOf(modalEl).show();
    $.getJSON(apiURL, { action: "songEditInfo", file: song }).done(function (res) {
      if (song !== current) return;
      if (res && res.status === "success") fill(res.data);
      else failed(res);
    }).fail(function () { failed(); }).always(function () { if (song === current) setBusy(false); });
  }

  function fieldValues() {
    var out = { file: current, mbid: form.find("[name=mbid]").val() };
    FIELDS.forEach(function (f) { out[f] = form.find("[name=" + f + "]").val(); });
    return out;
  }

  // keepFields: a cover change; don't throw away what's typed in the form.
  function done(res, close, keepFields) {
    if (!res || res.status !== "success") return failed(res);
    applyResult(res.data);
    if (res.data.edit) {
      if (keepFields) {
        info = res.data.edit;
        renderCover(info);
        updateButtons();
      } else {
        fill(res.data.edit);
      }
    }
    if (close) modalOf(modalEl).hide();
    showToast(res.response, "success");
    if (window.metaJobPanel) metaJobPanel.refresh();
  }

  // Save: an override in the app
  form.on("submit", function (e) {
    e.preventDefault();
    if (busy || !info) return;
    setBusy(true);
    post("songEdit", fieldValues()).then(function (res) { done(res, true); }, function () { failed(); })
      .always(function () { setBusy(false); });
  });

  // Save to file: the song's own tags. Empty fields keep the file's value.
  modal.find(".songEditToFile").on("click", function () {
    if (busy || !info || info.writeBlocker) return;
    if (!window.confirm("Write this info into “" + current + "”? This changes the music file itself.")) return;
    var values = fieldValues();
    var data = new FormData();
    FIELDS.forEach(function (f) { data.append(f, values[f] !== "" ? values[f] : (info.tagged[f] || "")); });
    data.append("file", current);
    data.append("mbid", values.mbid);
    setBusy(true);
    postForm("songWriteTags", data).then(function (res) { done(res, true); }, function () { failed(); })
      .always(function () { setBusy(false); });
  });

  modal.find(".songEditReset").on("click", function () {
    if (busy || !info) return;
    if (!window.confirm("Forget your changes to this song’s info and cover in this app?")) return;
    setBusy(true);
    post("songEditReset", { file: current }).then(function (res) { done(res, false); }, function () { failed(); })
      .always(function () { setBusy(false); });
  });

  // Cover: chosen → saved right away as an override
  var coverInput = modal.find(".songEditCoverInput");
  modal.on("click", "[data-cover=choose]", function () { if (!busy) coverInput.trigger("click"); });
  coverInput.on("change", function () {
    var file = this.files && this.files[0];
    this.value = "";
    if (!file || !current) return;
    var data = new FormData();
    data.append("file", current);
    data.append("cover", file);
    setBusy(true);
    postForm("songCover", data).then(function (res) { done(res, false, true); }, function () { failed(); })
      .always(function () { setBusy(false); });
  });
  modal.on("click", "[data-cover=remove]", function () {
    if (busy || !current) return;
    setBusy(true);
    post("songCover", { file: current, remove: 1 }).then(function (res) { done(res, false, true); }, function () { failed(); })
      .always(function () { setBusy(false); });
  });

  // Fill empty fields from what MusicBrainz knows (looks the song up if needed)
  modal.find(".songEditFill").on("click", function () {
    if (busy || !current) return;
    var btn = $(this);
    var song = current;
    setBusy(true);
    $.ajax({ url: apiURL, data: { action: "songOnline", file: song }, dataType: "json", timeout: 90000 }).done(function (res) {
      if (song !== current) return;
      var o = res && res.data;
      if (!res || res.status !== "success") return failed(res);
      if (!o.found) return showToast("Not found on MusicBrainz. Paste the right song’s MusicBrainz link, then Save.", "warning");
      var from = {
        title:  o.recording.title,
        artist: o.artist.name,
        album:  o.albumOfficial ? o.album : "",
        year:   o.year,
        genre:  (o.genres || []).slice(0, 3).join(", "),
        track:  o.albumOfficial ? o.track : "",
      };
      var filled = 0;
      Object.keys(from).forEach(function (f) {
        var input = form.find("[name=" + f + "]");
        // Only where neither the field nor the file has a value
        if (from[f] && !input.val() && !input.attr("placeholder")) { input.val(from[f]); filled++; }
      });
      showToast(filled ? "Filled " + filled + " field" + (filled > 1 ? "s" : "") + " from MusicBrainz. Check them, then Save." : "Nothing to fill: the song already has that info.", filled ? "success" : "info");
    }).fail(function () { failed(); }).always(function () { setBusy(false); btn.trigger("blur"); });
  });

  modal.on("hidden.bs.modal", function () { current = null; info = null; });

  window.songEditor = { open: open };

  /* ──────────────────────────── Fetch metadata job ───────────────────────── */
  var panel = $(".metaJob");
  var pollTimer = null;
  var listAll = false;
  var lastStatus = null;

  function fmtCount(n, one, many) { return n + " " + (n === 1 ? one : many); }

  function renderJob(d) {
    lastStatus = d;
    var job = d.job || {};
    var alive = !!job.alive;
    var state = job.state || "idle";
    panel.find(".metaJobStart").prop("hidden", alive).prop("disabled", !d.enabled);
    panel.find(".metaJobStop").prop("hidden", !alive).prop("disabled", state === "stopping");
    panel.find(".metaJobMode").prop("disabled", alive);
    if (job.mode && alive) panel.find(".metaJobMode").val(job.mode);

    var hasRun = state !== "idle" && job.total !== undefined;
    panel.find(".metaJobProgress").prop("hidden", !hasRun || (!alive && !job.total));
    if (hasRun) {
      var pct = job.total ? Math.round(100 * job.done / job.total) : 0;
      panel.find(".progress-bar").css("width", pct + "%").toggleClass("progress-bar-striped progress-bar-animated", alive);
      var parts = [job.done + " / " + job.total];
      if (job.found) parts.push(fmtCount(job.found, "found", "found"));
      if (job.notFound) parts.push(job.notFound + " not found");
      if (job.skipped) parts.push(job.skipped + " already known");
      if (job.errors) parts.push(fmtCount(job.errors, "error", "errors"));
      var label = { starting: "Starting…", running: "", stopping: "Stopping after this song…", done: "Finished", stopped: "Stopped", error: "" }[state] || "";
      panel.find(".metaJobStats").text((label ? label + " · " : "") + parts.join(" · "));
      panel.find(".metaJobCurrent").text(alive && job.current ? "Looking up " + job.current : "").prop("hidden", !(alive && job.current));
    }

    var msg = !d.enabled ? "Turn on Online song info above to fetch metadata." : (job.message || "");
    panel.find(".metaJobMessage").text(msg).prop("hidden", !msg).toggleClass("text-danger", state === "error");

    var log = job.log || [];
    panel.find(".metaJobLogWrap").prop("hidden", !log.length);
    panel.find(".metaJobLog").text(log.slice().reverse().map(function (l) {
      if (!Array.isArray(l)) return String(l);
      return new Date(l[0] * 1000).toLocaleTimeString() + "  " + l[1];
    }).join("\n"));

    renderUnknown(d.library || { notFound: [], unchecked: 0 });
    if (alive) schedulePoll(); else stopPoll();
  }

  function renderUnknown(lib) {
    var list = panel.find(".metaJobUnknownList");
    var items = lib.notFound || [];
    var html = "";
    if (lib.unchecked) {
      html += '<div class="small text-secondary mb-2">' + fmtCount(lib.unchecked, "song hasn’t", "songs haven’t") + " been looked up yet.</div>";
    }
    if (!items.length) {
      html += '<div class="small text-secondary">' + (lib.unchecked ? "" : "Every song looked up so far was found.") + '</div>';
      list.html(html);
      return;
    }
    var shown = listAll ? items : items.slice(0, 8);
    html += '<ul class="metaJobSongs">';
    shown.forEach(function (s) {
      var name = s.title ? s.title + (s.artist ? " – " + s.artist : "") : s.file;
      html += '<li><span class="text-truncate" title="' + esc(s.file) + '">' + esc(name) +
        (s.edited ? ' <span class="siFrom">edited</span>' : "") + '</span>' +
        '<button type="button" class="btn btn-sm btn-ghost-secondary btn-pill" data-edit="' + esc(s.file) + '">' + lucideIcon("pencil") + ' Edit</button></li>';
    });
    html += '</ul>';
    if (items.length > shown.length) {
      html += '<button type="button" class="btn btn-sm btn-link px-0 metaJobMore">Show all ' + items.length + '</button>';
    }
    list.html(html);
  }

  function load() {
    return $.getJSON(apiURL, { action: "metaJobStatus" }).done(function (res) {
      if (res && res.status === "success") renderJob(res.data);
    });
  }
  function schedulePoll() {
    stopPoll();
    pollTimer = setTimeout(function () {
      if ($("#configModal").hasClass("show")) load(); else stopPoll();
    }, 2000);
  }
  function stopPoll() {
    if (pollTimer) clearTimeout(pollTimer);
    pollTimer = null;
  }

  if (panel.length) {
    $("#configModal").on("shown.bs.modal", function () { load(); }).on("hidden.bs.modal", stopPoll);
    panel.on("click", ".metaJobStart", function () {
      var btn = $(this).prop("disabled", true);
      post("metaJobStart", { mode: panel.find(".metaJobMode").val() }).then(function (res) {
        if (!res || res.status !== "success") failed(res);
        setTimeout(load, 800);
      }, function () { failed(); }).always(function () { btn.prop("disabled", false); });
    });
    panel.on("click", ".metaJobStop", function () {
      $(this).prop("disabled", true);
      post("metaJobStop", {}).then(function (res) {
        if (!res || res.status !== "success") failed(res);
        load();
      });
    });
    panel.on("click", ".metaJobMore", function () {
      listAll = true;
      if (lastStatus) renderUnknown(lastStatus.library);
    });
    panel.on("click", "[data-edit]", function () {
      var song = String($(this).data("edit"));
      // One dialog at a time: settings close, the editor opens
      $("#configModal").one("hidden.bs.modal", function () { open(song); });
      modalOf(document.getElementById("configModal")).hide();
    });
  }

  window.metaJobPanel = { refresh: function () { if (panel.length && $("#configModal").hasClass("show")) load(); } };
})();
