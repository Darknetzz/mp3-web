#!/usr/bin/env python3
"""Generate mp3-web branding SVGs. Text is converted to paths (Lato, OFL).
Usage: python3 build.py assets/branding  (then render PNGs with rsvg-convert)"""
import math, sys, os
from fontTools.ttLib import TTFont
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen

OUT = sys.argv[1]
GREEN = "#2fb344"
GREEN_DARK = "#24913a"
INK = "#ffffff"
BG = "#151b23"
BG2 = "#1d2633"
MUTED = "#9aa7b8"

_fonts = {}
def font(w):
    if w not in _fonts:
        _fonts[w] = TTFont(f"/usr/share/fonts/truetype/lato/Lato-{w}.ttf")
    return _fonts[w]

def text_path(s, weight, size, x, y, anchor="start"):
    """Return (svg path d, width). y is the baseline."""
    f = font(weight)
    gs = f.getGlyphSet(); cmap = f.getBestCmap(); hmtx = f["hmtx"]
    upm = f["head"].unitsPerEm; k = size / upm
    names = [cmap[ord(c)] for c in s]
    width = sum(hmtx[n][0] for n in names) * k
    if anchor == "middle": x -= width / 2
    pen = SVGPathPen(gs, ntos=lambda v: f"{v:.1f}".rstrip("0").rstrip("."))
    cx = 0
    for n in names:
        tp = TransformPen(pen, (k, 0, 0, -k, x + cx * k, y))
        gs[n].draw(tp)
        cx += hmtx[n][0]
    return pen.getCommands(), width

def mark(glyph=INK, tile=GREEN, simple=False):
    """Logo mark in a 512x512 box: waveform bars shaped like a play
    triangle, with signal arcs radiating from its tip."""
    p = [f'<rect width="512" height="512" rx="112" fill="{tile}"/>']
    # A play triangle sliced vertically into waveform bars.
    if simple:  # favicon: fewer, chunkier pieces
        x0, tip, half, gap, nbars, arcs, sw = 104, 318, 150, 58, 2, [104], 50
    else:
        x0, tip, half, gap, nbars, arcs, sw = 100, 300, 144, 38, 3, [70, 124], 34
    r = 10  # corner softening
    x0 += r; tip -= r * 2; half -= r * 1.6
    slice_w = (tip - x0 - gap * (nbars - 1)) / nbars
    top = lambda x: 256 - half * (tip - x) / (tip - x0)
    bot = lambda x: 256 + half * (tip - x) / (tip - x0)
    for i in range(nbars):
        xa = x0 + i * (slice_w + gap); xb = xa + slice_w
        pts = [(xa, top(xa)), (xb, top(xb)), (xb, bot(xb)), (xa, bot(xa))]
        if i == nbars - 1:
            pts = [(xa, top(xa)), (xb, 256), (xa, bot(xa))]
        pts = " ".join(f"{x:.1f},{y:.1f}" for x, y in pts)
        p.append(f'<polygon points="{pts}" fill="{glyph}" stroke="{glyph}" stroke-width="{r*2}" stroke-linejoin="round"/>')
    tip += r * 2
    a = math.radians(48)
    for r in arcs:
        x1, y1 = tip + r * math.cos(-a), 256 + r * math.sin(-a)
        x2, y2 = tip + r * math.cos(a), 256 + r * math.sin(a)
        p.append(f'<path d="M{x1:.1f} {y1:.1f}A{r} {r} 0 0 1 {x2:.1f} {y2:.1f}" fill="none" stroke="{glyph}" stroke-width="{sw}" stroke-linecap="round"/>')
    return "".join(p)

def svg(w, h, body, title):
    return (f'<svg xmlns="http://www.w3.org/2000/svg" width="{w}" height="{h}" viewBox="0 0 {w} {h}" role="img" aria-label="{title}">'
            f'<title>{title}</title>{body}</svg>\n')

def deco_bars(x0, x1, cy, maxh, bw, gap, opacity, seed=7):
    """Faint decorative waveform across a span."""
    out = []; i = 0; x = x0
    while x + bw <= x1:
        t = i * 0.37 + seed
        h = maxh * (0.25 + 0.75 * abs(math.sin(t) * math.cos(t * 0.53 + 1.1)))
        out.append(f'<rect x="{x:.1f}" y="{cy - h/2:.1f}" width="{bw}" height="{h:.1f}" rx="{bw/2}" fill="{GREEN}" opacity="{opacity}"/>')
        x += bw + gap; i += 1
    return "".join(out)

def lockup(w, h, logo, name_size, tag_size, tagline, cx=None, center=False):
    """Logo + name + tagline. Returns svg body pieces."""
    parts = []
    name_l, wl = text_path("mp3", "Black", name_size, 0, 0)
    name_r, wr = text_path("-web", "Black", name_size, 0, 0)
    tag, wt = text_path(tagline, "Regular", tag_size, 0, 0)
    gap = logo * 0.22
    text_w = max(wl + wr, wt)
    total = logo + gap + text_w
    x = (w - total) / 2 if center else (cx if cx is not None else 0)
    ly = (h - logo) / 2
    parts.append(f'<g transform="translate({x:.1f} {ly:.1f}) scale({logo/512})">{mark()}</g>')
    tx = x + logo + gap
    # vertical: name cap block + tagline, centered on logo
    block = name_size * 0.72 + tag_size * 1.9
    top = h / 2 - block / 2
    base1 = top + name_size * 0.72
    base2 = base1 + tag_size * 1.9
    d1, _ = text_path("mp3", "Black", name_size, tx, base1)
    d2, _ = text_path("-web", "Black", name_size, tx + wl, base1)
    d3, _ = text_path(tagline, "Regular", tag_size, tx, base2)
    parts.append(f'<path d="{d1}" fill="{INK}"/><path d="{d2}" fill="{GREEN}"/><path d="{d3}" fill="{MUTED}"/>')
    return "".join(parts), x, x + total

TAG = "Self-hosted music player for your local library, with casting"

os.makedirs(OUT, exist_ok=True)
open(f"{OUT}/logo.svg", "w").write(svg(512, 512, mark(), "mp3-web"))
open(f"{OUT}/logo-favicon.svg", "w").write(svg(512, 512, mark(simple=True), "mp3-web"))

# Banner: self-contained dark card so it reads the same on GitHub light & dark.
W, H = 1280, 320
body, lx0, lx1 = lockup(W, H, 168, 92, 27, TAG, cx=72)
grad = (f'<defs><linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">'
        f'<stop offset="0" stop-color="{BG2}"/><stop offset="1" stop-color="{BG}"/></linearGradient></defs>')
card = f'<rect width="{W}" height="{H}" rx="24" fill="url(#bg)"/>'
deco = deco_bars(lx1 + 56, W - 64, H / 2, 150, 8, 8, 0.22)
open(f"{OUT}/banner.svg", "w").write(svg(W, H, grad + card + deco + body, "mp3-web — " + TAG))

# Social preview 1280x640 (full bleed; GitHub crops corners on some surfaces).
W, H = 1280, 640
body, lx0, lx1 = lockup(W, H, 220, 120, 31, TAG, center=True)
grad = (f'<defs><linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">'
        f'<stop offset="0" stop-color="{BG2}"/><stop offset="1" stop-color="{BG}"/></linearGradient></defs>')
card = f'<rect width="{W}" height="{H}" fill="url(#bg)"/>'
deco = deco_bars(64, W - 64, H - 70, 60, 8, 8, 0.25) + deco_bars(64, W - 64, 70, 60, 8, 8, 0.12, seed=3)
open(f"{OUT}/social-preview.svg", "w").write(svg(W, H, grad + card + deco + body, "mp3-web — " + TAG))
print("ok")
