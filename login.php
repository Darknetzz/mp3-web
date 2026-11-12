<?php
/* ──────────────────────────────────────────────────────────────────────────
 * Sign-in page. The form posts to api.php?action=login.
 * ────────────────────────────────────────────────────────────────────────── */
require_once('_includes.php');
if (setupNeeded()) authRedirect('setup.php');
if (isSignedIn() || (currentUser() !== null && !authRequired())) authRedirect('index.php');
$theme = themeSetup();
$title = getConfig("site_title");
$esc   = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
?>
<!DOCTYPE html>
<html lang="en"<?= $theme["attributes"] ?>>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <meta name="theme-color" content="#2fb344">
    <title>Sign in · <?= $esc($title) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.7.4/dist/css/tabler.min.css" integrity="sha384-+YtspJF2L+4VLu1yrWfgSSmCUujhd1W+ji1C5TxKZeErkdVQQVTgKFBXiMcDf7VO" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css?v=<?= @filemtime(__DIR__ . '/style.css') ?>">
    <link rel="stylesheet" href="themes.css">
    <style id="themeVars"><?= $theme["css"] ?></style>
    <?= $theme["script"] ?>
  </head>
  <body class="authPage">
    <main class="authCard card">
      <div class="card-body">
        <div class="authBrand">
          <img src="assets/branding/logo.svg" alt="" width="48" height="48">
          <h1><?= $esc($title) ?></h1>
        </div>
        <form id="loginForm" autocomplete="on" novalidate>
          <input type="hidden" name="action" value="login">
          <input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token'] ?? '') ?>">
          <div class="mb-3">
            <label class="form-label" for="username">Username</label>
            <input class="form-control" id="username" name="username" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>
          </div>
          <div class="mb-3">
            <label class="form-label" for="password">Password</label>
            <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>
          </div>
          <label class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="remember" value="1" checked>
            <span class="form-check-label">Keep me signed in on this device</span>
          </label>
          <div class="alert alert-danger authError" role="alert" hidden></div>
          <button class="btn btn-success w-100" type="submit"><?= icon("log-in", 1, 0) ?> Sign in</button>
        </form>
        <p class="text-secondary small mt-3 mb-0">No account? Ask an admin to create one for you.</p>
      </div>
    </main>
    <script>
      (function () {
        var form = document.getElementById("loginForm");
        var error = form.querySelector(".authError");
        var button = form.querySelector("button[type=submit]");
        form.addEventListener("submit", function (e) {
          e.preventDefault();
          error.hidden = true;
          button.disabled = true;
          fetch("api.php", { method: "POST", body: new FormData(form), credentials: "same-origin" })
            .then(function (r) { return r.json(); })
            .then(function (res) {
              if (res.status === "success") {
                var next = new URLSearchParams(location.search).get("next");
                location.href = next && /^[a-z0-9_.-]+\.php$/i.test(next) ? next : "index.php";
                return;
              }
              error.innerHTML = res.response || "Could not sign in.";
              error.hidden = false;
              button.disabled = false;
              form.password.select();
            })
            .catch(function () {
              error.textContent = "Could not reach the server.";
              error.hidden = false;
              button.disabled = false;
            });
        });
      })();
    </script>
  </body>
</html>
