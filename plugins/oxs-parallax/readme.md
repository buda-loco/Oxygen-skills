# oxs-parallax

Portable scroll-linked parallax for WordPress (built for Oxygen 6 sites, works with any markup).
Drop the folder into `wp-content/plugins/`, activate, done.

## Modes (Settings → Parallax)

One site-wide default, switchable in the admin, plus a per-element override:

| Mode | Behavior |
|---|---|
| **off** *(default on fresh installs)* | Parallax disabled everywhere; elements stay static. |
| **scroll-driven** | Transform is scrubbed to scroll position and **reverses** when you scroll back up. |
| **one-off** | Each element reveals in **once** when it enters the viewport, then **holds** (no rewind). |

**Per-element override** — wins over the global default: add `oxs-plx--off`, `oxs-plx--scroll`,
or `oxs-plx--oneoff` alongside `oxs-plx`. (E.g. keep the site *off* but opt one hero into scroll.)

The global default is emitted as a class on `<html>` (`oxs-plx-mode-off|scroll|oneoff`) from an
early `<head>` script, so it's set before first paint. `off` is the shipping default so the plugin
never animates anything until an admin (or a class) opts in.

Note: `oxs-plx--grow` (scale-up) is a scroll-transit effect — in one-off mode it settles to rest
(scale 1) and shows no growth. `oxs-plx--zoom` works in both (one-off = a zoom-in that settles).

## Usage

1. Add the class **`oxs-plx`** to any element (Oxygen: class field, or `settings.advanced.classes`
   in programmatic builds), and pick a mode in Settings → Parallax (or an override class).
2. Tune with CSS custom properties — **lengths with units** (unitless values silently disable
   the CSS engine):

   ```css
   --plx-from: 60px;   /* translate when the element ENTERS the viewport (bottom) */
   --plx-to:  -60px;   /* translate when it EXITS (top) — defaults ±40px */
   ```

   Set them via a preset class, inline `style="--plx-from:120px;--plx-to:-120px"`,
   your stylesheet, or **Oxygen Variables** (they compile to CSS custom properties).

3. Presets: `oxs-plx--slow` (±24px) · `oxs-plx--fast` (±80px) · `oxs-plx--reverse` (down instead
   of up; combine `oxs-plx--fast oxs-plx--reverse` for a fast downward drift) ·
   **`oxs-plx--zoom`** (scale 1.18→1, no translate — for full-bleed hero images/videos) ·
   **`oxs-plx--grow`** (scale 1→1.12 — portraits/cards that swell as you scroll past).

   **Scale channel**: `--plx-scale-from` / `--plx-scale-to` (unitless) animate the `scale`
   property alongside `translate` — both composited. Hero-media zoom is the intended use;
   keep scale off small text (blurry rasterization mid-animation).

## Guarantees / constraints

- Animates the **`translate` property** — your existing `transform: rotate()/scale()` is untouched.
- **Vertical-only** by design (can never cause horizontal scrollbars).
- **`prefers-reduced-motion: reduce` disables everything** in every mode.
- **scroll** mode: pure CSS scroll-driven animation on modern browsers (compositor, JS does zero
  work); IntersectionObserver-gated rAF fallback elsewhere. **one-off** mode: an IntersectionObserver
  adds `.oxs-plx--in` once and a CSS transition does the reveal (all browsers). **off**: nothing runs.
- Intended for **decorative elements** (watermarks, dot grids, framed photos) — not large hero
  images (paint cost) and not elements inside transformed carousel tracks.
- Sections whose decoratives overhang should keep `overflow: hidden` (the movement extends
  an element's effective footprint by the var range).
- Dynamically injected elements: call `window.oxsPlxRefresh()` (fallback engine only;
  the CSS engine picks new elements up automatically).

## Testing

Open `test.html` in a browser (and again with `?fallback=1`) and follow the protocol in its
source comment: default/preset/inline-precedence travel, rotate-coexistence, forced-fallback
parity, injected-element refresh, reduced-motion, resize recollection.

## ⚠️ The overflow trap (learned in production)

`overflow: hidden` on ANY ancestor **creates a scroll container**, and `view()` timelines
track the *nearest scroll container* — a never-scrolling `overflow:hidden` section freezes
the animation at one value (element just sits offset, doesn't move). Fix on every section
that hosts a `oxs-plx` element:

```css
.my-section{ overflow:hidden; overflow:clip; }  /* clip clips the same but creates NO scroll container */
```

Old browsers ignore `clip`, keep `hidden`, and use the JS fallback (which computes against
the viewport and is immune). The bundled `test.html` can't catch this — it has no wrapped
sections — so verify on real pages by scrolling and watching `getComputedStyle(el).translate`.
