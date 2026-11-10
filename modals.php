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

      <!-- # NOTE: Not in a session: create / join -->
      <div class="sessionLobby">
      <div class="d-flex flex-column flex-md-row justify-content-around">

        <div class="card m-2 w-100">
          <div class="card-status-top bg-success"></div>
          <h4 class="card-header text-center">Create session</h4>
          <div class="card-body text-center">
            <p class="text-muted">Start a session and invite others. Everyone hears the same song, in sync.</p>
            <a href="javascript:void(0);" class="btn btn-success m-2 sessionBtn" data-target="#createSessionForm">
                '.icon("person-plus-fill", 2).'
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
                '.icon("people-fill", 2).'
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
            <div class="input-group mb-3 mx-auto" style="max-width: 520px;">
              <input type="text" class="form-control sessionInviteLink" readonly aria-label="Invite link">
              <button type="button" class="btn btn-outline-primary copyInviteBtn">'.icon("link-45deg", 1.2, 0).' Copy invite link</button>
            </div>
            <div class="sessionHostOnly mb-3" style="display:none;">
              <label class="form-check form-switch d-inline-flex align-items-center gap-2">
                <input class="form-check-input m-0" type="checkbox" id="sessionAllowAll">
                <span>Let everyone control playback</span>
              </label>
            </div>
            <button type="button" class="btn btn-danger leaveSessionBtn">'.icon("box-arrow-right", 1.2, 0).' Leave session</button>
          </div>
        </div>
      </div>

      </div>
    </div>
  </div>
</div>
';

/* ────────────────────────────── Config Modal ────────────────────────────── */
echo '
<div class="modal fade" id="configModal" tabindex="-1" aria-labelledby="configModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title" id="configModalLabel">Configuration</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <div class="modal-status bg-primary"></div>
      </div>
      <div class="modal-body">
      <form action="api.php" method="POST">
      <div class="modalTable">
      <table class="table table-bordered">
        <thead>
          <tr>
            <th colspan="100%" class="text-bg-secondary text-center">'.CONFIG_FILE.'</th>
          </tr>
        </thead>
        <tbody>';
      foreach (CONFIG as $key => $values) {
        $name          = $values["name"];
        $value         = $values["value"];
        $description   = $values["description"];
        $type          = $values["type"];
        $attributes    = $values["attributes"] ?? [];
        $inputData     = "data-key='".$key."'";
        $badgeClass    = "badge text-bg-primary";
        
        # String
        if ($type == "string") {
          $badgeClass = "badge text-bg-secondary";
          $input      = '<textarea class="autoheight form-control settingInput" '.$inputData.'>'.$value.'</textarea>';
        }

        # Array
        if ($type == "array") {
          $badgeClass = "badge text-bg-info";
          $input = '
          <div class="configList">
          <div class="configListBtns input-group m-2">
              <button type="button" class="btn btn-outline-success btn-sm array-plus w-100" data-key="'.$key.'">
                <span aria-hidden="true">&plus;</span>
              </button>
            </div>
          </div>
          ';
          if (!is_array($value) || empty($value)) {
            $value = [];
          }
          foreach ($value as $arrayKey => $arrayValue) {
            $input .= "
            <div class='input-group m-2 configListItem'>
              <input type='text' class='form-control settingInput' value='".htmlspecialchars($arrayValue, ENT_QUOTES, 'UTF-8')."' data-key='".$key."-".$arrayKey."' placeholder='Array item $arrayKey'>
                <button type='button' class='btn btn-outline-danger btn-sm array-minus' data-key='$key'>
                  <span aria-hidden='true'>&minus;</span>
                </button>
            </div>
            ";
          }
        }

        # Boolean
        if ($type == "bool") {
          $badgeClass = "badge text-bg-azure";
          if ($value === "true" || $value === 1) {
            $value = True;
          } elseif ($value === "false" || $value === 0 || empty($value)) {
            $value = False;
          }
          $input = '
            <div class="form-check form-switch">
              <input class="form-check-input settingInput" type="checkbox" '.($value ? 'checked' : '').' '.$inputData.'>
            </div>';
        }

        # Range
        if ($type == "range") {
          $badgeClass = "badge text-bg-warning";
          $value      = $value ?? 0;
          $min        = $attributes["min"] ?? 0;
          $max        = $attributes["max"] ?? 1;
          $step       = $attributes["step"] ?? .05;
          $input      = '
            <input class="form-range settingInput settingRange" data-valueobject="'.$key.'-val" type="range" value="'.$value.'" min="'.$min.'" max="'.$max.'" step="'.$step.'" '.$inputData.'>
            <output for="'.$key.'" class="form-label" id="'.$key.'-val">'.($value * 100).'%</output>
          ';
        }

        # Selection
        if ($type == "selection") {
          $badgeClass = "badge text-bg-success";
          $options = $values["options"];
          $input = '<select class="form-select settingInput" '.$inputData.'>';
          if (is_array($options)) {
            foreach ($options as $optionValue => $optionName) {
              $selected  = ($optionValue == $value) ? 'selected' : '';
              $input    .= '<option value="'.$optionValue.'" '.$selected.'>'.$optionName.'</option>';
            }
          } else {
            $selected = Null;
            $input = "Invalid selection (not an array)";
          }
          $input .= '</select>';
        }
        echo '
        <tr>
            <td class="text-primary">
            <div class="d-flex justify-content-between">
              <div>
                <label for="'.$key.'" class="form-label">'.$name.'</label>
                <small class="text-muted">'.$key.': '.$description.'</small>
              </div>
              <div>
                <span class="'.$badgeClass.'">'.$type.'</span>
              </div>
            </div>
            </td>
          <td>
            '.$input.'
          </td>
        </tr>';
      }
echo '</tbody>
      </table>
      </div>
      <div class="d-flex justify-content-end">
        <a href="javascript:void(0);" class="btn btn-outline-secondary m-1" data-bs-dismiss="modal">Close</a>
        <button type="reset" class="btn btn-warning m-1 resetCfgBtn" style="display:none;">Reset Changes</button>
        <a href="?reload=1" class="btn btn-success m-1 reloadCfgBtn" style="display:none;">Reload page to apply changes</a>
      </div>
      </form>
      </div>
    </div>
  </div>
</div>
';