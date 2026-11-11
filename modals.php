<?php

/* ───────────────────────── Listening session modal ──────────────────────── */
echo '
<div class="modal fade" id="sessionModal" tabindex="-1" aria-labelledby="sessionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title" id="sessionModalLabel">Listen together</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <div class="modal-status bg-primary"></div>
      </div>
      <div class="modal-body">

      <!-- # NOTE: Your display name (shown to others in a session) -->
      <form class="displayNameForm mx-2 mb-2" autocomplete="off">
        <label class="form-label" for="displayNameInput">Your name</label>
        <div class="input-group" style="max-width: 420px;">
          <span class="input-group-text">'.icon("user", 1.2, 0).'</span>
          <input type="text" class="form-control" id="displayNameInput" name="name" maxlength="30" required
                 placeholder="What others in the session see">
          <button type="submit" class="btn btn-outline-primary">Save</button>
        </div>
      </form>

      <!-- # NOTE: Not in a session: create / join -->
      <div class="sessionLobby">
      <div class="d-flex flex-column flex-md-row justify-content-around">

        <div class="card m-2 w-100">
          <div class="card-status-top bg-success"></div>
          <h4 class="card-header text-center">Create session</h4>
          <div class="card-body text-center">
            <p class="text-muted">Start a session and invite others. Everyone hears the same song, in sync.</p>
            <a href="javascript:void(0);" class="btn btn-success m-2 sessionBtn" data-target="#createSessionForm">
                '.icon("user-plus", 2).'
            </a>
            <form class="sessionForm" id="createSessionForm" style="display:none;" method="POST">
              <input type="text" class="form-control m-1 w-100" name="name" maxlength="40" placeholder="Session name (optional)">
              <div class="m-2">
                <label class="form-check d-inline-flex align-items-center gap-2">
                  <input type="checkbox" class="form-check-input m-0" name="public" value="1">
                  <span>Public <small class="text-muted">(listed for anyone on this server to join)</small></span>
                </label>
              </div>
              <div class="btn-group w-100 m-1">
                <button type="submit" class="btn btn-success m-1">Create session</button>
                <a href="javascript:void(0);" class="btn btn-secondary m-1 cancelSessionBtn">Cancel</a>
              </div>
            </form>
          </div>
        </div>

        <div class="card m-2 w-100">
          <div class="card-status-top bg-primary"></div>
          <h4 class="card-header text-center">Join session</h4>
          <div class="card-body text-center">
            <p class="text-muted">Join with a code, or pick a public session below.</p>
            <a href="javascript:void(0);" class="btn btn-primary m-2 sessionBtn" data-target="#joinSessionForm">
                '.icon("users", 2).'
            </a>
            <form class="sessionForm" id="joinSessionForm" style="display:none;" method="POST">
              <input type="text" class="form-control m-1 w-100 font-monospace" name="sessionCode" maxlength="8"
                     placeholder="Session code (8 characters)" autocomplete="off" spellcheck="false">
              <div class="btn-group w-100 m-1">
                <button type="submit" class="btn btn-primary m-1">Join session</button>
                <a href="javascript:void(0);" class="btn btn-secondary m-1 cancelSessionBtn">Cancel</a>
              </div>
            </form>
            <h5 class="mt-3 text-start">Public sessions</h5>
            <div class="sessionPublicList text-start"></div>
          </div>
        </div>

      </div>
      </div>

      <!-- # NOTE: In a session -->
      <div class="sessionActive" style="display:none;">
        <div class="card m-2">
          <div class="card-status-top bg-green"></div>
          <div class="card-body text-center">
            <h2 class="sessionName mb-1"></h2>
            <div class="mb-3">
              <span class="sessionListening fw-bold"></span> listening
              <span class="badge sessionRoleBadge ms-2"></span>
            </div>
            <div class="text-muted small">Session code</div>
            <div class="sessionCodeText font-monospace fs-1 mb-3"></div>
            <div class="sessionQr mx-auto mb-2" role="img" aria-label="QR code with the invite link" style="display:none;"></div>
            <div class="sessionQrHint text-muted small mb-3" style="display:none;">Scan to join on a phone</div>
            <div class="input-group mb-3 mx-auto" style="max-width: 520px;">
              <input type="text" class="form-control sessionInviteLink" readonly aria-label="Invite link">
              <button type="button" class="btn btn-outline-primary copyInviteBtn">'.icon("link", 1.2, 0).' Copy invite link</button>
            </div>
            <div class="mx-auto mb-3 text-start" style="max-width: 520px;">
              <div class="text-muted small mb-1">Members</div>
              <div class="list-group sessionMembers"></div>
            </div>
            <div class="sessionHostOnly mb-3" style="display:none;">
              <label class="form-check form-switch d-inline-flex align-items-center gap-2">
                <input class="form-check-input m-0" type="checkbox" id="sessionAllowAll">
                <span>Let everyone control playback</span>
              </label>
            </div>
            <button type="button" class="btn btn-danger leaveSessionBtn">'.icon("log-out", 1.2, 0).' Leave session</button>
          </div>
        </div>
      </div>

      </div>
    </div>
  </div>
</div>
';

/* ────────────────────────────── Settings Modal ──────────────────────────── */

# Settings are grouped by key; anything not listed here ends up under "Other".
$settingsCategories = [
  "general"    => ["name" => "General",    "icon" => "sliders-horizontal", "keys" => ["site_title", "no_song_text", "env"]],
  "library"    => ["name" => "Library",    "icon" => "list-video", "keys" => ["audio_path", "allowed_types", "include_file_extension", "default_sort", "default_sort_order", "default_table_cols"]],
  "playback"   => ["name" => "Playback",   "icon" => "circle-play", "keys" => ["autoplay_next", "default_volume", "seek_step", "start_shuffle", "start_loop", "remember_position", "auto_scroll", "auto_scroll_on_end", "use_legacy_player"]],
  "appearance" => ["name" => "Appearance", "icon" => "palette",         "keys" => ["player_theme", "custom_base", "custom_background", "custom_surface", "custom_text", "accent_color", "custom_accent"]],
  "layout"     => ["name" => "Layout",     "icon" => "panels-top-left", "keys" => ["player_position", "show_waveform", "show_quick_buttons", "show_upload", "compact_rows", "fluid-container"]],
  "background" => ["name" => "Background", "icon" => "image",           "keys" => ["background_image", "background_dim", "background_blur"]],
  "other"      => ["name" => "Other",      "icon" => "ellipsis", "keys" => []],
];

# Rows that only make sense for a particular value of another setting.
$settingsShowWhen = [
  "custom_base"       => "player_theme=custom",
  "custom_background" => "player_theme=custom",
  "custom_surface"    => "player_theme=custom",
  "custom_text"       => "player_theme=custom",
  "custom_accent"     => "accent_color=custom",
  "background_dim"    => "background_image!=",
  "background_blur"   => "background_image!=",
  "auto_scroll_on_end" => "auto_scroll=true",
];

$accentSwatches = [
  "green" => "#2fb344", "blue" => "#3b82f6", "purple" => "#a855f7", "pink" => "#ec4899",
  "orange" => "#f97316", "red" => "#ef4444", "teal" => "#14b8a6", "yellow" => "#eab308",
];

function settingsEsc($v): string {
  return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function formatRangeValue($value, string $format): string {
  if ($format === "px") return round((float) $value) . " px";
  return round((float) $value * 100) . "%";
}

/** The input for one setting. Every control carries .settingInput and data-key. */
function renderSettingControl(string $key, array $setting): string {
  global $accentSwatches;
  $type  = $setting["type"] ?? "string";
  $value = $setting["value"] ?? "";
  $attrs = $setting["attributes"] ?? [];
  $k     = settingsEsc($key);
  $id    = "cfg-" . preg_replace('/[^a-z0-9_-]/i', '', $key);

  switch ($type) {
    case "bool":
      $on = ($value === true || $value === "true" || $value === 1 || $value === "1");
      return '<label class="form-check form-switch settingSwitch m-0">
                <input class="form-check-input settingInput" type="checkbox" role="switch" id="'.$id.'" data-key="'.$k.'"'.($on ? ' checked' : '').'>
              </label>';

    case "selection":
      $options = is_array($setting["options"] ?? null) ? $setting["options"] : [];
      if (count($options) <= 4) {
        $html = '<div class="settingSegmented" role="radiogroup">';
        foreach ($options as $optValue => $optName) {
          $optId = $id . "-" . preg_replace('/[^a-z0-9_-]/i', '', (string) $optValue);
          $html .= '<input type="radio" class="btn-check settingInput" name="'.$id.'" id="'.$optId.'" value="'.settingsEsc($optValue).'" data-key="'.$k.'"'.((string) $optValue === (string) $value ? ' checked' : '').'>'
                .  '<label class="segBtn" for="'.$optId.'">'.settingsEsc($optName).'</label>';
        }
        return $html . '</div>';
      }
      $html = '<select class="form-select settingInput" id="'.$id.'" data-key="'.$k.'">';
      foreach ($options as $optValue => $optName) {
        $html .= '<option value="'.settingsEsc($optValue).'"'.((string) $optValue === (string) $value ? ' selected' : '').'>'.settingsEsc($optName).'</option>';
      }
      $html .= '</select>';
      if ($key === "accent_color") {
        $swatch = $accentSwatches[$value] ?? "var(--tblr-success)";
        $html = '<div class="d-flex align-items-center gap-2"><span class="accentDot" style="background:'.settingsEsc($swatch).'"></span>'.$html.'</div>';
      }
      return $html;

    case "range":
      $min    = $attrs["min"] ?? 0;
      $max    = $attrs["max"] ?? 1;
      $step   = $attrs["step"] ?? 0.05;
      $format = $attrs["format"] ?? "percent";
      return '<div class="settingRangeWrap">
                <input type="range" class="form-range settingInput settingRange" id="'.$id.'" data-key="'.$k.'" data-format="'.settingsEsc($format).'"
                       min="'.settingsEsc($min).'" max="'.settingsEsc($max).'" step="'.settingsEsc($step).'" value="'.settingsEsc($value).'">
                <output class="settingRangeValue" for="'.$id.'">'.formatRangeValue($value, $format).'</output>
              </div>';

    case "number":
      $unit = $attrs["unit"] ?? "";
      return '<div class="input-group settingNumber">
                <input type="number" class="form-control settingInput" id="'.$id.'" data-key="'.$k.'"
                       min="'.settingsEsc($attrs["min"] ?? "").'" max="'.settingsEsc($attrs["max"] ?? "").'" step="'.settingsEsc($attrs["step"] ?? 1).'" value="'.settingsEsc($value).'">
                '.($unit !== "" ? '<span class="input-group-text">'.settingsEsc($unit).'</span>' : '').'
              </div>';

    case "color":
      return '<div class="d-flex align-items-center gap-2 justify-content-end">
                <span class="settingColorHex font-monospace small text-secondary">'.settingsEsc($value).'</span>
                <input type="color" class="form-control form-control-color settingInput" id="'.$id.'" data-key="'.$k.'" value="'.settingsEsc($value).'">
              </div>';

    case "array":
      $items = is_array($value) ? $value : [];
      $html  = '<div class="settingChips" data-key="'.$k.'">';
      foreach ($items as $item) {
        $html .= '<span class="settingChip"><span class="chipText">'.settingsEsc($item).'</span>'
              .  '<button type="button" class="chipRemove" aria-label="Remove '.settingsEsc($item).'">&times;</button></span>';
      }
      $html .= '<input type="text" class="chipAdd" placeholder="Add…" aria-label="Add item" size="6">';
      return $html . '</div>';

    case "multiselect":
      $options  = is_array($setting["options"] ?? null) ? $setting["options"] : [];
      $selected = is_array($value) ? $value : [];
      $html = '<div class="settingMulti settingSegmented flex-wrap justify-content-end" data-key="'.$k.'">';
      foreach ($options as $opt) {
        $optId = $id . "-" . preg_replace('/[^a-z0-9_-]/i', '', $opt ?: "x") . "-" . substr(md5($opt), 0, 4);
        $html .= '<input type="checkbox" class="btn-check multiOpt" id="'.$optId.'" value="'.settingsEsc($opt).'"'.(in_array($opt, $selected, true) ? ' checked' : '').'>'
              .  '<label class="segBtn" for="'.$optId.'">'.settingsEsc($opt).'</label>';
      }
      return $html . '</div>';

    default:
      return '<input type="text" class="form-control settingInput" id="'.$id.'" data-key="'.$k.'" value="'.settingsEsc(is_array($value) ? implode(", ", $value) : $value).'">';
  }
}

# Build the rows per category
$categoryOf = [];
foreach ($settingsCategories as $catKey => $cat) {
  foreach ($cat["keys"] as $settingKey) $categoryOf[$settingKey] = $catKey;
}
$categoryRows = array_fill_keys(array_keys($settingsCategories), []);
foreach (CONFIG as $key => $setting) {
  if (!is_array($setting)) continue;
  $name   = $setting["name"] ?? $key;
  $desc   = $setting["description"] ?? "";
  $search = strtolower($name . " " . $desc . " " . $key);
  $when   = isset($settingsShowWhen[$key]) ? ' data-show-when="'.settingsEsc($settingsShowWhen[$key]).'"' : '';
  $categoryRows[$categoryOf[$key] ?? "other"][$key] = '
            <div class="settingRow" data-setting="'.settingsEsc($key).'" data-search="'.settingsEsc($search).'"'.$when.'>
              <div class="settingText">
                <div class="settingName" title="'.settingsEsc($key).'">'.settingsEsc($name).' <span class="settingSaved">'.icon("circle-check", 0.9, 0).'</span></div>
                <div class="settingDesc">'.settingsEsc($desc).'</div>
              </div>
              <div class="settingControl">'.renderSettingControl($key, $setting).'</div>
            </div>';
}

$navHtml = "";
$sectionsHtml = "";
foreach ($settingsCategories as $catKey => $cat) {
  $rows = $categoryRows[$catKey];
  if (empty($rows)) continue;
  $ordered = [];
  foreach ($cat["keys"] as $settingKey) {
    if (isset($rows[$settingKey])) {
      $ordered[] = $rows[$settingKey];
      unset($rows[$settingKey]);
    }
  }
  $navHtml .= '<a href="#settings-'.$catKey.'" class="settingsNavLink" data-target="settings-'.$catKey.'">'.icon($cat["icon"], 1, 0).'<span>'.$cat["name"].'</span></a>';
  $sectionsHtml .= '
          <section class="settingsSection" id="settings-'.$catKey.'">
            <h2 class="settingsSectionTitle">'.$cat["name"].'</h2>
            <div class="settingsCard">'.implode("", $ordered).implode("", $rows).'
            </div>
          </section>';
}

echo '
<div class="modal fade settingsModal" id="configModal" tabindex="-1" aria-labelledby="configModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-lg-down">
    <div class="modal-content">
      <div class="modal-header settingsHeader">
        <div class="w-100">
          <div class="d-flex align-items-center justify-content-between">
            <h1 class="settingsTitle" id="configModalLabel">Settings</h1>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="settingsSearch">
            '.icon("search", 1, 0).'
            <input type="search" class="settingsSearchInput" placeholder="Search settings" aria-label="Search settings" autocomplete="off">
          </div>
        </div>
      </div>
      <div class="modal-body settingsBody">
        <div class="settingsLayout">
          <nav class="settingsNav" aria-label="Settings sections">'.$navHtml.'</nav>
          <div class="settingsContent">'.$sectionsHtml.'
            <div class="settingsEmpty text-secondary" style="display:none;">No settings match your search.</div>
            <div class="settingsFile text-secondary small">Saved to <code>'.settingsEsc(CONFIG_FILE).'</code></div>
          </div>
        </div>
      </div>
      <div class="modal-footer settingsFooter" style="display:none;">
        <span class="text-secondary me-auto">'.icon("info", 1, 0).' Some changes apply after a reload.</span>
        <button type="button" class="btn btn-success reloadCfgBtn" onclick="window.location.reload()">Reload to apply</button>
      </div>
    </div>
  </div>
</div>
';
