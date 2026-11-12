/* ────────────────────────────────────────────────────────────────────────── */
/*                                Audio graph                                 */
/* ────────────────────────────────────────────────────────────────────────── */
// Owns the single Web Audio route for the <audio> element (an element can only
// be wrapped by createMediaElementSource once, so everything shares this one):
//
//   source → [loudness: compressor → limiter] → output → volume → speakers
//                                                  └→ taps (waveform)
//
// With loudness on, the user's volume is applied by the `volume` gain node
// *after* the dynamics (the element itself stays at 1). Otherwise the element's
// volume would turn the signal down before the compressor, which then sees a
// quiet track and boosts it back up — making low volumes far too loud.
//
// The graph is only built when something needs it (the waveform or loudness
// equalization), and only inside a user gesture: once the element is routed
// through an AudioContext, a suspended context would silence it.
(function () {
  "use strict";

  var ctx = null, source = null, output = null;
  var comp = null, limiter = null, volumeNode = null;
  var volume = 1;
  var loudness = !!window.loudnessEqualization;
  var wanted = loudness;
  var readyCallbacks = [];

  function build() {
    if (ctx) return true;
    var AC = window.AudioContext || window.webkitAudioContext;
    var audio = document.querySelector("audio");
    if (!AC || !audio) return false;
    try {
      ctx = new AC();
      source = ctx.createMediaElementSource(audio);
      output = ctx.createGain();
      volumeNode = ctx.createGain();
      output.connect(volumeNode);
      volumeNode.connect(ctx.destination);

      // Loudness equalization: a wide-knee compressor pulls loud passages
      // down and its built-in automatic makeup gain (≈ +15 dB at these
      // settings) lifts everything back up, so quiet and loud songs end up at
      // a similar level. The limiter catches the peaks that would clip.
      comp = ctx.createDynamicsCompressor();
      comp.threshold.value = -30;
      comp.knee.value      = 20;
      comp.ratio.value     = 6;
      comp.attack.value    = 0.01;
      comp.release.value   = 0.3;
      limiter = ctx.createDynamicsCompressor();
      limiter.threshold.value = -1.5;
      limiter.knee.value      = 0;
      limiter.ratio.value     = 20;
      limiter.attack.value    = 0.002;
      limiter.release.value   = 0.1;
      comp.connect(limiter);
      limiter.connect(output);

      route();
      readyCallbacks.splice(0).forEach(function (cb) { cb(ctx, output); });
      return true;
    } catch (e) {
      console.warn("Audio graph: Web Audio unavailable", e);
      ctx = null;
      return false;
    }
  }

  function route() {
    if (!source) return;
    source.disconnect();
    source.connect(loudness ? comp : output);
    applyVolume();
  }

  // Put the user's volume on the element, or on the post-dynamics gain node
  // when loudness equalization is active (see the diagram above).
  function applyVolume() {
    var audio = document.querySelector("audio");
    var post = !!(ctx && loudness);
    if (audio) audio.volume = post ? 1 : volume;
    if (volumeNode) volumeNode.gain.value = post ? volume : 1;
  }

  function onGesture() {
    if (build() && ctx.state === "suspended") ctx.resume();
    document.removeEventListener("pointerdown", onGesture, true);
    document.removeEventListener("keydown", onGesture, true);
  }

  function arm() {
    if (ctx) return;
    document.addEventListener("pointerdown", onGesture, true);
    document.addEventListener("keydown", onGesture, true);
  }

  window.audioGraph = {
    // Ask for the graph; cb(ctx, outputNode) runs once it exists.
    request: function (cb) {
      if (ctx) { cb(ctx, output); return; }
      readyCallbacks.push(cb);
      if (!wanted) { wanted = true; arm(); }
    },
    context: function () { return ctx; },
    resume: function () {
      if (ctx && ctx.state === "suspended") ctx.resume();
    },
    loudness: function () { return loudness; },
    volume: function () { return volume; },
    setVolume: function (v) { volume = v; applyVolume(); },
    setLoudness: function (on) {
      loudness = !!on;
      if (ctx) { route(); return; }
      if (loudness && !wanted) { wanted = true; arm(); }
    },
  };

  if (wanted) arm();
})();
