/* ──────────────────────────────────────────────────────────────────────────
 * Accounts: sign out, Settings → Account (name, password, sharing) and
 * Settings → Users (admins). Also sends the page to the sign-in screen when
 * the session runs out (any API call answering 401).
 * Uses globals from js.php: apiURL, csrfToken, showToast, currentUser, isAdminUser.
 * ────────────────────────────────────────────────────────────────────────── */
(function () {
  "use strict";

  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }
  function post(action, data) {
    return $.ajax({ url: apiURL, type: "POST", dataType: "json", data: $.extend({ action: action, csrf_token: csrfToken }, data || {}) });
  }

  // Signed out elsewhere, or the session expired: go sign in again.
  $(document).ajaxError(function (e, xhr) {
    if (xhr && xhr.status === 401) window.location.href = "login.php";
  });

  /* ───────────────────────────── Sign out ────────────────────────────────── */
  $(document).on("click", ".logoutBtn", function (e) {
    e.preventDefault();
    post("logout").then(function (res) {
      window.location.href = res && res.data && res.data.login_required === false ? "index.php" : "login.php";
    }, function () {
      showToast("Could not reach the server.", "danger");
    });
  });

  /* ──────────────────────── Open a settings section ──────────────────────── */
  $(document).on("click", ".openSettingsSection", function (e) {
    e.preventDefault();
    var section = $(this).data("section");
    var modal = $("#configModal");
    var go = function () {
      var target = document.getElementById(section);
      if (target) target.scrollIntoView({ block: "start" });
    };
    if (modal.hasClass("show")) { go(); return; }
    modal.one("shown.bs.modal", go);
    (window.bootstrap || window.tabler).Modal.getOrCreateInstance(modal[0]).show(); // Tabler: no global bootstrap
  });

  /* ─────────────────────────────── Account ───────────────────────────────── */
  var card = $(".accountCard");
  if (card.length) {
    var nameTimer = null;
    card.find(".accountDisplayName").on("input", function () {
      var input = $(this);
      clearTimeout(nameTimer);
      nameTimer = setTimeout(function () {
        var name = input.val().trim();
        if (!name) return;
        post("accountUpdate", { display_name: name }).then(function (res) {
          if (res.status !== "success") { showToast(res.response, "danger"); return; }
          var shown = res.data.user.display_name;
          $(".userMenuName").text(shown);
          $(".userAvatar").text(shown.charAt(0).toUpperCase());
          if (window.currentUser) currentUser.display_name = shown;
          input.closest(".settingRow").addClass("justSaved");
          setTimeout(function () { input.closest(".settingRow").removeClass("justSaved"); }, 1500);
        });
      }, 600);
    });

    card.find(".accountSharePlaying").on("change", function () {
      var on = this.checked;
      post("accountUpdate", { share_playing: on ? 1 : 0 }).then(function (res) {
        if (res.status !== "success") { showToast(res.response, "danger"); return; }
        if (window.currentUser) currentUser.share_playing = on;
        showToast(on ? "Others can now see what you're playing." : "What you play is private now.", "success");
        if (window.activityPanel) activityPanel.sharingChanged(on);
      });
    });

    card.find(".accountPassword").on("submit", function (e) {
      e.preventDefault();
      var form = this;
      post("accountUpdate", { current_password: form.current_password.value, new_password: form.new_password.value }).then(function (res) {
        showToast(res.response, res.status === "success" ? "success" : "danger");
        if (res.status === "success") form.reset();
      });
    });
  }

  /* ──────────────────────────────── Users ────────────────────────────────── */
  var users = $(".usersCard");
  if (users.length && window.isAdminUser) {
    var me = null;

    function roleSelect(u) {
      return '<select class="form-select form-select-sm userRole" aria-label="Role of ' + esc(u.username) + '">' +
        '<option value="user"' + (u.role === "user" ? " selected" : "") + '>User</option>' +
        '<option value="admin"' + (u.role === "admin" ? " selected" : "") + '>Admin</option></select>';
    }
    function when(ts) {
      return ts ? new Date(ts * 1000).toLocaleDateString() : "never";
    }
    function render(list) {
      if (!list.length) {
        users.find(".usersList").html('<div class="text-secondary small p-2">No accounts yet. Add the first one below; it becomes an admin.</div>');
        return;
      }
      var html = list.map(function (u) {
        var self = u.id === me;
        return '<div class="userRow" data-id="' + u.id + '">' +
          '<span class="userAvatar" aria-hidden="true">' + esc(u.display_name.charAt(0).toUpperCase()) + '</span>' +
          '<div class="userInfo"><div class="settingName">' + esc(u.display_name) + (self ? ' <span class="badge bg-green-lt">you</span>' : '') + '</div>' +
          '<div class="settingDesc">' + esc(u.username) + ' · last sign-in ' + esc(when(u.last_login)) + '</div></div>' +
          '<div class="userActions">' + roleSelect(u) +
          '<button type="button" class="btn btn-sm btn-ghost-secondary btn-icon userResetPw" title="Set a new password" aria-label="Set a new password for ' + esc(u.username) + '">' + lucideIcon("key-round") + '</button>' +
          (self ? '' : '<button type="button" class="btn btn-sm btn-ghost-danger btn-icon userDelete" title="Delete user" aria-label="Delete ' + esc(u.username) + '">' + lucideIcon("trash-2") + '</button>') +
          '</div></div>';
      }).join("");
      users.find(".usersList").html(html);
    }
    function load() {
      $.getJSON(apiURL, { action: "usersList" }).then(function (res) {
        if (res.status !== "success") { users.find(".usersList").html('<div class="text-danger small p-2">' + esc(res.response) + '</div>'); return; }
        me = res.data.me;
        render(res.data.users);
      });
    }
    function find(el) {
      var row = $(el).closest(".userRow");
      return { id: row.data("id"), name: row.find(".settingDesc").text().split(" · ")[0] };
    }

    $("#configModal").on("show.bs.modal", load);

    users
      .on("change", ".userRole", function () {
        var sel = $(this), u = find(this);
        post("usersUpdate", { id: u.id, role: sel.val() }).then(function (res) {
          showToast(res.response, res.status === "success" ? "success" : "danger");
          load();
        });
      })
      .on("click", ".userResetPw", function () {
        var u = find(this);
        var pw = window.prompt("New password for " + u.name + " (at least 6 characters):");
        if (!pw) return;
        post("usersUpdate", { id: u.id, password: pw }).then(function (res) {
          showToast(res.response, res.status === "success" ? "success" : "danger");
        });
      })
      .on("click", ".userDelete", function () {
        var u = find(this);
        if (!window.confirm("Delete " + u.name + "? Their private playlists and favorites are deleted; playlists they shared become yours.")) return;
        post("usersDelete", { id: u.id }).then(function (res) {
          showToast(res.response, res.status === "success" ? "success" : "danger");
          load();
        });
      })
      .on("submit", ".usersAdd", function (e) {
        e.preventDefault();
        var form = this;
        var hadNone = !me;
        post("usersCreate", $(form).serializeArray().reduce(function (o, f) { o[f.name] = f.value; return o; }, {})).then(function (res) {
          showToast(res.response, res.status === "success" ? "success" : "danger");
          if (res.status !== "success") return;
          form.reset();
          // The first account: sign in with it to use it (login may still be off).
          if (hadNone && currentUser && currentUser.id === 0) {
            showToast("Account created. Sign in with it to keep your own playlists and settings.", "info");
          }
          load();
        });
      });
  }

  /* ─────────────── After setup: start "fetch metadata" if asked ─────────── */
  try {
    if (window.isAdminUser && sessionStorage.getItem("mp3web.startMetaJob") === "1") {
      sessionStorage.removeItem("mp3web.startMetaJob");
      post("metaJobStart", { mode: "missing" }).then(function (res) {
        if (res.status === "success") showToast("Looking up song info in the background (Settings → Song info).", "info");
      });
    }
  } catch (e) {}
})();
