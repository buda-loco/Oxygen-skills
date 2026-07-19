# Oxygen 6 Builder — a Claude Code skill

**Build and fix [Oxygen 6](https://oxygenbuilder.com/) pages by just asking, in plain English.**

> *"Build a Financing page with an intro, an FAQ accordion, and a contact button."*
>
> *"My page won't open in the builder anymore. Fix it."*
>
> *"Style the WooCommerce checkout to match my brand."*

This teaches [Claude Code](https://claude.com/claude-code) how Oxygen 6 actually works underneath, so it builds pages that open properly, match your design, and don't quietly break the rest of your site.

Not using Claude? It's also a written field guide and a small PHP toolbox you can use on your own — jump to [working by hand](#working-by-hand).

---

## Install

```bash
git clone https://github.com/buda-loco/Oxygen-skills.git
cp -R Oxygen-skills/oxygen-page-builder ~/.claude/skills/
```

That's it. The skill loads by itself whenever you're working on an Oxygen site — no command to remember.

*(Want it for one project only? Copy it into `your-project/.claude/skills/` instead.)*

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

Everything lives in [`oxygen-page-builder/`](oxygen-page-builder/).

| File | What it's for |
|---|---|
| [**SKILL.md**](oxygen-page-builder/SKILL.md) | Start here — the core rules and the build-then-verify workflow. |
| [**RECIPES.md**](oxygen-page-builder/RECIPES.md) | Worked examples: pages, shop, cart, checkout, search, 404, blog, dynamic content. |
| [**GOTCHAS.md**](oxygen-page-builder/GOTCHAS.md) | The trap list — symptom, cause, fix. This is where the saved hours live. |
| [**PROPERTIES.md**](oxygen-page-builder/PROPERTIES.md) | The exact data shapes, for when you need to write them yourself. |
| [**ELEMENTS.md**](oxygen-page-builder/ELEMENTS.md) | All 165 built-in elements and what each one is for. |
| [**SEO.md**](oxygen-page-builder/SEO.md) | What WordPress gives you free, and a drop-in helper for the rest. |
| [**scripts/**](oxygen-page-builder/scripts/) | The PHP toolbox, a validator, and a folder of worked example scripts. |

---

## Working by hand

If you'd rather write the code yourself, the toolbox runs anywhere wp-cli does:

```bash
export OXY_SITE_PATH="/path/to/your-site/app"                 # your WordPress site
./scripts/wp-eval.sh scripts/examples/build_blog_archive.php  # run a build script
./scripts/wp-eval.sh scripts/validate-tree.php <postId> fetch # check it before trusting it
./scripts/wp-eval.sh -- post list                             # any wp-cli command
```

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

> **The one rule worth remembering:** the website is forgiving, the builder is strict. A page can look perfect to visitors and still refuse to open for editing. So never invent a shape from memory — use the toolbox, copy one the builder itself saved, and run the validator before you call it done.

---

## Requirements

- WordPress running **Oxygen 6.1** or newer
- **wp-cli** and **PHP 8.x**
- Optional: [Local](https://localwp.com/) for local development — the toolbox already knows its quirks

---

## Good to know

- **This came out of a real production store,** so the examples use made-up post IDs and a placeholder brand ("Acme", teal and slate, Oswald/Inter). Yours will differ — treat them as patterns, not settings to copy.
- **It's pinned to Oxygen 6.1.** Later versions may shift things. If something looks off, build one example in your own builder and compare.
- **Always verify before trusting a write.** Run the validator, then open the page in the builder. Both take seconds.
- **Keep your own project notes.** This deliberately ships without them — a short file in *your* repo listing your template IDs and setup will save future-you a lot of digging.

---

## Contributing

Corrections, new recipes and newly-discovered traps are very welcome — especially for Oxygen versions past 6.1. Please mention the exact version you tested against.

## Author

Created by **Benjamin Arnedo**.

## License

MIT — © 2026 Benjamin Arnedo. See [LICENSE](LICENSE).
