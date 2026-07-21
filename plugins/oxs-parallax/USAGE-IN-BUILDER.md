# oxs-parallax — using it in the Oxygen builder

A dev reference for adding scroll effects **without writing code** — everything is class-driven,
so you add classes in the builder and pick a global mode in the admin.

---

## 1. Pick the global mode (once, in the admin)

**WordPress admin → Settings → Parallax.** One radio sets the site-wide default for every effect:

| Mode | What every effect does |
|---|---|
| **Off** *(default on fresh installs)* | Nothing animates; all content is static and visible. |
| **Scroll-driven** | Motion is **scrubbed to the scroll position** and **reverses when you scroll back up** — your scroll is a playhead. |
| **One-off** | Each element animates **once** when it enters the viewport, then **holds** (no rewind). |

You can override the mode on any single element with a class (see §4).

---

## 2. Parallax — move / scale an element as you scroll

In the Oxygen builder, select an element → **Advanced → CSS Classes** (or the class field) and add:

```
oxs-plx  oxs-plx--slow
```

`oxs-plx` turns it on; the second class is a **preset**:

| Preset | Effect |
|---|---|
| `oxs-plx--slow` | gentle vertical drift (±24px) |
| `oxs-plx--fast` | strong vertical drift (±80px) — big watermarks |
| `oxs-plx--reverse` | drift downward instead of up (combine with `--fast`) |
| `oxs-plx--zoom` | scale 1.18 → 1 — full-bleed hero **images/videos** |
| `oxs-plx--grow` | scale 1 → 1.12 — portraits/cards that swell (scroll-mode only) |

**Fine-tune** with CSS custom properties (element style field, a class, or an **Oxygen Variable**):

```
--plx-from: 60px;        /* offset as it enters (needs a unit) */
--plx-to:  -60px;        /* offset as it exits */
--plx-scale-from: 1.1;   /* optional scale channel (unitless) */
--plx-scale-to: 1;
```

> Use parallax on **decorative** elements (watermarks, dot grids, framed photos, hero media) —
> not body text.

---

## 3. Reveals — animate an element in as it enters view

Add `oxs-reveal` plus a **direction**:

```
oxs-reveal  oxs-reveal--up
```

| Direction | Reveals from |
|---|---|
| `oxs-reveal--up` / `--down` | below / above |
| `oxs-reveal--left` / `--right` | the left / right |
| `oxs-reveal--fade` | opacity only |
| `oxs-reveal--zoom` | scales up into place |

In **scroll** mode the reveal is a playhead (scrubs in, reverses on scroll-up); in **one-off** it
plays once and holds; in **off** the element is just visible.

**Completion offset** — where in the viewport the reveal FINISHES as you scroll (scroll mode).
A higher finish point = the animation plays out over more of the scroll and is easier to see.
Add one of:

| Class | Finishes | Feel |
|---|---|---|
| `oxs-reveal--top` | near the top of the viewport | longest, most visible |
| `oxs-reveal--mid` | at centre — **the default** (no class needed) | balanced |
| `oxs-reveal--bottom` | a bit below centre | shorter, still visible |

Custom: set `--rv-at` yourself (e.g. `style="--rv-at:65%"`) for any finish point between ~20% and
90% of the viewport. (Default is 50% = centre, so short elements still animate where you can see it.)

**Stagger a group** (columns, cards, logos): add an increasing step class to each sibling —

```
card 1:  oxs-reveal oxs-reveal--up oxs-reveal--d1
card 2:  oxs-reveal oxs-reveal--up oxs-reveal--d2
card 3:  oxs-reveal oxs-reveal--up oxs-reveal--d3   (…up to --d10)
```

`--d1…--d10` offset the scroll window (scroll mode) and the transition delay (one-off mode).

---

## 4. Override the mode on one element

Any element can ignore the global default:

```
oxs-plx--off  |  oxs-plx--scroll  |  oxs-plx--oneoff       (for parallax)
oxs-reveal--off  |  oxs-reveal--scroll  |  oxs-reveal--oneoff   (for reveals)
```

E.g. keep the whole site **off** but opt one hero into `oxs-plx oxs-plx--zoom oxs-plx--scroll`.

---

## 5. The one gotcha — `overflow`

`view()` scroll animations break if an **ancestor** uses `overflow: hidden` (it silently becomes a
scroll container and freezes the effect at one frame). On any section that *contains* an `oxs-plx`
or `oxs-reveal` element and needs clipping, chain `clip`:

```css
.my-section { overflow: hidden; overflow: clip; }   /* clips the same, but no scroll container */
```

Old browsers ignore `clip`, keep `hidden`, and fall back to the JS engine (immune to the trap).

---

## 6. Accessibility

`prefers-reduced-motion: reduce` disables **every** mode automatically — content is shown static,
never hidden. Reveals are fail-safe by design: if the script fails to load, elements stay visible.
