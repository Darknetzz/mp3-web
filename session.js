/* ────────────────────────────────────────────────────────────────────────── */
/*                      Listening sessions ("listen together")                */
/* ────────────────────────────────────────────────────────────────────────── */
// Polls api.php?action=sessionState, makes local playback follow the shared
// state, and pushes local actions (via the hooks in js.php) when allowed.
(function () {
  "use strict";

  var POLL_MS        = 1500;
  var POLL_HIDDEN_MS = 5000;
  var DRIFT_SECS     = 1.0;   // listeners re-seek beyond this
  var HOST_DRIFT     = 1.5;   // host re-publishes its position beyond this
  var PUSH_DEBOUNCE  = 150;

  var view           = null;  // last session view from the server
  var appliedVersion = 0;
  var myVersion      = 0;     // last version this device wrote
  var applying       = false;
  var pollTimer      = null;
  var pushTimer      = null;
  var pushOverride   = {};
  var pushIfVersion  = null;
  var pushInFlight   = false;
  var clockOffset    = 0;
  var bestRtt        = Infinity;
  var lastHostBeat   = 0;

  // Invite links (?join=CODE) are read now, before js.php clears the query string.
  var inviteCode = null;
  try {
    var params = new URLSearchParams(window.location.search);
    var j = (params.get("join") || "").toLowerCase();
    if (/^[a-f0-9]{8}$/.test(j)) inviteCode = j;
  } catch (e) {}

  // QR code for the invite link (qrcode-generator, loaded in index.php).
  var renderedQrUrl = null;
  function renderQr(url) {
    if (url === renderedQrUrl) return;
    renderedQrUrl = url;
    var box = $(".sessionQr");
    if (typeof window.qrcode !== "function") {
      box.hide(); $(".sessionQrHint").hide();
      return;
    }
    try {
      var qr = window.qrcode(0, "M"); // type 0 = smallest version that fits
      qr.addData(url);
      qr.make();
      box.html(qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true, title: "Join " + url })).show();
      $(".sessionQrHint").show();
    } catch (e) {
      console.warn("Could not render the invite QR code", e);
      box.hide(); $(".sessionQrHint").hide();
    }
  }

  function esc(str) {
    return $("<div>").text(str == null ? "" : String(str)).html();
  }

  function serverNow() {
    return Date.now() + clockOffset;
  }

  function request(method, action, data) {
    var t0 = Date.now();
    var payload = $.extend({ action: action }, data || {});
    if (method === "POST") payload.csrf_token = csrfToken;
    return $.ajax({ url: apiURL, type: method, data: payload, dataType: "json", cache: false })
      .then(function (res) {
        var t1 = Date.now();
        if (res && res.data && res.data.server_ms) {
          var rtt = t1 - t0;
          // Keep the offset from the fastest round trip seen recently.
          if (rtt <= bestRtt * 1.5 || rtt < 100) {
            bestRtt = Math.min(bestRtt, rtt);
            clockOffset = res.data.server_ms - (t0 + t1) / 2;
          }
        }
        return res;
      });
  }

  /* ──────────────────────────────── Roles ─────────────────────────────────── */
  function isActive()   { return !!sessionCode; }
  function canControl() { return !view || !!view.can_control; }

  /* ──────────────────────────────── Speaker ───────────────────────────────── */
  // A guest can play the session on this device, or leave it on the host's
  // speaker and use this device as a remote. The local player keeps playing in
  // sync either way (so the progress bar, song end and lock-screen controls
  // keep working), it is just muted. Remembered per browser.
  var SPEAKER_KEY = "sessionSpeaker";
  var onHostSpeaker = false;
  try { onHostSpeaker = localStorage.getItem(SPEAKER_KEY) === "host"; } catch (e) {}
  var silenced = false;

  function setHostSpeaker(remote) {
    onHostSpeaker = !!remote;
    try { localStorage.setItem(SPEAKER_KEY, onHostSpeaker ? "host" : "here"); } catch (e) {}
    $("input[name=sessionSpeakerChoice][value=" + (onHostSpeaker ? "host" : "here") + "]").prop("checked", true);
    $("#sessionPlayHere").prop("checked", !onHostSpeaker);
  }

  // The host is the speaker, so it is never silenced (a guest who becomes host
  // starts playing out loud).
  function applySpeaker() {
    var silent = isActive() && !!view && !view.is_host && onHostSpeaker;
    if (silent === silenced) return;
    if (silenced && !silent && isActive() && view && view.is_host) {
      showToast("You're the host now, so the session plays on this device.", "info");
    }
    silenced = silent;
    player.setMuted(silent);
  }

  /* ─────────────────────────────── UI state ───────────────────────────────── */
  function renderUI() {
    var active = isActive() && view;
    $(".sessionLobby").toggle(!active);
    $(".sessionActive").toggle(!!active);
    $("body").toggleClass("session-readonly", !!active && !view.can_control);
    if (window.queuePanel) queuePanel.refresh(); // queue editing follows control rights
    applySpeaker();

    if (!active) {
      $(".sessionStatus").hide().empty();
      $(".sessionBadge").hide().text("");
      return;
    }

    var role = view.is_host ? "Host" : (view.can_control ? "Can control" : "Listener");
    var inviteUrl = window.location.origin + window.location.pathname + "?join=" + view.code;

    $(".sessionName").text(view.name);
    $(".sessionCodeText").text(view.code);
    $(".sessionInviteLink").val(inviteUrl);
    renderQr(inviteUrl);
    $(".sessionListening").text(view.listening);
    $(".sessionRoleBadge").text(role)
      .toggleClass("bg-green text-green-fg", view.is_host)
      .toggleClass("bg-secondary text-secondary-fg", !view.is_host);
    $(".sessionHostOnly").toggle(!!view.is_host);
    $(".sessionGuestOnly").toggle(!view.is_host);
    $("#sessionPlayHere").prop("checked", !onHostSpeaker);
    $("#sessionAllowAll").prop("checked", !!view.allow_all);
    $(".sessionBadge").text(view.listening).show();

    renderMembers(view.members || []);

    var status = "Session: " + esc(view.name) + " · " + view.listening + " listening · " + role;
    if (silenced) status += " · on the host&rsquo;s speaker";
    if (!$(".sessionStatus .tapToListen").length || !$(".sessionStatus").is(":visible")) {
      $(".sessionStatus").html(status).show();
    } else {
      $(".sessionStatus .sessionStatusText").html(status);
    }
  }

  function renderMembers(members) {
    var html = "";
    members.forEach(function (m) {
      html += '<div class="list-group-item d-flex align-items-center gap-2' + (m.active ? '' : ' text-muted') + '">' +
        '<span class="status-dot ' + (m.active ? 'status-dot-animated bg-green' : 'bg-secondary') + '"></span>' +
        '<span class="text-truncate flex-fill">' + esc(m.name) + (m.me ? ' <small class="text-muted">(you)</small>' : '') + '</span>' +
        (m.remote ? '<small class="text-muted text-nowrap">on host&rsquo;s speaker</small>' : '') +
        (m.host ? '<span class="badge bg-green text-green-fg">Host</span>' : '') +
        (m.active ? '' : '<small>away</small>') +
        '</div>';
    });
    $(".sessionMembers").html(html);
  }

  /* ──────────────────────────── Display name ──────────────────────────────── */
  var NAME_KEY = "displayName";

  function storedName() {
    try { return localStorage.getItem(NAME_KEY) || ""; } catch (e) { return ""; }
  }

  function saveDisplayName(name, quiet) {
    return request("POST", "setDisplayName", { name: name }).then(function (res) {
      if (res.status !== "success") {
        if (!quiet) showToast(res.response, "danger");
        return;
      }
      displayName = res.data.name;
      try { localStorage.setItem(NAME_KEY, displayName); } catch (e) {}
      $("#displayNameInput").val(displayName);
      if (!quiet) showToast(res.response, "success");
      if (res.data.session && isActive()) {
        view = res.data.session;
        renderUI();
      }
    });
  }

  /* ──────────────────────────── Session defaults ──────────────────────────── */
  // userPrefs comes from the server (js.php). A copy is kept in localStorage so
  // the prefs survive the PHP session expiring, like the display name.
  var PREFS_KEY = "sessionPrefs";
  var prefsTimer = null;

  function storedPrefs() {
    try { return JSON.parse(localStorage.getItem(PREFS_KEY) || "null"); } catch (e) { return null; }
  }

  function storePrefs() {
    try { localStorage.setItem(PREFS_KEY, JSON.stringify(userPrefs)); } catch (e) {}
  }

  function renderPrefs() {
    $("[data-pref]").each(function () {
      var el = $(this), v = userPrefs[el.data("pref")];
      if (el.is(":checkbox")) el.prop("checked", !!v);
      else if (!el.is(":focus")) el.val(v == null ? "" : v);
    });
  }

  function saveUserPrefs(prefs, quiet) {
    return request("POST", "setUserPrefs", { prefs: JSON.stringify(prefs) }).then(function (res) {
      if (res.status !== "success") {
        if (!quiet) showToast(res.response, "danger");
        return;
      }
      userPrefs = res.data.prefs;
      storePrefs();
      renderPrefs();
      fillCreateForm();
      if (!quiet) showToast(res.response, "success");
    });
  }

  function prefsDiffer(a, b) {
    for (var k in a) {
      if (Object.prototype.hasOwnProperty.call(b, k) && a[k] !== b[k]) return true;
    }
    return false;
  }

  // Prefill the create form from the defaults (the user can still change it).
  function fillCreateForm() {
    var form = $("#createSessionForm");
    if (form.is(":visible")) return; // don't clobber what's being typed
    form.find("[name=name]").val(userPrefs.session_name || "");
    form.find("[name=public]").prop("checked", !!userPrefs.session_public);
    form.find("[name=allow_all]").prop("checked", !!userPrefs.session_allow_all);
  }

  /* ─────────────────────────── Apply remote state ─────────────────────────── */
  function expectedPosition(st) {
    var pos = +st.position || 0;
    if (st.playing) pos += Math.max(0, serverNow() - st.updated_ms) / 1000;
    var d = player.getDuration();
    return d ? Math.min(pos, Math.max(0, d - 0.25)) : pos;
  }

  function applyQueue(st) {
    var ids = [];
    (st.queue || []).forEach(function (name) {
      var idx = findIndexByFilename(name);
      if (idx !== null) ids.push(idx);
    });
    queue = ids;
    queueChanged();
  }

  function apply(v, force) {
    view = v;
    renderUI();
    if (!force && v.version <= appliedVersion) {
      syncDrift(v.state);
      return;
    }
    appliedVersion = v.version;
    var st = v.state;
    applying = true;
    try {
      if (!!loop !== !!st.loop) toggleLoop();
      if (!!shuffle !== !!st.shuffle) toggleShuffle();
      applyQueue(st);

      if (!st.song) {
        if (playing) player.pause();
        return;
      }
      var expected = expectedPosition(st);
      if (st.song !== songName) {
        var idx = findIndexByFilename(st.song);
        if (idx === null) {
          showToast("The session is playing a song that isn't in this library: " + esc(st.song), "warning");
          return;
        }
        playSong(idx, { autoplay: !!st.playing, startTime: expected });
        return;
      }
      if (Math.abs(player.getTime() - expected) > DRIFT_SECS) {
        player.seek(expected);
      }
      if (st.playing && !playing) player.play();
      if (!st.playing && playing) player.pause();
    } finally {
      applying = false;
    }
  }

  // Runs on every poll: keep listeners in step, and let the host re-publish its
  // real position if it drifted (e.g. it buffered).
  function syncDrift(st) {
    if (!st || !st.song || st.song !== songName || pushInFlight || pushTimer) return;
    var expected = expectedPosition(st);
    var diff = player.getTime() - expected;
    if (view.is_host) {
      if (st.playing && playing && Math.abs(diff) > HOST_DRIFT && Date.now() - lastHostBeat > 5000) {
        lastHostBeat = Date.now();
        api.localChange();
      }
      return;
    }
    if (myVersion === view.version) return; // I wrote this state; I'm the reference
    if (st.playing && Math.abs(diff) > DRIFT_SECS) {
      applying = true;
      try { player.seek(expected); } finally { applying = false; }
    }
    if (st.playing && !playing) {
      applying = true;
      try { player.play(); } finally { applying = false; }
    }
  }

  /* ──────────────────────────────── Polling ───────────────────────────────── */
  function schedulePoll(delay) {
    clearTimeout(pollTimer);
    if (!isActive()) return;
    pollTimer = setTimeout(poll, delay != null ? delay : (document.hidden ? POLL_HIDDEN_MS : POLL_MS));
  }

  function poll() {
    if (!isActive()) return;
    request("GET", "sessionState").then(function (res) {
      if (!isActive()) return;
      if (res.status === "success") {
        apply(res.data, false);
      } else if (res.data && res.data.in_session === false) {
        exitSession("The session has ended.");
        return;
      }
      schedulePoll();
    }, function () {
      schedulePoll(POLL_HIDDEN_MS); // network hiccup: back off a little
    });
  }

  /* ──────────────────────────────── Pushing ───────────────────────────────── */
  function currentState() {
    var names = [];
    (queue || []).forEach(function (id) {
      try { names.push(getSongNameByIndex(id)); } catch (e) {}
    });
    var st = {
      song:     songName || null,
      position: songName ? (player.getTime() || 0) : 0,
      playing:  !!songName && playing,
      queue:    names,
      loop:     !!loop,
      shuffle:  !!shuffle,
    };
    return $.extend(st, pushOverride);
  }

  function push() {
    pushTimer = null;
    if (!isActive()) return;
    var state = currentState();
    var data = { state: JSON.stringify(state) };
    if (pushIfVersion !== null) data.if_version = pushIfVersion;
    pushOverride = {};
    pushIfVersion = null;
    pushInFlight = true;
    request("POST", "sessionUpdate", data).then(function (res) {
      pushInFlight = false;
      if (!res || !res.data) return;
      if (res.data.in_session === false) {
        exitSession("The session has ended.");
        return;
      }
      if (res.status === "success") {
        myVersion = res.data.version;
        appliedVersion = res.data.version;
        view = res.data;
        renderUI();
      } else {
        // Denied or conflict: adopt what the session says.
        if (res.status === "error") showToast(res.response, "warning");
        apply(res.data, true);
      }
    }, function () {
      pushInFlight = false;
      showToast("Could not reach the server to sync the session.", "danger");
    });
  }

  /* ─────────────────────────── Enter / exit ───────────────────────────────── */
  function enterSession(v, opts) {
    opts = opts || {};
    sessionCode = v.code;
    appliedVersion = 0;
    myVersion = 0;
    view = v;
    renderUI();
    if (opts.publishLocal && songName) {
      // A new session takes over whatever the host is playing.
      api.localChange();
    } else {
      apply(v, true);
    }
    schedulePoll();
  }

  function exitSession(message) {
    clearTimeout(pollTimer);
    clearTimeout(pushTimer);
    pushTimer = null;
    sessionCode = null;
    view = null;
    appliedVersion = 0;
    renderUI();
    if (message) showToast(message, "info");
  }

  /* ───────────────────────────── Public API ───────────────────────────────── */
  var api = {
    active: isActive,
    canControl: canControl,
    // True when a control action must be refused (listener without permission).
    blocks: function () {
      return isActive() && !applying && !canControl();
    },
    denied: function () {
      showToast("Only the host can control playback in this session.", "warning");
    },
    // Called by js.php after a local control action.
    localChange: function (override) {
      if (!isActive() || applying || !canControl()) return;
      $.extend(pushOverride, override || {});
      clearTimeout(pushTimer);
      pushTimer = setTimeout(push, PUSH_DEBOUNCE);
    },
    // The current song finished. One controlling device advances; the server
    // rejects the others (if_version), and they adopt the winner's state.
    songEnded: function () {
      if (!canControl()) return; // listeners wait for the next state
      var baseVersion = view ? view.version : null;
      if (loop && window.currentIndex !== undefined) {
        playSong(window.currentIndex);
      } else {
        nextSong();
      }
      pushIfVersion = baseVersion;
    },
    // Autoplay was blocked (e.g. after a refresh): offer a button.
    playBlocked: function () {
      if (!isActive()) return;
      var status = $(".sessionStatus");
      if (status.find(".tapToListen").length) return;
      var text = status.html();
      status.html(
        '<span class="sessionStatusText">' + text + '</span> ' +
        '<a href="javascript:void(0);" class="tapToListen link-success fw-bold ms-2">▶ Tap to start listening</a>'
      ).show();
    },
  };
  window.sessionSync = api;
  api.join = function (code) { join(code); }; // the Activity panel's "Listen along"

  /* ───────────────────────────── Modal wiring ─────────────────────────────── */
  function loadPublicSessions() {
    var list = $(".sessionPublicList");
    list.html('<div class="text-muted small">Loading public sessions…</div>');
    request("GET", "listSessions").then(function (res) {
      var sessions = (res && res.data && res.data.sessions) || [];
      if (!sessions.length) {
        list.html('<div class="text-muted small">No public sessions right now.</div>');
        return;
      }
      var html = '<div class="list-group">';
      sessions.forEach(function (s) {
        html += '<a href="javascript:void(0);" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center joinPublicBtn" data-code="' + esc(s.code) + '">' +
          '<span><strong>' + esc(s.name) + '</strong>' +
          (s.song ? '<br><small class="text-muted">' + esc(s.song) + '</small>' : '') + '</span>' +
          '<span class="badge bg-primary text-primary-fg">' + s.listening + ' listening</span></a>';
      });
      list.html(html + '</div>');
    }, function () {
      list.html('<div class="text-danger small">Could not load public sessions.</div>');
    });
  }

  function join(code) {
    request("POST", "joinSession", { sessionCode: code, remote: onHostSpeaker ? 1 : 0 }).then(function (res) {
      if (res.status !== "success") {
        showToast(res.response, "danger");
        return;
      }
      showToast(res.response, "success");
      enterSession(res.data);
    }, function () {
      showToast("Could not join the session.", "danger");
    });
  }

  $(function () {
    $("#displayNameInput").val(displayName);
    // With an account, the server keeps the name and defaults: nothing to restore.
    var hasAccount = !!(window.currentUser && currentUser.id > 0);
    // The PHP session may be newer than this browser's saved name: restore it.
    var saved = storedName();
    if (!hasAccount && saved && saved !== displayName) saveDisplayName(saved, true);

    // Restore saved defaults if the server has forgotten them (PHP session expired).
    var savedPrefs = storedPrefs();
    if (!hasAccount && savedPrefs && prefsDiffer(savedPrefs, userPrefs)) {
      saveUserPrefs(savedPrefs, true);
    } else {
      storePrefs();
    }
    renderPrefs();
    fillCreateForm();

    $(document).on("change", "[data-pref]", function () {
      var el = $(this), key = el.data("pref"), patch = {};
      patch[key] = el.is(":checkbox") ? el.is(":checked") : el.val().trim();
      if (patch[key] === userPrefs[key]) return;
      $.extend(userPrefs, patch); // keep the create form in step right away
      clearTimeout(prefsTimer);
      prefsTimer = setTimeout(function () { saveUserPrefs(userPrefs, false); }, 300);
    });
    $(".cancelSessionBtn").on("click", function () {
      setTimeout(fillCreateForm, 0); // after js.php hides the form
    });

    $(".displayNameForm").on("submit", function (e) {
      e.preventDefault();
      var name = $("#displayNameInput").val().trim();
      if (!name || name === displayName) return;
      saveDisplayName(name, false);
    });

    $("#createSessionForm").on("submit", function (e) {
      e.preventDefault();
      var form = $(this);
      request("POST", "createSession", {
        name:      form.find("[name=name]").val(),
        public:    form.find("[name=public]").is(":checked") ? 1 : 0,
        allow_all: form.find("[name=allow_all]").is(":checked") ? 1 : 0,
      }).then(function (res) {
        if (res.status !== "success") {
          showToast(res.response, "danger");
          return;
        }
        showToast(res.response, "success");
        $(".sessionForm").hide();
        fillCreateForm();
        enterSession(res.data, { publishLocal: userPrefs.session_share_playback !== false });
      }, function () {
        showToast("Could not create the session.", "danger");
      });
    });

    $("#joinSessionForm").on("submit", function (e) {
      e.preventDefault();
      var code = $(this).find("[name=sessionCode]").val().trim().toLowerCase();
      join(code);
    });

    $(document).on("click", ".joinPublicBtn", function () {
      join($(this).data("code"));
    });

    setHostSpeaker(onHostSpeaker); // show the remembered choice
    $("input[name=sessionSpeakerChoice]").on("change", function () {
      setHostSpeaker($(this).val() === "host");
    });
    $("#sessionPlayHere").on("change", function () {
      setHostSpeaker(!$(this).is(":checked"));
      applySpeaker();
      renderUI();
      if (!isActive()) return;
      request("POST", "sessionSpeaker", { remote: onHostSpeaker ? "true" : "false" }).then(function (res) {
        showToast(res.response, res.status === "success" ? "success" : "danger");
        if (res.data && res.data.code) {
          view = res.data;
          renderUI();
        }
      });
    });

    $(".leaveSessionBtn").on("click", function () {
      request("POST", "leaveSession").always(function () {
        exitSession("You left the session.");
      });
    });

    $("#sessionAllowAll").on("change", function () {
      request("POST", "sessionSettings", { allow_all: $(this).is(":checked") ? "true" : "false" }).then(function (res) {
        showToast(res.response, res.status === "success" ? "success" : "danger");
        if (res.data && res.data.code) {
          view = res.data;
          renderUI();
        }
      });
    });

    $(".copyInviteBtn").on("click", function () {
      var input = $(".sessionInviteLink");
      var text = input.val();
      var done = function () { showToast("Invite link copied.", "success"); };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done, function () { input.trigger("select"); });
      } else {
        input.trigger("select");
        try { document.execCommand("copy"); done(); } catch (e) {}
      }
    });

    $("#sessionModal").on("show.bs.modal", function () {
      if (!isActive()) loadPublicSessions();
    });

    $(document).on("click", ".tapToListen", function () {
      var text = $(".sessionStatus .sessionStatusText").html();
      $(".sessionStatus").html(text);
      if (view) apply(view, true);
    });

    document.addEventListener("visibilitychange", function () {
      if (!document.hidden && isActive()) schedulePoll(0);
    });

    // Already in a session (page refresh): resume syncing.
    if (sessionCode) {
      renderUI();
      schedulePoll(0);
    } else if (inviteCode) {
      $('[data-bs-target="#sessionModal"]').first().trigger("click");
      $(".sessionForm").hide();
      $("#joinSessionForm").show().find("[name=sessionCode]").val(inviteCode).trigger("focus");
    } else {
      renderUI();
    }
  });
})();
