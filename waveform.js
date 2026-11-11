/* ────────────────────────────────────────────────────────────────────────── */
/*                                  Waveform                                  */
/* ────────────────────────────────────────────────────────────────────────── */
// Level visualizer in the playbar, either next to the song title or across the
// whole bar behind the controls. Driven by a Web Audio AnalyserNode while
// playing locally; draws simulated levels while casting (no local signal).
// The animation loop only runs while playing, so pausing freezes the last frame.
// Options come from window.waveformConfig (js.php, "Player bar" settings) and
// can be changed live with waveform.configure().
(function () {
  "use strict";

  var cfg = {
    enabled:     true,
    placement:   "inline",  // "inline" | "background"
    style:       "bars",    // "bars" | "bottom" | "line"
    bars:        0,         // 0 = auto
    color:       "accent",  // "accent" | "text" | "custom"
    customColor: "#2fb344",
    opacity:     0.3,       // background placement only
    sensitivity: 1,
  };
  var key;
  for (key in (window.waveformConfig || {})) cfg[key] = window.waveformConfig[key];

  var canvas    = null;
  var ctx       = null;
  var audioCtx  = null;
  var analyser  = null;
  var freqData  = null;
  var rafId     = null;
  var isRemote  = false;
  var isPlaying = false;
  var simulated = [];
  var lastValues = null;
  var graphRequested = false;
  var reducedMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function barCount() {
    var n = parseInt(cfg.bars, 10);
    if (n > 0) return Math.max(4, Math.min(128, n));
    return cfg.placement === "background" ? 64 : 20;
  }

  function color() {
    if (cfg.color === "custom" && /^#[0-9a-f]{6}$/i.test(cfg.customColor)) return cfg.customColor;
    var style = getComputedStyle(document.body);
    var c = cfg.color === "text"
      ? style.getPropertyValue("--tblr-body-color").trim()
      : style.getPropertyValue("--tblr-success").trim();
    return c || "#2fb344";
  }

  /* ─────────────────────────────── Canvas ──────────────────────────────── */
  // Back the canvas with device pixels at its current CSS size.
  function sizeCanvas() {
    if (!canvas) return;
    var dpr  = window.devicePixelRatio || 1;
    var rect = canvas.getBoundingClientRect();
    var w = Math.max(1, Math.round((rect.width  || 120) * dpr));
    var h = Math.max(1, Math.round((rect.height || 32)  * dpr));
    if (canvas.width !== w || canvas.height !== h) {
      canvas.width  = w;
      canvas.height = h;
    }
    ctx = canvas.getContext("2d");
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  }

  // Put the canvas where the placement says and apply on/off and opacity.
  function place() {
    if (!canvas) return;
    var bg = cfg.placement === "background";
    var target = bg ? document.querySelector(".audio-player-container")
                    : document.querySelector(".playerLeft");
    if (target && canvas.parentNode !== target) {
      if (bg) target.insertBefore(canvas, target.firstChild);
      else target.appendChild(canvas);
    }
    canvas.classList.toggle("waveformBg", bg);
    canvas.hidden = !cfg.enabled;
    canvas.style.opacity = bg ? String(Math.max(0.05, Math.min(1, cfg.opacity))) : "";
    document.body.classList.toggle("waveform-bg", bg && cfg.enabled);
  }

  /* ───────────────────────────── Audio input ───────────────────────────── */
  // The <audio> element's Web Audio route lives in audiograph.js; the analyser
  // just taps its output.
  function attachAnalyser(ac, output) {
    audioCtx = ac;
    analyser = ac.createAnalyser();
    analyser.smoothingTimeConstant = 0.75;
    output.connect(analyser);
    sizeAnalyser();
  }

  // Enough frequency bins for every bar to get its own (fftSize 64..1024).
  function sizeAnalyser() {
    if (!analyser) return;
    var size = 64;
    while (size / 2 * 0.8 < barCount() && size < 1024) size *= 2;
    if (analyser.fftSize !== size) analyser.fftSize = size;
    freqData = new Uint8Array(analyser.frequencyBinCount);
  }

  function requestGraph() {
    if (graphRequested || !cfg.enabled || !window.audioGraph) return;
    graphRequested = true;
    window.audioGraph.request(attachAnalyser);
  }

  // Levels in the range 0..1, one per bar
  function levels() {
    var n = barCount();
    var out = new Array(n);
    var gain = Math.max(0.1, cfg.sensitivity || 1);
    if (!isRemote && analyser && audioCtx.state === "running") {
      analyser.getByteFrequencyData(freqData);
      // Use the lower ~80% of bins; the top ones are mostly empty for music.
      var usable = Math.floor(freqData.length * 0.8);
      for (var i = 0; i < n; i++) {
        var bin = Math.floor(i * usable / n);
        out[i] = Math.min(1, freqData[bin] / 255 * gain);
      }
      return out;
    }
    while (simulated.length < n) simulated.push(0.1);
    for (var j = 0; j < n; j++) {
      var target = 0.15 + Math.random() * 0.75 * (1 - j / (n * 1.4));
      simulated[j] += (target - simulated[j]) * 0.18;
      out[j] = Math.min(1, simulated[j] * gain);
    }
    return out;
  }

  /* ─────────────────────────────── Drawing ─────────────────────────────── */
  function drawBars(values, w, h, fromBottom) {
    var n   = values.length;
    var gap = Math.max(1, Math.min(4, (w / n) * 0.2));
    var barW = Math.max(1, (w - gap * (n - 1)) / n);
    for (var i = 0; i < n; i++) {
      var bh = Math.max(2, values[i] * h);
      var x  = i * (barW + gap);
      var y  = fromBottom ? h - bh : (h - bh) / 2;
      var r  = Math.min(barW / 2, 2);
      if (ctx.roundRect) {
        ctx.beginPath();
        ctx.roundRect(x, y, barW, bh, fromBottom ? [r, r, 0, 0] : r);
        ctx.fill();
      } else {
        ctx.fillRect(x, y, barW, bh);
      }
    }
  }

  // A smooth wave through the levels, filled down to the bottom edge.
  function drawLine(values, w, h) {
    var n = values.length;
    var pad = 1.5;
    var pts = [];
    for (var i = 0; i < n; i++) {
      pts.push([n === 1 ? w / 2 : i * w / (n - 1), h - pad - values[i] * (h - pad * 2)]);
    }
    ctx.beginPath();
    ctx.moveTo(pts[0][0], pts[0][1]);
    for (var j = 1; j < n; j++) {
      var mx = (pts[j - 1][0] + pts[j][0]) / 2;
      var my = (pts[j - 1][1] + pts[j][1]) / 2;
      ctx.quadraticCurveTo(pts[j - 1][0], pts[j - 1][1], mx, my);
    }
    ctx.lineTo(pts[n - 1][0], pts[n - 1][1]);
    ctx.lineWidth = 2;
    ctx.lineJoin = "round";
    ctx.stroke();
    ctx.lineTo(w, h);
    ctx.lineTo(0, h);
    ctx.closePath();
    ctx.globalAlpha = 0.35;
    ctx.fill();
    ctx.globalAlpha = 1;
  }

  function draw(values) {
    if (!ctx || !cfg.enabled) return;
    lastValues = values;
    var w = canvas.clientWidth  || 120;
    var h = canvas.clientHeight || 32;
    ctx.clearRect(0, 0, w, h);
    var c = color();
    ctx.fillStyle = c;
    ctx.strokeStyle = c;
    if (cfg.style === "line") drawLine(values, w, h);
    else drawBars(values, w, h, cfg.style === "bottom");
  }

  function drawFlat() {
    var zeros = [];
    for (var i = 0, n = barCount(); i < n; i++) zeros.push(0);
    draw(zeros);
  }

  // Redraw the last frame (after a resize or option change while paused).
  function redraw() {
    if (lastValues && lastValues.length === barCount()) draw(lastValues);
    else drawFlat();
  }

  /* ──────────────────────────────── Loop ───────────────────────────────── */
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
    if (!cfg.enabled) return;
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
      lastValues = null;
      drawFlat();
    },
    // Change options live, e.g. { placement: "background", bars: 48 }
    configure: function (opts) {
      for (var k in opts) cfg[k] = opts[k];
      place();
      sizeCanvas();
      sizeAnalyser();
      requestGraph();
      if (isPlaying && cfg.enabled) start();
      else { stopLoop(); redraw(); }
    },
    config: function () { return cfg; },
  };

  function init() {
    canvas = document.querySelector(".waveform");
    if (!canvas) return;
    place();
    sizeCanvas();
    drawFlat();
    requestGraph();
    if (window.ResizeObserver) {
      new ResizeObserver(function () {
        sizeCanvas();
        if (!rafId) redraw();
      }).observe(canvas);
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
