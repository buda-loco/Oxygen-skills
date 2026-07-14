# Oxygen 6 (Breakdance engine) — Claude Code skill & field guide

A reusable [Claude Code](https://claude.com/claude-code) **skill** — plus standalone reference docs and
a small PHP toolbox — for **building and editing Oxygen 6.1 sites programmatically**, through the
`_oxygen_data` post-meta tree, instead of clicking around the visual builder.

Oxygen 6 is a **complete rewrite on the Breakdance engine**. Classic Oxygen v3/v4 knowledge
(`ct_section`, `ct_id`, shortcodes) does **not** apply. Everything here is verified against the live
plugin (**v6.1.0**): the exact write-shapes the builder's strict io-ts validation accepts, the element
inventory, and — most valuably — the long list of traps that silently break a tree or a stylesheet.

> **Golden rule captured throughout:** the PHP renderer is lenient, but the builder runs strict io-ts
> validation. A tree can render on the front-end yet fail to open in the builder. Never guess shapes —
> use the toolbox, `oxy_golden()`, or a builder-saved sample.

## What's inside

| File | What it is |
|---|---|
| `SKILL.md` | The skill entry point: golden rules, the toolbox, the build/verify workflow, styling strategies |
| `ELEMENTS.md` | Inventory of the 165 native elements + where their source lives |
| `PROPERTIES.md` | Exact write-shapes: tree/node, selectors, **Global Settings** (colors/fonts/width), template settings, element content keys |
| `RECIPES.md` | End-to-end recipes: pages, WooCommerce PDP/PLP, cart/checkout/account, search, 404, single-post, native loops, dynamic data, reference stylesheets, images, footer rebuilds |
| `GOTCHAS.md` | Symptom → fix table + every trap (io-ts failures, dead WooCommerce selectors, the `.bde-div` cascade, comment-strip, WC-template cache fatals, …) |
| `SEO.md` | What WordPress/WooCommerce give free + an SEO mu-plugin pattern (meta/OG/JSON-LD/canonicals) |
| `scripts/lib.php` | The core toolbox: `oxy_write_tree`, node factories (`oxy_div`/`oxy_text`/`oxy_image`/…), `oxy_golden`, validation |
| `scripts/validate-tree.php` | Validate a tree against the builder's io-ts invariants + known traps (run after every write) |
| `scripts/wp-eval.sh` | Run wp-cli against a WordPress + Oxygen site (env-configurable; handy with "Local") |
| `scripts/examples/` | Six worked scripts: page/template build, native loop, component placement, dynamic data, CSS injection, image swap |

## Requirements

- WordPress with **Oxygen 6.1** (Breakdance engine) installed and active.
- **wp-cli** and **PHP 8.x**.
- Optional: [Local](https://localwp.com/) for local dev — `scripts/wp-eval.sh` wraps its non-standard PHP/MySQL paths.

## Use it as a Claude Code skill

Copy the folder into your skills directory so Claude Code auto-loads it:

```bash
# user-level (all projects)
cp -R "oxygen-page-builder" ~/.claude/skills/
# or project-level
cp -R "oxygen-page-builder" <your-project>/.claude/skills/
```

Then just ask Claude to build or fix Oxygen pages; the skill's `description` triggers it. `SKILL.md`'s
YAML frontmatter is what Claude reads.

## Use it without Claude (plain reference + toolbox)

The `.md` files are a standalone field guide. The PHP toolbox works from any wp-cli context:

```bash
export OXY_SITE_PATH="/path/to/your-site/app"   # dir containing public/ (a Local site) or WP root
./scripts/wp-eval.sh scripts/examples/build_blog_archive.php     # run a build script
./scripts/wp-eval.sh scripts/validate-tree.php <postId> fetch    # ALWAYS validate after a write
./scripts/wp-eval.sh -- post list                               # any wp command
```

Core workflow (see `SKILL.md`):

```php
require '/path/to/oxygen-page-builder/scripts/lib.php';
$id = wp_insert_post([...]);
oxy_write_tree($id, [
  oxy_div([
    oxy_text('TITLE', 'h1', ['hero__title']),
    oxy_rich('<p>…</p>', ['prose']),
    oxy_button('CTA', '/contact/', ['btn']),
  ], ['container']),
]);
// then: validate-tree.php <id> fetch  +  open ?oxygen=builder&id=<id>
```

## Track your own project state

This package intentionally ships **no `PROJECT-STATE.md`** — that file is project-specific. Keep one in
your own repo as a living inventory: template/component post IDs, global setup, and known debt. It makes
handoffs (and future-you) far easier.

## Provenance & caveats

- Distilled from a **real production WooCommerce storefront** build. Example scripts and some doc
  snippets use **illustrative** post IDs (e.g. footer `#15`) and a placeholder brand (**"Acme"**) —
  treat them as patterns, not literals; your IDs will differ.
- Verified against **Oxygen 6.1.0**. Shapes can change between plugin versions — when in doubt, capture
  a golden sample from your own builder (`\Breakdance\Data\get_tree($id)`) and compare.
- No warranty. Always run `validate-tree.php` and open the page in the builder before trusting a write.

## Contributing

Corrections and new verified recipes/traps welcome — especially for plugin versions beyond 6.1.0.
Please note the exact Oxygen version you verified against.

## Author

Created by **Benjamin Arnedo**.

## License

MIT — © 2026 Benjamin Arnedo. See [LICENSE](LICENSE).
