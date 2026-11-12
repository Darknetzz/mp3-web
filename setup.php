<?php
/* ──────────────────────────────────────────────────────────────────────────
 * First-run setup: shown until the first account exists (or the wizard was
 * finished with login turned off). Posts to api.php?action=setupFinish.
 * ────────────────────────────────────────────────────────────────────────── */
require_once('_includes.php');
if (!setupNeeded()) authRedirect('index.php');
$theme = themeSetup();
$esc   = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };

$needToken  = (string) envValue('SETUP_TOKEN', '') !== '';
$envAuth    = envBool('AUTH_REQUIRED');          // null = up to the wizard
$folders    = musicFolders();
$folderStat = musicFolderStats();
$themes     = GLOBAL_CONFIG['player_theme']['options'] ?? [];
$themeNow   = (string) getConfig('player_theme');
$hasSongs   = count(musicLibrary()) > 0;
?>
<!DOCTYPE html>
<html lang="en"<?= $theme["attributes"] ?>>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <meta name="theme-color" content="#2fb344">
    <title>Set up · mp3-web</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.7.4/dist/css/tabler.min.css" integrity="sha384-+YtspJF2L+4VLu1yrWfgSSmCUujhd1W+ji1C5TxKZeErkdVQQVTgKFBXiMcDf7VO" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css?v=<?= @filemtime(__DIR__ . '/style.css') ?>">
    <link rel="stylesheet" href="themes.css">
    <style id="themeVars"><?= $theme["css"] ?></style>
    <?= $theme["script"] ?>
  </head>
  <body class="authPage">
    <main class="authCard setupCard card">
      <div class="card-body">
        <div class="authBrand">
          <img src="assets/branding/logo.svg" alt="" width="48" height="48">
          <div>
            <h1>Welcome</h1>
            <div class="text-secondary">Let's get your music player ready.</div>
          </div>
        </div>

        <ol class="setupSteps" aria-label="Setup steps">
          <li class="active" data-step="1">Account</li>
          <li data-step="2">Music</li>
          <li data-step="3">Basics</li>
          <li data-step="4">Done</li>
        </ol>

        <form id="setupForm" novalidate>
          <input type="hidden" name="action" value="setupFinish">
          <input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token'] ?? '') ?>">
          <input type="hidden" name="music_folders" value="">

          <!-- 1. Account -->
          <section class="setupStep" data-step="1">
            <h2>Create the admin account</h2>
            <p class="text-secondary">The admin manages users, music folders and settings. You can add more people later in Settings → Users.</p>
            <?php if ($needToken): ?>
            <div class="mb-3">
              <label class="form-label" for="setupToken">Setup token</label>
              <input class="form-control" id="setupToken" name="setup_token" autocomplete="off" spellcheck="false" required>
              <div class="form-hint">The value of <code>SETUP_TOKEN</code> this server was started with.</div>
            </div>
            <?php endif; ?>
            <?php if ($envAuth === false): ?>
            <div class="alert alert-info">Login is turned off by <code>AUTH_REQUIRED=false</code>, so an account is optional. Leave it empty to skip.</div>
            <?php endif; ?>
            <div class="row g-3">
              <div class="col-sm-6">
                <label class="form-label" for="username">Username</label>
                <input class="form-control" id="username" name="username" autocomplete="username" autocapitalize="none" spellcheck="false" pattern="[A-Za-z0-9._\-]{2,32}" <?= $envAuth === false ? '' : 'required' ?> autofocus>
              </div>
              <div class="col-sm-6">
                <label class="form-label" for="displayName">Display name <span class="text-secondary">(optional)</span></label>
                <input class="form-control" id="displayName" name="display_name" maxlength="30" autocomplete="name">
              </div>
              <div class="col-sm-6">
                <label class="form-label" for="password">Password</label>
                <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" minlength="<?= AUTH_PASSWORD_MIN ?>">
              </div>
              <div class="col-sm-6">
                <label class="form-label" for="password2">Repeat password</label>
                <input class="form-control" id="password2" name="password2" type="password" autocomplete="new-password">
              </div>
            </div>
          </section>

          <!-- 2. Music folders -->
          <section class="setupStep" data-step="2" hidden>
            <h2>Where is your music?</h2>
            <p class="text-secondary">Add one or more folders on this server. Uploads go to the first one.</p>
            <div class="settingFolders setupFolders">
              <ul class="folderList">
                <?php foreach ($folders as $i => $f): $st = $folderStat[$i] ?? null; ?>
                <li class="folderItem" data-path="<?= $esc($f['path']) ?>">
                  <?= icon("folder", 1.1, 0) ?>
                  <span class="folderInfo"><span class="folderName"><?= $esc($f['path']) ?></span>
                    <span class="folderStatus"><?= $st === null ? '' : (!$st['exists'] ? '<span class="text-danger">Not found</span>' : $st['songs'] . ($st['songs'] === 1 ? ' song' : ' songs')) ?></span></span>
                  <label class="form-check form-switch folderSub m-0">
                    <input class="form-check-input folderRecursive" type="checkbox" role="switch"<?= $f['recursive'] ? ' checked' : '' ?>>
                    <span class="form-check-label">Subfolders</span>
                  </label>
                  <button type="button" class="folderRemove" aria-label="Remove <?= $esc($f['path']) ?>">&times;</button>
                </li>
                <?php endforeach; ?>
              </ul>
              <button type="button" class="btn btn-sm btn-outline-secondary btn-pill folderAddBtn" aria-expanded="false"><?= icon("folder-plus", 1, 0) ?> Add folder…</button>
              <div class="folderBrowser" hidden>
                <div class="folderBrowserBar">
                  <button type="button" class="btn btn-sm btn-ghost-secondary folderUp" title="Up one folder" aria-label="Up one folder"><?= icon("corner-left-up", 1.1, 0) ?></button>
                  <input type="text" class="form-control form-control-sm folderPathInput" aria-label="Folder path" placeholder="/path/to/music" spellcheck="false" autocomplete="off">
                </div>
                <ul class="folderDirs" aria-label="Subfolders"></ul>
                <div class="folderBrowserButtons">
                  <button type="button" class="btn btn-sm btn-outline-secondary btn-pill folderCancel">Cancel</button>
                  <button type="button" class="btn btn-sm btn-success btn-pill folderUse">Add this folder</button>
                </div>
              </div>
            </div>
          </section>

          <!-- 3. Basics -->
          <section class="setupStep" data-step="3" hidden>
            <h2>A few basics</h2>
            <div class="mb-3">
              <label class="form-label" for="siteTitle">Site title</label>
              <input class="form-control" id="siteTitle" name="site_title" value="<?= $esc(getConfig('site_title')) ?>" maxlength="80">
            </div>
            <div class="mb-3">
              <label class="form-label" for="playerTheme">Theme</label>
              <select class="form-select" id="playerTheme" name="player_theme">
                <?php foreach ($themes as $value => $label): ?>
                <option value="<?= $esc($value) ?>"<?= (string) $value === $themeNow ? ' selected' : '' ?>><?= $esc($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php if ($envAuth === null): ?>
            <label class="form-check form-switch mb-2">
              <input type="hidden" name="auth_required" value="0">
              <input class="form-check-input" type="checkbox" name="auth_required" value="1" checked>
              <span class="form-check-label">Require sign-in <span class="text-secondary d-block small">Turn off only on a private network where everyone may use the admin account.</span></span>
            </label>
            <?php endif; ?>
            <label class="form-check form-switch mb-2">
              <input type="hidden" name="share_playing" value="0">
              <input class="form-check-input" type="checkbox" name="share_playing" value="1" checked>
              <span class="form-check-label">Show other users what I'm playing <span class="text-secondary d-block small">They can join in from their Activity panel. Every user can change this.</span></span>
            </label>
            <label class="form-check form-switch mb-2">
              <input type="hidden" name="fetch_metadata" value="0">
              <input class="form-check-input" type="checkbox" name="fetch_metadata" value="1"<?= $hasSongs ? ' checked' : '' ?>>
              <span class="form-check-label">Look up song info and covers online <span class="text-secondary d-block small">Runs in the background for songs that are missing details.</span></span>
            </label>
          </section>

          <!-- 4. Done -->
          <section class="setupStep" data-step="4" hidden>
            <h2>You're all set</h2>
            <p class="text-secondary setupDoneText">Opening your music…</p>
          </section>

          <div class="alert alert-danger authError mt-3" role="alert" hidden></div>
          <div class="setupNav">
            <button type="button" class="btn btn-outline-secondary setupBack" hidden>Back</button>
            <button type="submit" class="btn btn-success ms-auto setupNext">Next</button>
          </div>
        </form>
      </div>
    </main>

    <script>
      (function () {
        var ICON_SPRITE = <?= json_encode(ICON_SPRITE) ?>;
        var form   = document.getElementById("setupForm");
        var error  = form.querySelector(".authError");
        var back   = form.querySelector(".setupBack");
        var next   = form.querySelector(".setupNext");
        var steps  = form.querySelectorAll(".setupStep");
        var dots   = document.querySelectorAll(".setupSteps li");
        var step   = 1;
        var loginOptional = <?= json_encode($envAuth === false) ?>;

        function icon(name) {
          return '<svg class="lucide icon-' + name + '" aria-hidden="true" focusable="false"><use href="' + ICON_SPRITE + '#' + name + '"></use></svg>';
        }
        function showError(msg) {
          error.innerHTML = msg;
          error.hidden = !msg;
        }
        function go(n) {
          step = n;
          steps.forEach(function (s) { s.hidden = Number(s.dataset.step) !== n; });
          dots.forEach(function (d) {
            var i = Number(d.dataset.step);
            d.classList.toggle("active", i === n);
            d.classList.toggle("done", i < n);
          });
          back.hidden = n === 1 || n === 4;
          next.hidden = n === 4;
          next.textContent = n === 3 ? "Finish" : "Next";
          showError("");
          var first = form.querySelector('.setupStep[data-step="' + n + '"] input:not([type=hidden]), .setupStep[data-step="' + n + '"] select');
          if (first) first.focus();
        }

        function checkAccount() {
          var u = form.username.value.trim();
          if (loginOptional && u === "") return "";
          if (!/^[A-Za-z0-9._-]{2,32}$/.test(u)) return "Usernames are 2–32 characters: letters, digits, dot, dash or underscore.";
          if (form.password.value.length < <?= AUTH_PASSWORD_MIN ?>) return "Passwords need at least <?= AUTH_PASSWORD_MIN ?> characters.";
          if (form.password.value !== form.password2.value) return "The passwords don't match.";
          return "";
        }

        /* ── Music folders ── */
        var box = form.querySelector(".setupFolders");
        var browse = null;
        function folderValues() {
          return Array.prototype.map.call(box.querySelectorAll(".folderItem"), function (li) {
            return { path: li.dataset.path, recursive: li.querySelector(".folderRecursive").checked ? 1 : 0 };
          });
        }
        function browseFolder(path) {
          var list = box.querySelector(".folderDirs");
          var q = new URLSearchParams({ action: "browseDirs", path: path });
          if (form.setup_token) q.set("setup_token", form.setup_token.value);
          fetch("api.php?" + q, { credentials: "same-origin" }).then(function (r) { return r.json(); }).then(function (res) {
            if (res.status !== "success") { showError(res.response || "Could not open that folder."); return; }
            showError("");
            var d = browse = res.data;
            box.querySelector(".folderPathInput").value = d.path === "" ? "." : d.path;
            box.querySelector(".folderUp").disabled = !d.parent;
            list.innerHTML = "";
            if (!d.readable) list.innerHTML = '<li class="folderDirsEmpty text-danger">The web server can’t read this folder.</li>';
            else if (!d.dirs.length) list.innerHTML = '<li class="folderDirsEmpty text-secondary">No subfolders.</li>';
            d.dirs.forEach(function (name) {
              var li = document.createElement("li");
              var b = document.createElement("button");
              b.type = "button";
              b.className = "folderDir";
              b.dataset.name = name;
              b.innerHTML = icon("folder") + "<span></span>";
              b.querySelector("span").textContent = name;
              li.appendChild(b);
              list.appendChild(li);
            });
          }).catch(function () { showError("Could not reach the server."); });
        }
        function closeBrowser() {
          box.querySelector(".folderBrowser").hidden = true;
          box.querySelector(".folderAddBtn").setAttribute("aria-expanded", "false");
        }
        box.addEventListener("click", function (e) {
          var t = e.target.closest("button");
          if (!t) return;
          if (t.classList.contains("folderAddBtn")) {
            var panel = box.querySelector(".folderBrowser");
            panel.hidden = !panel.hidden;
            t.setAttribute("aria-expanded", panel.hidden ? "false" : "true");
            if (!panel.hidden) browseFolder("");
          } else if (t.classList.contains("folderDir") && browse) {
            browseFolder(browse.absolute.replace(/\/+$/, "") + "/" + t.dataset.name);
          } else if (t.classList.contains("folderUp") && browse && browse.parent) {
            browseFolder(browse.parent);
          } else if (t.classList.contains("folderCancel")) {
            closeBrowser();
          } else if (t.classList.contains("folderRemove")) {
            t.closest(".folderItem").remove();
          } else if (t.classList.contains("folderUse")) {
            var typed = box.querySelector(".folderPathInput").value.trim();
            if (!browse || (typed !== (browse.path === "" ? "." : browse.path) && typed !== browse.absolute)) {
              browseFolder(typed);
              return;
            }
            var path = browse.path === "" ? "." : browse.path;
            if (folderValues().some(function (f) { return f.path === path; })) { showError("That folder is already in the list."); return; }
            var li = document.createElement("li");
            li.className = "folderItem";
            li.dataset.path = path;
            li.innerHTML = icon("folder") +
              '<span class="folderInfo"><span class="folderName"></span><span class="folderStatus"></span></span>' +
              '<label class="form-check form-switch folderSub m-0"><input class="form-check-input folderRecursive" type="checkbox" role="switch" checked>' +
              '<span class="form-check-label">Subfolders</span></label><button type="button" class="folderRemove">&times;</button>';
            li.querySelector(".folderName").textContent = path;
            li.querySelector(".folderRemove").setAttribute("aria-label", "Remove " + path);
            box.querySelector(".folderList").appendChild(li);
            closeBrowser();
          }
        });
        box.querySelector(".folderPathInput").addEventListener("keydown", function (e) {
          if (e.key === "Enter") { e.preventDefault(); browseFolder(this.value.trim()); }
        });

        back.addEventListener("click", function () { go(step - 1); });

        form.addEventListener("submit", function (e) {
          e.preventDefault();
          if (step === 1) {
            var err = checkAccount();
            if (err) { showError(err); return; }
            go(2);
            return;
          }
          if (step === 2) {
            if (!folderValues().length) { showError("Add at least one music folder."); return; }
            go(3);
            return;
          }
          form.music_folders.value = JSON.stringify(folderValues());
          next.disabled = true;
          fetch("api.php", { method: "POST", body: new FormData(form), credentials: "same-origin" })
            .then(function (r) { return r.json(); })
            .then(function (res) {
              next.disabled = false;
              if (res.status !== "success") {
                if (res.data && res.data.step) go(res.data.step);
                if (res.data && res.data.setup_token === false) go(1);
                showError(res.response || "Setup failed.");
                return;
              }
              go(4);
              var done = function () { location.href = "index.php"; };
              if (res.data && res.data.fetch_metadata) {
                // Signed in as the new admin now, with a fresh CSRF token on the page we open next;
                // start the job from there instead of here.
                sessionStorage.setItem("mp3web.startMetaJob", "1");
              }
              setTimeout(done, 700);
            })
            .catch(function () {
              next.disabled = false;
              showError("Could not reach the server.");
            });
        });
      })();
    </script>
  </body>
</html>
