# oxs-parallax

Portable scroll-linked parallax for WordPress (built for Oxygen 6 sites, works with any markup).
Drop the folder into `wp-content/plugins/`, activate, done — no settings page by design.

## Usage

1. Add the class **`oxs-plx`** to any element (Oxygen: class field, or `settings.advanced.classes`
   in programmatic builds).
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
- **`prefers-reduced-motion: reduce` disables everything** in both engines.
- Modern browsers: pure CSS scroll-driven animation (compositor, the JS does zero work).
  Older browsers: IntersectionObserver-gated rAF fallback writing `el.style.translate`.
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
