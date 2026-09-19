# Docsplorer

A lightweight WordPress plugin for browsing documents (or any files) in
nested folders — think decades and years, or any hierarchy you like —
with drag-and-drop upload and organization in the admin, and a clean,
breadcrumb-navigable folder browser on the public side of your site.

[![Tests](https://github.com/pedroviking/docsplorer/actions/workflows/tests.yml/badge.svg)](https://github.com/pedroviking/docsplorer/actions/workflows/tests.yml)

## Why

Built for a homeowners association site that needed a simple way to
publish 20+ years of meeting minutes and documents, organized by year,
without relying on an unmaintained plugin that didn't support current
PHP versions.

## Features

- **Nested folders** (e.g. Decade → Year), not just a flat list
- **Drag-and-drop admin screen** — drop files straight into a folder,
  drag existing documents or whole folders to re-file them
- **Rename and delete** folders and documents from the same screen
- **Frontend browser** via a simple shortcode, with breadcrumb
  navigation and file-type icons (PDF, Word, Excel, etc.)
- No custom database tables — built entirely on WordPress' own post
  types and taxonomies, so your data stays portable and inspectable
- A handful of actions/filters for building your own add-ons — see
  [`HOOKS.md`](HOOKS.md)

## Requirements

- WordPress 5.9+
- PHP 7.4+

## Installation

Not yet published on the WordPress.org plugin directory. Until then:

1. Download this repository as a ZIP (Code → Download ZIP), or clone it.
2. Upload the `docsplorer` folder to `wp-content/plugins/`.
3. Activate **Docsplorer** under Plugins in your WordPress admin.

## Usage

- Manage folders and documents under **Docsplorer** in the admin menu.
- Drop a file onto the drop zone to upload it into the currently open
  folder; drag a file card or a folder in the tree to re-file it.
- Add the `[docsplorer_documents]` shortcode to any page or post to
  show the public, browsable folder view.

## Extending

Docsplorer fires a set of actions and filters intended for building a
separate add-on plugin on top of it (rather than modifying this plugin
directly). See [`HOOKS.md`](HOOKS.md) for the full list.

## Running the tests

The test suite uses PHPUnit + WP_Mock and runs in plain PHP — no
WordPress install or database required. See
[`tests/README.md`](tests/README.md) for setup instructions.

```bash
composer install
vendor/bin/phpunit
```

## License

GPL v2 or later — see [`LICENSE`](LICENSE).

## Author

Peder Møller
