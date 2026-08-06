# Oxygen 6 Builder — a Claude Code skill

**Build and fix [Oxygen 6](https://oxygenbuilder.com/) pages by just asking, in plain English.**

> *"Build a Financing page with an intro, an FAQ accordion, and a contact button."*
>
> *"My page won't open in the builder anymore. Fix it."*
>
> *"Style the WooCommerce checkout to match my brand."*

This teaches [Claude Code](https://claude.com/claude-code) how Oxygen 6 actually works underneath, so it builds pages that open properly, match your design, and don't quietly break the rest of your site.

Not using Claude? It's also a written field guide and a small PHP toolbox you can use on your own — jump to [doing it yourself](#doing-it-yourself).

---

## Install

```bash
git clone https://github.com/buda-loco/Oxygen-skills.git
cp -R Oxygen-skills/oxygen-page-builder ~/.claude/skills/
```

That's it. The skill loads by itself whenever you're working on an Oxygen site — no command to remember.

*(Want it for one project only? Copy it into `your-project/.claude/skills/` instead.)*

---

## What's new

### August 2026

**One command now checks your whole site.** Ask Claude to verify, or run `./scripts/verify-site.sh` yourself. It opens every page and looks for the problems Oxygen doesn't warn you about: pages that won't open in the builder, images missing alt text, a page with no heading, brand styling that isn't really there. You get one answer — pass or fail — with the list.

It earns its keep. First time it ran on a real site it found three live bugs that every other check had passed, including a page shipping with no headings at all, and a set of hidden content types quietly publishing placeholder text to Google.

**Your design now goes in the builder's panels, not into custom CSS.** That's the difference between a site you can keep editing yourself and one where every small tweak needs a developer. The skill checks its own work as it builds and complains when it takes the lazy route. On a real site it moved 68 stray CSS rules back into the panels without changing a single pixel.

Three things that used to need workarounds:

- **Reusable sections with different text.** Build a testimonial block once, drop it on five pages, give each one its own words. Before, you had to duplicate the whole block.
- **Click, hover and scroll behaviour without code.** Toggles, tabs, show/hide, reveals. Claude builds these as real builder settings you can edit, instead of a lump of JavaScript.
- **Pulling in related content.** A testimonial showing its company's logo, a product showing its brand — following the link between them, no code snippet required.

Scroll-in animations behave now, too. They were replaying every time you scrolled past, and stayed invisible to screen readers and to Google until someone scrolled. Both fixed, with a drop-in file you can copy to any Oxygen site.

Last one: if you have [impeccable](https://impeccable.style) installed, Claude can run a design review of the finished page and tell you what looks generic — while leaving your brand's actual colours and fonts alone.

### July 2026

**Scroll animations, as a plugin.** [`plugins/oxs-parallax`](plugins/oxs-parallax) — add a class to any element and it drifts, scales or reveals as you scroll. Two modes site-wide, per-element overrides, and it stands down for visitors who've asked for reduced motion.

---

## What you can ask for

**Building pages**
- "Create an About page with a hero, three feature columns, and a contact form."
- "Make a 404 page and a search results page that match the rest of the site."
- "Turn this Word document into a page, with each heading and paragraph editable in the builder."

**When something's broken**
- "My page says *IO-TS decoding failed* and won't open. Fix it."
- "This CSS isn't applying to my WooCommerce cart and I can't see why."
- "The buttons on this page aren't clickable links — sort that out."

**Your shop**
- "Restyle the product page, cart and checkout to match my brand."
- "Put a filterable product grid on the category pages."

**Blog and dynamic content**
- "Turn my blog archive into an editable loop with clickable cards."
- "Add a 'latest posts' block to the bottom of every product page."

**Brand and polish**
- "Set my brand colours and fonts everywhere, so they show up in the builder's colour picker."
- "Check this page for accessibility and SEO problems, then fix them."

A guiding principle runs through all of it: **everything Claude builds stays editable by you in the visual builder.** No page ends up as a lump of code you can't click on.

---

## Why this is needed

**Oxygen 6 was rebuilt from scratch.** It's a completely different engine from Oxygen 3 and 4, which means most tutorials, code snippets and forum answers you'll find are now simply wrong — and AI assistants confidently repeat them.

**Your design lives in the database, not in files,** stored in a very particular format. Get one small detail wrong and the builder refuses to open the page — the infamous *"IO-TS decoding failed"* — even though the page still looks perfectly fine to visitors. That gap is what makes it so easy to break something without noticing.

**Many of Oxygen's failures are silent.** Brand CSS that matches nothing. Buttons that look like links but aren't. Images with no alt text. Nothing errors; it just quietly doesn't work.

So this skill was built the slow way: every shape, element and trap in it was **checked against a running Oxygen 6.1 install**, not guessed. The most valuable part isn't the how-to — it's the long list of things that fail silently, each with its fix.

---

## What's inside

The skill itself lives in [`oxygen-page-builder/`](oxygen-page-builder/).

| File | What it's for |
|---|---|
| [**SKILL.md**](oxygen-page-builder/SKILL.md) | Start here — the core rules and the build-then-check workflow. |
| [**RECIPES.md**](oxygen-page-builder/RECIPES.md) | Worked examples: pages, shop, cart, checkout, search, 404, blog, dynamic content. |
| [**GOTCHAS.md**](oxygen-page-builder/GOTCHAS.md) | The trap list — symptom, cause, fix. This is where the saved hours live. |
| [**PROPERTIES.md**](oxygen-page-builder/PROPERTIES.md) | The exact data shapes, for when you need to write them yourself. |
| [**ELEMENTS.md**](oxygen-page-builder/ELEMENTS.md) | All 165 built-in elements and what each one is for. |
| [**SEO.md**](oxygen-page-builder/SEO.md) | What WordPress gives you free, and a drop-in helper for the rest. |
| [**TYPOGRAPHY.md**](oxygen-page-builder/TYPOGRAPHY.md) | A ready-made type scale and spacing rhythm that adapt to screen size. |
| [**scripts/**](oxygen-page-builder/scripts/) | The PHP toolbox, the site checker, and worked example scripts. |
| [**assets/**](oxygen-page-builder/assets/) | Small drop-in files that repair known Oxygen bugs. Copy them into any site. |

And one standalone plugin:

| Plugin | What it does |
|---|---|
| [**oxs-parallax**](plugins/oxs-parallax) | Scroll-linked motion — drift, scale and reveal effects driven by a CSS class. Respects reduced-motion. |

---

## Doing it yourself

Everything Claude uses is a normal script you can run. If you'd rather write the code, the toolbox works anywhere wp-cli does:

```bash
export OXY_SITE_PATH="/path/to/your-site/app"                 # your WordPress site
./scripts/wp-eval.sh scripts/examples/build_blog_archive.php  # run a build script
./scripts/wp-eval.sh scripts/validate-tree.php <postId> fetch # check it before trusting it
./scripts/wp-eval.sh -- post list                             # any wp-cli command
```

Check the whole site at once:

```bash
./scripts/verify-site.sh                     # every page: will it open, is it accessible, is it findable
./scripts/verify-site.sh --builder --detect  # also open each page in the builder, and review the design
```

That check exists because **a page can look perfect to visitors and still be broken.** The builder is far pickier than the website, so a page can load fine for everyone and refuse to open for editing. Meanwhile nothing warns you about a missing alt text or a page with no heading. This looks for all of it and gives you one answer.

A whole page is a few lines:

```php
require '/path/to/oxygen-page-builder/scripts/lib.php';

$id = wp_insert_post(['post_title' => 'Financing', 'post_type' => 'page', 'post_status' => 'publish']);

oxy_write_tree($id, [
  oxy_div([
    oxy_text('FINANCING', 'h1', ['hero__title']),
    oxy_rich('<p>Intro paragraph…</p>', ['prose']),
    oxy_button('CONTACT US', '/contact/', ['btn']),
  ], ['container']),
]);
```

> **The one rule worth remembering:** the website is forgiving, the builder is strict. Never invent a format from memory — use the toolbox, copy one the builder itself saved, and run the check before you call it done.

---

## Requirements

- WordPress running **Oxygen 6.1** or newer
- **wp-cli** and **PHP 8.x**
- Optional: [Local](https://localwp.com/) for local development — the toolbox already knows its quirks
- Optional: Node and Chrome, only for the builder check and the design review

---

## Good to know

- **This came out of a real production store,** so the examples use made-up post IDs and a placeholder brand ("Acme", teal and slate, Oswald/Inter). Yours will differ — treat them as patterns, not settings to copy.
- **It's pinned to Oxygen 6.1.** Later versions may shift things. If something looks off, build one example in your own builder and compare.
- **Always check before trusting a change.** Run `verify-site.sh`, then open the page in the builder. Both take seconds, and between them they catch nearly everything.
- **Keep your own project notes.** This deliberately ships without them — a short file in *your* repo listing your template IDs and setup will save future-you a lot of digging.

---

## Contributing

Corrections, new recipes and newly-discovered traps are very welcome — especially for Oxygen versions past 6.1. Please mention the exact version you tested against.

## Support this

This is free, and it stays free. It exists because I kept breaking my own Oxygen sites and got tired of guessing why — so every trap in here cost me an afternoon before it saved you one.

If it saved you one of those afternoons, you can [buy me a coffee](https://buymeacoffee.com/benarnedo). Entirely optional, genuinely appreciated.

<a href="https://buymeacoffee.com/benarnedo"><img src="https://img.shields.io/badge/Buy%20me%20a%20coffee-benarnedo-FFDD00?style=flat-square&logo=buymeacoffee&logoColor=black" alt="Buy me a coffee"></a>

Starring the repo helps too, and costs nothing but a click.

## Author

Created by **Benjamin Arnedo**.

## License

MIT — © 2026 Benjamin Arnedo. See [LICENSE](LICENSE).
