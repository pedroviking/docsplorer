# Extending Docsplorer

Docsplorer fires a handful of WordPress actions and filters that a
separate add-on plugin (e.g. DocsplorerPlus) can hook into, instead of
calling Docsplorer's internal functions directly. Internal function names
can change between releases; these hooks are the stable, supported way in.

## Actions (something happened)

All actions fire from `includes/admin-manager.php`, right before the
AJAX response is sent, so a listener can safely read anything Docsplorer
just wrote to the database.

| Hook | Fires when | Arguments |
|---|---|---|
| `docsplorer_after_upload` | A document has been uploaded and filed | `$post_id, $attachment_id, $folder_id` (`$folder_id` is `0` for the root view) |
| `docsplorer_after_move` | A document has been re-filed into a folder | `$doc_id, $folder_id` (`$folder_id` is `0` for the root view) |
| `docsplorer_after_rename_doc` | A document has been renamed | `$doc_id, $name` |
| `docsplorer_after_folder_created` | A new folder has been created | `$term_id, $parent_id` (`$parent_id` is `0` for top-level) |
| `docsplorer_after_folder_moved` | A folder has been re-parented | `$term_id, $new_parent_id` |
| `docsplorer_after_folder_renamed` | A folder has been renamed | `$term_id, $name` |
| `docsplorer_after_delete_doc` | A document has been moved to the trash | `$doc_id` |
| `docsplorer_after_folder_deleted` | An (empty) folder has been deleted | `$term_id` |

Example:

```php
add_action( 'docsplorer_after_upload', function ( $post_id, $attachment_id, $folder_id ) {
    // e.g. queue a PDF thumbnail generation job.
}, 10, 3 );
```

## Filters (add your own UI)

| Hook | Where it prints | Signature |
|---|---|---|
| `docsplorer_manager_toolbar` | Just below the intro text on the Document Manager screen, above the tree/grid | `apply_filters( 'docsplorer_manager_toolbar', string $html )` |
| `docsplorer_manager_card_actions` | Inside each document card, after the delete (×) button | `apply_filters( 'docsplorer_manager_card_actions', string $html, WP_Post $doc )` |

Both are plain string filters -- return HTML, it gets echoed as-is (already
trusted, same pattern WordPress itself uses for `the_content`). Example:

```php
add_filter( 'docsplorer_manager_toolbar', function ( $html ) {
    return $html . '<p><a href="https://example.com/upgrade" class="button button-primary">Upgrade to Plus</a></p>';
} );
```

## What's intentionally *not* a hook (yet)

The frontend `[docsplorer_documents]` shortcode doesn't expose equivalent
filters yet. If a premium add-on needs to add something to the
public-facing folder browser (not just the admin screen), that's a
reasonable next hook to add -- ask, rather than reading shortcode.php's
internals directly, since those internals aren't a stable contract.
