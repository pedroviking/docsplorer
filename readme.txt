=== Docsplorer ===
Contributors: pedroviking
Tags: documents, files, folders, file manager, document library
Requires at least: 5.9
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.6.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Browse documents in nested, drag-and-drop-organized folders, with a simple public shortcode and no custom database tables.

== Description ==

Docsplorer is a lightweight document library for WordPress. Organize
documents into nested folders (Decade > Year, Department > Project, or
any hierarchy you like), manage everything with a drag-and-drop admin
screen, and let visitors browse the same structure on your public site
with a single shortcode.

**Admin features**

* Drag and drop files straight from your computer to upload them into a folder
* Drag existing documents or whole folders to re-file them
* Rename or delete folders and documents from the same screen
* Nested folders of unlimited depth

**Frontend features**

* `[docsplorer_documents]` shortcode shows a breadcrumb-navigable folder browser
* File-type icons (PDF, Word, Excel, images, and more)
* No page reloads needed to browse between folders

**Under the hood**

Docsplorer stores everything using WordPress' own post types and
taxonomies -- no custom database tables, so your data stays portable
and inspectable with standard WordPress tools. It also exposes a small
set of actions and filters for building your own extensions; see
`HOOKS.md` in the plugin's source repository.

This plugin is free and open source. Source code, issue tracker, and
the automated test suite live at:
https://github.com/pedroviking/docsplorer

== Installation ==

1. Upload the `docsplorer` folder to `/wp-content/plugins/`, or install
   through the WordPress plugin screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to **Docsplorer** in the admin menu to create folders and upload
   documents.
4. Add the `[docsplorer_documents]` shortcode to any page or post to
   show the public folder browser.

== Frequently Asked Questions ==

= Do I need to know how to code to use this? =

No. Creating folders, uploading files, and organizing them is all done
by dragging and dropping in the admin screen.

= Can I nest folders as deep as I like? =

Yes, folders can be nested to any depth (e.g. a decade folder
containing year folders, which could themselves contain sub-folders).

= Where are the actual files stored? =

Uploaded files go through WordPress' normal media library, so they end
up in your regular `wp-content/uploads/` folder, organized by upload
date. The folder structure you see in Docsplorer is a separate
organizational layer on top of that, not a real filesystem folder
structure.

= Does deleting a document also delete the uploaded file? =

Not currently. Deleting a document removes it from Docsplorer (moving
it to the trash), but the underlying file remains in your media
library. This may change in a future version.

== Screenshots ==

1. The Document Manager admin screen, with the folder tree and drag-and-drop upload area.
2. The public folder browser shown by the `[docsplorer_documents]` shortcode.

== Changelog ==

= 1.6.0 =
* Added rename support for both documents and folders.
* Added extension hooks (actions and filters) for building add-ons.

= 1.5.0 =
* Added actions and filters for extending Docsplorer from a separate plugin.

= 1.4.0 =
* Split the plugin into separate include files for readability.
* Added a PHPUnit + WP_Mock test suite and GitHub Actions CI.

= 1.3.0 =
* Fixed: a folder's "No documents in this folder" message no longer shows when it has subfolders.
* Renamed the admin root view and frontend breadcrumb for clarity.

= 1.2.0 =
* Renamed the plugin to Docsplorer.

= 1.0.0 =
* First tagged release: nested folders, drag-and-drop admin manager, frontend shortcode.

== Upgrade Notice ==

= 1.6.0 =
Adds rename support for folders and documents; no action needed to upgrade.
