# Oxygen 6 Builder — a Claude Code skill (+ field guide)

**Build and edit [Oxygen 6](https://oxygenbuilder.com/) WordPress pages with plain English — or with a few lines of PHP — instead of clicking around the visual builder.**

This is a [Claude Code](https://claude.com/claude-code) skill that teaches the AI how Oxygen 6 *actually* stores a page, so it can create pages, fix broken layouts, and match your brand without guessing. It's also a standalone reference (docs + a small PHP toolbox) you can use on your own.

---

## Why this exists

Oxygen 6 is a **complete rewrite** on the Breakdance engine. Every old Oxygen tutorial, snippet, and StackOverflow answer (`ct_section`, `ct_id`, shortcodes) is now **wrong**. Worse: a page's design lives in a database field as a strict data structure, and the builder silently refuses to open anything shaped even slightly wrong ("IO-TS decoding failed").

So AI assistants — and humans — tend to *guess*, and quietly break things.

This skill removes the guessing. Everything in it was **verified against the live plugin (v6.1.0)**: the exact shapes the builder accepts, the full element list, and — the most valuable part — a long list of traps that fail silently, with the fix for each.

---

## Quick start (with Claude Code)

```bash
git clone https://github.com/buda-loco/Oxygen-skills.git
cp -R Oxygen-skills/oxygen-page-builder ~/.claude/skills/     # all projects
# …or into <your-project>/.claude/skills/ for one project
```

That's it. Next time you're working on an Oxygen site, just ask — the skill loads automatically.

### Things you can now ask Claude

- *"Build a Financing page in Oxygen with an intro, an FAQ accordion, and a contact button."*
- *"My page won't open in the builder — it says IO-TS decoding failed. Fix it."*
- *"Style the WooCommerce cart and checkout to match my brand."*
- *"Turn my blog archive into a native, editable loop with clickable cards."*
- *"This CSS isn't applying to a WooCommerce element — why?"* (spoiler: it's [a famous trap](oxygen-page-builder/GOTCHAS.md))

---

## Prefer to work by hand?

The docs are a plain field guide, and the PHP toolbox works from any wp-cli context:

```bash
export OXY_SITE_PATH="/path/to/your-site/app"                 # your WP/Local site
./scripts/wp-eval.sh scripts/examples/build_blog_archive.php  # run a build script
./scripts/wp-eval.sh scripts/validate-tree.php <postId> fetch # ALWAYS validate after a write
./scripts/wp-eval.sh -- post list                            # any wp-cli command
```

Building a page is a few lines:

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
// then: validate-tree.php <id> fetch  →  open ?oxygen=builder&id=<id>
```

> **The one rule that saves you:** the front-end renderer is forgiving, but the builder is strict. A page can look fine on the site yet refuse to open in the builder. Never hand-guess a shape — use the toolbox, `oxy_golden()`, or copy a shape the builder itself saved. (More in [`SKILL.md`](oxygen-page-builder/SKILL.md).)

---

## What's inside

Everything lives in [`oxygen-page-builder/`](oxygen-page-builder/):

| File | What it gives you |
|---|---|
| **SKILL.md** | Start here. The golden rules, the toolbox, the build-and-verify workflow, and the "general → specific" approach to CSS & templates. |
| **RECIPES.md** | Copy-paste recipes: pages, WooCommerce product & shop pages, cart/checkout/account, search, 404, blog, native loops, dynamic data, images, footer rebuilds. |
| **GOTCHAS.md** | The trap list — symptom → cause → fix. This is where the hours-saved live. |
| **PROPERTIES.md** | Exact write-shapes: the page tree, style selectors, Global Settings (colors/fonts/width), templates, per-element keys, responsive breakpoints. |
| **ELEMENTS.md** | All 165 native elements and where to find each one's source. |
| **SEO.md** | What WordPress/WooCommerce give you free, plus a drop-in SEO helper pattern (meta, Open Graph, JSON-LD). |
| **scripts/** | `lib.php` (the toolbox), `validate-tree.php` (run after every write), `wp-eval.sh` (wp-cli wrapper), and `examples/` (six worked scripts). |

---

## Requirements

- WordPress with **Oxygen 6.1** (Breakdance engine) active
- **wp-cli** and **PHP 8.x**
- Optional: [Local](https://localwp.com/) for local dev — `wp-eval.sh` handles its non-standard PHP/MySQL paths for you

---

## Good to know

- **Distilled from a real production WooCommerce build.** Example post IDs (e.g. footer `#15`) and the placeholder brand (**"Acme"**, teal + slate, Oswald/Inter) are illustrative — your IDs and brand will differ. Treat them as patterns.
- **Pinned to Oxygen 6.1.0.** Shapes can shift between plugin versions. When unsure, capture a fresh sample from your own builder (`\Breakdance\Data\get_tree($id)`) and compare.
- **No warranty.** Always run `validate-tree.php` and open the page in the builder before trusting a write.
- **Bring your own project notes.** This ships no `PROJECT-STATE.md` on purpose — keep one in *your* repo (template/component IDs, global setup, known debt); future-you will thank you.

---

## Contributing

Corrections and new verified recipes or traps are very welcome — especially for Oxygen versions beyond 6.1.0. Please note the exact version you tested against.

## Author

Created by **Benjamin Arnedo**.

## License

MIT — © 2026 Benjamin Arnedo. See [LICENSE](LICENSE).
