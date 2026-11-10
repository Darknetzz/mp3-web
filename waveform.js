/* ────────────────────────────────────────────────────────────────────────── */
/*                                  Waveform                                  */
/* ────────────────────────────────────────────────────────────────────────── */
// Small bar visualizer in the playbar. Driven by a Web Audio AnalyserNode while
// playing locally; draws simulated bars while casting (no local signal).
// The animation loop only runs while playing, so pausing freezes the last frame.
(function () {
  "use strict";

  var BAR_COUNT = 20;
  var BAR_GAP   = 2;

  var canvas    = null;
  var ctx       = null;
  var audioCtx  = null;
  var analyser  = null;
  var freqData  = null;
  var rafId     = null;
  var isRemote  = false;
  var isPlaying = false;
  var simulated = [];
  var reducedMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function barColor() {
    var c = getComputedStyle(document.body).getPropertyValue("--tblr-success").trim();
    return c || "#2fb344";
  }

  function setupCanvas() {
    canvas = document.querySelector(".waveform");
    if (!canvas) return false;
    var dpr  = window.devicePixelRatio || 1;
    var rect = canvas.getBoundingClientRect();
    canvas.width  = Math.round((rect.width  || 120) * dpr);
    canvas.height = Math.round((rect.height || 32)  * dpr);
    ctx = canvas.getContext("2d");
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    for (var i = 0; i < BAR_COUNT; i++) simulated.push(0.1);
    return true;
  }

  // Must run inside a user gesture: once the <audio> element is routed through
  // an AudioContext, a suspended context would silence it.
  function ensureGraph() {
    if (audioCtx) return;
    var AC = window.AudioContext || window.webkitAudioContext;
    var audio = document.querySelector("audio");
    if (!AC || !audio) return;
    try {
      audioCtx = new AC();
      var source = audioCtx.createMediaElementSource(audio);
      analyser = audioCtx.createAnalyser();
      analyser.fftSize = 64;
      analyser.smoothingTimeConstant = 0.75;
      source.connect(analyser);
      analyser.connect(audioCtx.destination);
      freqData = new Uint8Array(analyser.frequencyBinCount);
    } catch (e) {
      console.warn("Waveform: Web Audio unavailable, using simulated bars", e);
      audioCtx = null;
      analyser = null;
    }
  }

  // Heights in the range 0..1, one per bar
  function levels() {
    var out = new Array(BAR_COUNT);
    if (!isRemote && analyser && audioCtx.state === "running") {
      analyser.getByteFrequencyData(freqData);
      // Use the lower ~80% of bins; the top ones are mostly empty for music.
      var usable = Math.floor(freqData.length * 0.8);
      for (var i = 0; i < BAR_COUNT; i++) {
        var bin = Math.floor(i * usable / BAR_COUNT);
        out[i] = freqData[bin] / 255;
      }
      return out;
    }
    for (var j = 0; j < BAR_COUNT; j++) {
      var target = 0.15 + Math.random() * 0.75 * (1 - j / (BAR_COUNT * 1.4));
      simulated[j] += (target - simulated[j]) * 0.18;
      out[j] = simulated[j];
    }
    return out;
  }

  function draw(values) {
    if (!ctx) return;
    var w = canvas.clientWidth  || 120;
    var h = canvas.clientHeight || 32;
    var barW = (w - BAR_GAP * (BAR_COUNT - 1)) / BAR_COUNT;
    ctx.clearRect(0, 0, w, h);
    ctx.fillStyle = barColor();
    for (var i = 0; i < BAR_COUNT; i++) {
      var bh = Math.max(2, values[i] * h);
      var x  = i * (barW + BAR_GAP);
      var y  = (h - bh) / 2;
      if (ctx.roundRect) {
        ctx.beginPath();
        ctx.roundRect(x, y, barW, bh, Math.min(barW / 2, 2));
        ctx.fill();
      } else {
        ctx.fillRect(x, y, barW, bh);
      }
    }
  }

  function drawFlat() {
    var zeros = [];
    for (var i = 0; i < BAR_COUNT; i++) zeros.push(0);
    draw(zeros);
  }

  function loop() {
    draw(levels());
    rafId = requestAnimationFrame(loop);
  }

  function stopLoop() {
    if (rafId !== null) cancelAnimationFrame(rafId);
    rafId = null;
  }

  function start() {
    stopLoop();
    if (audioCtx && audioCtx.state === "suspended") audioCtx.resume();
    if (reducedMotion) {
      draw(levels());
      return;
    }
    loop();
  }

  window.waveform = {
    setPlaying: function (playing) {
      isPlaying = playing;
      if (!ctx) return;
      if (playing) {
        start();
      } else {
        stopLoop(); // last frame stays on the canvas, i.e. frozen
      }
    },
    setRemote: function (remote) {
      isRemote = remote;
    },
    // "analyser" when bars follow the real audio, otherwise "simulated"
    mode: function () {
      return (!isRemote && analyser && audioCtx.state === "running") ? "analyser" : "simulated";
    },
    clear: function () {
      isPlaying = false;
      stopLoop();
      drawFlat();
    },
  };

  function init() {
    if (!setupCanvas()) return;
    drawFlat();
    var onGesture = function () {
      ensureGraph();
      if (audioCtx && audioCtx.state === "suspended") audioCtx.resume();
      document.removeEventListener("pointerdown", onGesture, true);
      document.removeEventListener("keydown", onGesture, true);
    };
    document.addEventListener("pointerdown", onGesture, true);
    document.addEventListener("keydown", onGesture, true);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
