# Typography & spacing — the DEFAULT type system for every build

**Use this by default** whenever you set up text, headings, buttons, forms, or section spacing
in an Oxygen build. It is a design-token system: define the tokens ONCE in the global stylesheet
(the HEADER template's CssCode node → `post-<id>.css`), then every text/space property references
a token. Consolidating to tokens is the whole point — changing one value reflows the entire site,
and the scale stays auditable.

Distilled from two sources + one full production build:
- **Responsive modular scale** (medium.com sketch-app-sources *Exploring responsive type scales*):
  use a **dual ratio** — a gentler ratio on mobile so headings stay legible, a larger ratio on
  desktop so they don't get "lost". Pick the ratio by archetype: **marketing = high**, blog = medium,
  product = low.
- **The typographic scale** (retinart.net, after Bringhurst *Elements of Typographic Style*): the
  classic scale `6·7·8·9·10·11·12·14·16·18·21·24·36·48·60·72` is **irregular on purpose** and is a
  **guide, not a formula** — taste beats math. Two rules that matter: **skip steps for hierarchy**
  (adjacent sizes read as a mistake), and **space is co-equal with size** (leading + section spacing
  must be as systematic as the type).

## The scale (paste into `:root` in the global CssCode node)

Fluid `clamp(min, calc(vw + rem), max)` per step interpolates the ratio between a ~380px and
~1280px viewport — so the ratio literally grows with the screen (mobile Minor Third 1.2 → desktop
Perfect Fourth 1.333, base 16→18px). Tune two numbers (the ratios) and everything follows.

```css
:root{
  /* type scale — steps: meta(−2) body-sm(−1) body(0) lead(+1) h4(+2) h3(+3) h2(+4) h1(+5) display(+7) */
  --fs-meta:     0.8125rem;                                    /* 13px — FLAT: small text must not shrink on desktop */
  --fs-body-sm:  0.9375rem;                                    /* 15px — FLAT */
  --fs-body:     clamp(1rem,    0.22vw + 0.95rem, 1.125rem);   /* 16 → 18 */
  --fs-lead:     clamp(1.2rem,  0.53vw + 1.07rem, 1.5rem);     /* 19 → 24 */
  --fs-h4:       clamp(1.44rem, 1.00vw + 1.20rem, 2rem);       /* 23 → 32 */
  --fs-h3:       clamp(1.72rem, 1.68vw + 1.33rem, 2.67rem);    /* 28 → 43 */
  --fs-h2:       clamp(2.07rem, 2.63vw + 1.45rem, 3.56rem);    /* 33 → 57 */
  --fs-h1:       clamp(2.49rem, 4.00vw + 1.54rem, 4.75rem);    /* 40 → 76 */
  --fs-display:  clamp(3.58rem, 8.63vw + 1.53rem, 8.4rem);     /* 57 → 135 — hero wordmarks */
  --fs-quote:    clamp(1.6rem,  1.42vw + 1.26rem, 2.4rem);     /* pull quotes (+3.5) */
  --fs-watermark:clamp(3.5rem,  13vw, 11rem);                  /* decorative, deliberately off-scale */
  /* line-height: tighter as size grows (Retinart ~120% default, less for display) */
  --lh-tight:.95; --lh-head:1.1; --lh-snug:1.28; --lh-body:1.6;
  /* weight — set to your family's real weights */
  --fw-book:450; --fw-bold:900;
  /* tracking */
  --ls-tight:-.03em; --ls-head:-.01em; --ls-label:.08em;
  /* text colors — consolidate every grey/white-alpha to these */
  --tc-heading:var(--c-ink); --tc-body:#2b2733; --tc-muted:#5b5666;
  --tc-on-purple:#fff; --tc-on-purple-muted:rgba(255,255,255,.9); --tc-on-purple-faint:rgba(255,255,255,.72);

  /* ===== SPACING RHYTHM (8px base; space co-equal with size) ===== */
  --sp-3xs:.25rem; --sp-2xs:.5rem; --sp-xs:.75rem; --sp-sm:1rem; --sp-md:1.5rem;
  --sp-lg:2.5rem;  --sp-xl:4rem;   --sp-2xl:6rem;  --sp-3xl:8rem;
  --sp-section:   clamp(3.5rem, 7vw, 6.5rem);    /* standard section vertical padding (56→104) */
  --sp-section-lg:clamp(5rem, 10vw, 8.5rem);     /* generous / hero-adjacent (80→136) */
  --sp-gap:       clamp(2rem, 4vw, 4rem);         /* major two-column / grid gap */
  --sp-gap-sm:    clamp(1.25rem, 2.5vw, 2.25rem); /* card grid gap */
}
```

## Canonical utility classes (the catalog + a ready handle)

Add these so any element can adopt a scale step directly, and so there is one place to read the
scale. Existing BEM classes should set the SAME tokens (don't invent new sizes).

```css
.t-display{font-size:var(--fs-display);line-height:var(--lh-tight);font-weight:var(--fw-bold);letter-spacing:var(--ls-tight);margin:0;}
.t-h1{font-size:var(--fs-h1);line-height:var(--lh-head);font-weight:var(--fw-book);letter-spacing:var(--ls-head);color:var(--tc-heading);margin:0;}
.t-h2{font-size:var(--fs-h2);line-height:var(--lh-head);font-weight:var(--fw-book);letter-spacing:var(--ls-head);color:var(--tc-heading);margin:0;}
.t-h3{font-size:var(--fs-h3);line-height:var(--lh-snug);font-weight:var(--fw-bold);color:var(--tc-heading);margin:0;}
.t-h4{font-size:var(--fs-h4);line-height:var(--lh-snug);font-weight:var(--fw-bold);color:var(--tc-heading);margin:0;}
.t-lead{font-size:var(--fs-lead);line-height:var(--lh-snug);font-weight:var(--fw-book);margin:0;}
.t-quote{font-size:var(--fs-quote);line-height:var(--lh-snug);font-style:italic;margin:0;}
.t-body{font-size:var(--fs-body);line-height:var(--lh-body);color:var(--tc-body);}
.t-body-sm{font-size:var(--fs-body-sm);line-height:var(--lh-body);color:var(--tc-muted);}
.t-meta{font-size:var(--fs-meta);line-height:var(--lh-snug);color:var(--tc-muted);}
.t-label{font-size:var(--fs-meta);font-weight:var(--fw-bold);text-transform:uppercase;letter-spacing:var(--ls-label);}
```

## Role → step map (assign every text element one step)

| Role | Step / token | Tag | Notes |
|---|---|---|---|
| Hero wordmark / logotype | `--fs-display` | `h1` (or sr-only h1 + visual) | bold, tight |
| Page hero title | `--fs-h1` | `h1` | one per page |
| Section heading | `--fs-h2` | `h2` | |
| Card / subsection name | `--fs-h3` | `h3` | |
| Small heading / eyebrow's sibling | `--fs-h4` | `h4` | |
| Uppercase eyebrow/label | `--fs-meta` + `--ls-label` | `div` (not a heading) | it's a label, not document structure |
| Intro / lead paragraph | `--fs-lead` | `p` | |
| Pull quote | `--fs-quote` | `blockquote`/`div` | italic |
| Body / prose | `--fs-body` | `p` | 17–18px on desktop |
| Secondary body | `--fs-body-sm` | `p`/`div` | |
| Meta / role / nav / caption | `--fs-meta` | `a`/`div` | FLAT — never shrinks |
| Button / input | `--fs-body` | control | em-relative padding so it scales with the token |
| Giant background watermark | `--fs-watermark` | `div` | decorative, off-scale |

## Rules that make it beautiful (not just consistent)

1. **Every text element gets exactly one scale step.** After a build, audit: 0 literal `font-size`
   on text should remain. Only genuine controls (buttons/inputs at a token) and decorative
   watermarks live outside the reading scale. (Audit: grep the compiled CSS for `font-size:` values
   that aren't `var(--fs-*)`.)
2. **Semantic tags = the scale, not the size.** One `<h1>` per page; `h2` sections; `h3` cards.
   Headings needing inline emphasis (`Fran <em>Pérez</em>`) → an `oxy_html('<h2 …>…</h2>')` node
   (real tag) rather than a `RichText` div. Uppercase eyebrows stay `div` (label, not structure).
3. **Skip steps for hierarchy.** Body → h4 → h3 → h2 is one modular step each; that gap is what
   reads as hierarchy. Never differentiate two levels by a hair.
4. **Small text is FLAT.** Sub-body steps (`--fs-meta`, `--fs-body-sm`) do NOT grow with the
   viewport — a growing desktop ratio would shrink them below legibility. Only body-and-up scale.
5. **Colors are tokens too.** Kill raw greys (`#4a4650`, `#54505c`…) → `--tc-muted`; kill
   white-at-N-alphas → `--tc-on-purple-muted/faint`. Headings `--tc-heading`, body `--tc-body`.
6. **Space is systematic.** Section padding = `--sp-section`/`--sp-section-lg`; grid gaps =
   `--sp-gap`/`--sp-gap-sm`/`--sp-lg`; heading→body and paragraph margins = `--sp-sm`/`--sp-md`.
   Line-height tightens as size grows (`--lh-body` 1.6 for reading, `--lh-head` 1.1 for headings).
7. **Retune from the top.** To change the whole feel, edit the two ratios (mobile/desktop) or the
   base, not 50 rules. That's the token system paying off.

## Applying it in an Oxygen build (fits the CSS ladder)

- Tokens + `.t-*` + base element classes live in the **global CssCode node** (rung 2 of the CSS
  ladder). Page-local `oxy_css()` decoration references the same tokens — never re-declares a size.
- Set Global Settings fonts/palette too, but the token stylesheet is the source of truth for the
  scale (Global Settings only drives the builder's pickers).
- Verify: (1) grep compiled CSS — sizes resolve to `--fs-*`, no stray greys; (2) a11y outline —
  exactly one `h1`, sensible `h1→h2→h3` (FAQ questions correctly nest as `h3`); (3) eyeball the
  rhythm at 375px and 1440px — headings should feel punchy on desktop, calm on mobile.
