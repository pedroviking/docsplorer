<?php
/**
 * The "Docsplorer" admin screen: folder tree, drag-and-drop upload
 * zone, file cards, and every admin-ajax.php handler it talks to.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * -----------------------------------------------------------------
 * 6. ADMIN "DOCUMENT MANAGER" -- drag & drop upload + drag-to-move
 * -----------------------------------------------------------------
 * A dedicated admin screen: folder tree on the left, drop zone +
 * file cards on the right. Dropping OS files onto the drop zone
 * uploads and files them straight into the open folder. Dragging an
 * existing file card onto a folder in the tree re-files it there.
 * Everything goes through admin-ajax.php with nonce + capability
 * checks, never trusting client-supplied folder/doc ownership.
 */
function docsplorer_add_manager_page() {
	add_menu_page(
		__( 'Docsplorer', 'docsplorer' ),
		__( 'Docsplorer', 'docsplorer' ),
		'edit_posts',
		'docsplorer-manager',
		'docsplorer_render_manager_page',
		'dashicons-media-document',
		25
	);
}
add_action( 'admin_menu', 'docsplorer_add_manager_page' );

function docsplorer_render_folder_tree( $parent_id, $selected_id ) {
	$terms = get_terms(
		array(
			'taxonomy'   => 'docsplorer_folder',
			'parent'     => $parent_id,
			'hide_empty' => false,
		)
	);

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return '';
	}

	$base_url = remove_query_arg( 'folder' );
	$out      = '<ul class="docsplorer-subtree">';
	foreach ( $terms as $term ) {
		$url    = add_query_arg( 'folder', $term->slug, $base_url );
		$is_sel = ( (int) $term->term_id === (int) $selected_id ) ? ' is-selected' : '';
		$out   .= '<li class="docsplorer-tree-item' . esc_attr( $is_sel ) . '" data-term-id="' . esc_attr( $term->term_id ) . '">';
		$out   .= '<span class="docsplorer-tree-row" draggable="true">';
		$out   .= '<a href="' . esc_url( $url ) . '" class="docsplorer-tree-link" draggable="false" data-term-name="' . esc_attr( $term->name ) . '">' . esc_html( $term->name ) . '</a>';
		$out   .= '<button type="button" class="docsplorer-tree-rename" data-term-id="' . esc_attr( $term->term_id ) . '" title="' . esc_attr__( 'Rename folder', 'docsplorer' ) . '">&#9998;</button>';
		$out   .= '<button type="button" class="docsplorer-tree-delete" data-term-id="' . esc_attr( $term->term_id ) . '" title="' . esc_attr__( 'Delete folder', 'docsplorer' ) . '">&times;</button>';
		$out   .= '</span>';
		$out   .= docsplorer_render_folder_tree( $term->term_id, $selected_id );
		$out   .= '</li>';
	}
	$out .= '</ul>';

	return $out;
}

function docsplorer_render_manager_cards( $term ) {
	$args = array(
		'post_type'      => 'docsplorer_document',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	);

	if ( $term ) {
		// A tax_query is inherently scoped to one specific folder term
		// here (not an open-ended query), so this stays fast even on a
		// large document library.
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		$args['tax_query'] = array(
			array(
				'taxonomy'         => 'docsplorer_folder',
				'field'            => 'term_id',
				'terms'            => $term->term_id,
				'include_children' => false,
			),
		);
	} else {
		// Root view: documents that have no folder assigned yet.
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'docsplorer_folder',
				'operator' => 'NOT EXISTS',
			),
		);
	}

	$docs = get_posts( $args );

	if ( empty( $docs ) ) {
		return '<p class="docsplorer-empty">' . esc_html__( 'No documents here yet. Drag files onto the drop zone above.', 'docsplorer' ) . '</p>';
	}

	$out = '';
	foreach ( $docs as $doc ) {
		$out .= docsplorer_render_one_manager_card( $doc );
	}

	return $out;
}

function docsplorer_render_one_manager_card( $doc ) {
	$attachment_id = (int) get_post_meta( $doc->ID, '_docsplorer_file_id', true );
	$url           = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
	$icon          = $url ? docsplorer_get_file_type( $url ) : array(
		'label' => '?',
		'color' => '#999999',
	);

	/**
	 * Extra HTML injected into each document card in the Document
	 * Manager, right after the delete button (e.g. an extra icon or
	 * link a premium add-on wants to show per document).
	 *
	 * @param string  $html Extra markup to append. Empty by default.
	 * @param WP_Post $doc  The docsplorer_document post this card is for.
	 */
	$extra_actions = apply_filters( 'docsplorer_manager_card_actions', '', $doc );

	return '<div class="docsplorer-card docsplorer-manager-card" draggable="true" data-doc-id="' . esc_attr( $doc->ID ) . '" data-doc-name="' . esc_attr( get_the_title( $doc ) ) . '">'
		. docsplorer_file_icon_svg( $icon['label'], $icon['color'] )
		. '<span class="docsplorer-name">' . esc_html( get_the_title( $doc ) ) . '</span>'
		. '<button type="button" class="docsplorer-rename" data-doc-id="' . esc_attr( $doc->ID ) . '" title="' . esc_attr__( 'Rename', 'docsplorer' ) . '">&#9998;</button>'
		. '<button type="button" class="docsplorer-delete" data-doc-id="' . esc_attr( $doc->ID ) . '" title="' . esc_attr__( 'Delete', 'docsplorer' ) . '">&times;</button>'
		. $extra_actions
		. '</div>';
}

function docsplorer_render_manager_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'docsplorer' ) );
	}

	// Which folder is currently open. Read-only display filtering, not
	// a state-changing action, so nonce verification doesn't apply.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$selected_slug = isset( $_GET['folder'] ) ? sanitize_title( wp_unslash( $_GET['folder'] ) ) : '';
	$selected_term = $selected_slug ? get_term_by( 'slug', $selected_slug, 'docsplorer_folder' ) : false;
	$selected_id   = $selected_term ? $selected_term->term_id : 0;
	$root_url      = remove_query_arg( 'folder' );
	$nonce         = wp_create_nonce( 'docsplorer_manager_nonce' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Docsplorer', 'docsplorer' ); ?></h1>
		<p><?php esc_html_e( 'Drag files onto the drop zone to upload them into the open folder. Drag a file card onto a folder on the left to move it.', 'docsplorer' ); ?></p>

		<?php
		/**
		 * Extra HTML printed just below the intro text on the Document
		 * Manager screen -- e.g. an "Upgrade to Pro" notice or extra
		 * global controls from a premium add-on.
		 *
		 * @param string $html Extra markup to print. Empty by default.
		 */
		echo apply_filters( 'docsplorer_manager_toolbar', '' ); // phpcs:ignore WordPress.Security.EscapeOutput -- filtered, trusted markup, same pattern as the_content().
		?>

		<div id="docsplorer-manager">
			<div class="docsplorer-tree-pane">
				<button type="button" id="docsplorer-new-folder" class="button"><?php esc_html_e( '+ New folder', 'docsplorer' ); ?></button>
				<ul class="docsplorer-tree" id="docsplorer-tree">
					<li class="docsplorer-tree-item<?php echo ( 0 === $selected_id ) ? ' is-selected' : ''; ?>" data-term-id="0">
						<a href="<?php echo esc_url( $root_url ); ?>" class="docsplorer-tree-link"><?php esc_html_e( 'All', 'docsplorer' ); ?></a>
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each value is escaped individually inside this function before being concatenated into the returned HTML string.
						echo docsplorer_render_folder_tree( 0, $selected_id );
						?>
					</li>
				</ul>
			</div>

			<div class="docsplorer-files-pane">
				<div id="docsplorer-dropzone" data-folder-id="<?php echo esc_attr( $selected_id ); ?>">
					<p><?php esc_html_e( 'Drag files here to upload', 'docsplorer' ); ?></p>
				</div>
				<div class="docsplorer-grid" id="docsplorer-file-grid">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each value is escaped individually inside this function before being concatenated into the returned HTML string.
					echo docsplorer_render_manager_cards( $selected_term );
					?>
				</div>
			</div>
		</div>
	</div>

	<style>
		#docsplorer-manager { display: flex; gap: 2em; margin-top: 1.5em; align-items: flex-start; }
		.docsplorer-tree-pane { width: 240px; flex-shrink: 0; background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 1em; }
		.docsplorer-grid {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
			gap: 1.25em;
			margin: 0 0 1.5em;
		}
		.docsplorer-card {
			display: flex;
			flex-direction: column;
			align-items: center;
			text-align: center;
			padding: 1em 0.5em;
			border-radius: 10px;
			border: 1px solid #e5e5e5;
			background: #fff;
			text-decoration: none;
			color: #333;
			transition: box-shadow .15s ease, transform .15s ease, border-color .15s ease;
		}
		.docsplorer-card:hover {
			box-shadow: 0 6px 16px rgba(0,0,0,.08);
			transform: translateY(-2px);
			border-color: #ccc;
		}
		.docsplorer-icon {
			width: 56px;
			height: 56px;
			margin-bottom: 0.6em;
		}
		.docsplorer-name {
			font-size: 0.85em;
			line-height: 1.3;
			word-break: break-word;
		}
		.docsplorer-tree, .docsplorer-subtree { list-style: none; margin: 0.5em 0 0; padding-left: 1em; }
		.docsplorer-tree { padding-left: 0; }
		.docsplorer-tree-row { display: flex; align-items: center; justify-content: space-between; border-radius: 5px; cursor: grab; }
		.docsplorer-tree-row.is-dragging { opacity: 0.4; }
		.docsplorer-tree-item > .docsplorer-tree-row > .docsplorer-tree-link { flex-grow: 1; display: block; padding: 0.35em 0.5em; text-decoration: none; color: #333; border-radius: 5px; }
		.docsplorer-tree-item.is-selected > .docsplorer-tree-row > .docsplorer-tree-link { background: #2271b1; color: #fff; }
		.docsplorer-tree-item.is-dragover > .docsplorer-tree-row > .docsplorer-tree-link { background: #d7e9c9; outline: 2px dashed #46b450; }
		.docsplorer-tree-delete {
			flex-shrink: 0; width: 20px; height: 20px; line-height: 18px; margin-right: 0.25em;
			border: none; border-radius: 50%; background: transparent; color: #999;
			cursor: pointer; font-size: 14px; padding: 0; opacity: 0; transition: opacity .1s;
		}
		.docsplorer-tree-rename {
			flex-shrink: 0; width: 20px; height: 20px; line-height: 18px; margin-right: 0.15em;
			border: none; border-radius: 50%; background: transparent; color: #999;
			cursor: pointer; font-size: 12px; padding: 0; opacity: 0; transition: opacity .1s;
		}
		.docsplorer-tree-row:hover .docsplorer-tree-delete,
		.docsplorer-tree-row:hover .docsplorer-tree-rename { opacity: 1; }
		.docsplorer-tree-delete:hover { background: #d63638; color: #fff; }
		.docsplorer-tree-rename:hover { background: #2271b1; color: #fff; }
		.docsplorer-files-pane { flex-grow: 1; }
		#docsplorer-dropzone {
			border: 2px dashed #b5b5b5;
			border-radius: 8px;
			padding: 2em;
			text-align: center;
			color: #666;
			margin-bottom: 1.5em;
			background: #fafafa;
		}
		#docsplorer-dropzone.is-dragover { border-color: #2271b1; background: #eef6fc; color: #2271b1; }
		.docsplorer-manager-card { position: relative; cursor: grab; }
		.docsplorer-manager-card.is-dragging { opacity: 0.4; }
		.docsplorer-rename {
			position: absolute; top: 4px; right: 26px;
			width: 20px; height: 20px; line-height: 18px;
			border: none; border-radius: 50%; background: #e5e5e5; color: #555;
			cursor: pointer; font-size: 12px; padding: 0;
		}
		.docsplorer-rename:hover { background: #2271b1; color: #fff; }
		.docsplorer-delete {
			position: absolute; top: 4px; right: 4px;
			width: 20px; height: 20px; line-height: 18px;
			border: none; border-radius: 50%; background: #e5e5e5; color: #555;
			cursor: pointer; font-size: 14px; padding: 0;
		}
		.docsplorer-delete:hover { background: #d63638; color: #fff; }
		.docsplorer-empty { font-style: italic; color: #777; }
	</style>

	<script>
	( function () {
		var nonce   = '<?php echo esc_js( $nonce ); ?>';
		var dropzone = document.getElementById( 'docsplorer-dropzone' );
		var grid      = document.getElementById( 'docsplorer-file-grid' );
		var tree      = document.getElementById( 'docsplorer-tree' );

		function postAjax( data ) {
			var formData = new FormData();
			Object.keys( data ).forEach( function ( key ) {
				formData.append( key, data[ key ] );
			} );
			formData.append( 'nonce', nonce );
			return fetch( ajaxurl, { method: 'POST', credentials: 'same-origin', body: formData } )
				.then( function ( res ) { return res.json(); } );
		}

		// --- Upload via drop zone ---
		[ 'dragenter', 'dragover' ].forEach( function ( evt ) {
			dropzone.addEventListener( evt, function ( e ) {
				e.preventDefault();
				dropzone.classList.add( 'is-dragover' );
			} );
		} );
		[ 'dragleave', 'drop' ].forEach( function ( evt ) {
			dropzone.addEventListener( evt, function () {
				dropzone.classList.remove( 'is-dragover' );
			} );
		} );
		dropzone.addEventListener( 'drop', function ( e ) {
			e.preventDefault();
			var files = e.dataTransfer.files;
			if ( ! files || ! files.length ) {
				return;
			}
			var folderId = dropzone.getAttribute( 'data-folder-id' );
			Array.prototype.forEach.call( files, function ( file ) {
				var formData = new FormData();
				formData.append( 'action', 'docsplorer_upload' );
				formData.append( 'nonce', nonce );
				formData.append( 'folder_id', folderId );
				formData.append( 'file', file );
				fetch( ajaxurl, { method: 'POST', credentials: 'same-origin', body: formData } )
					.then( function ( res ) { return res.json(); } )
					.then( function ( json ) {
						if ( json.success ) {
							var emptyMsg = grid.querySelector( '.docsplorer-empty' );
							if ( emptyMsg ) {
								emptyMsg.remove();
							}
							var wrapper = document.createElement( 'div' );
							wrapper.innerHTML = json.data.html;
							grid.appendChild( wrapper.firstElementChild );
						} else {
							alert( json.data && json.data.message ? json.data.message : 'Upload failed.' );
						}
					} );
			} );
		} );

		// --- Drag existing document cards to move them ---
		grid.addEventListener( 'dragstart', function ( e ) {
			if ( ! e.target.classList.contains( 'docsplorer-manager-card' ) ) {
				return;
			}
			e.target.classList.add( 'is-dragging' );
			e.dataTransfer.setData( 'text/plain', 'doc:' + e.target.getAttribute( 'data-doc-id' ) );
		} );
		grid.addEventListener( 'dragend', function ( e ) {
			if ( e.target.classList.contains( 'docsplorer-manager-card' ) ) {
				e.target.classList.remove( 'is-dragging' );
			}
		} );

		// --- Rename a document ---
		grid.addEventListener( 'click', function ( e ) {
			if ( ! e.target.classList.contains( 'docsplorer-rename' ) ) {
				return;
			}
			var card    = e.target.closest( '.docsplorer-manager-card' );
			var docId   = e.target.getAttribute( 'data-doc-id' );
			var current = card.getAttribute( 'data-doc-name' ) || '';
			var name    = prompt( '<?php echo esc_js( __( 'Rename document to:', 'docsplorer' ) ); ?>', current );
			if ( ! name || name === current ) {
				return;
			}
			postAjax( { action: 'docsplorer_rename_doc', doc_id: docId, name: name } ).then( function ( json ) {
				if ( json.success ) {
					card.setAttribute( 'data-doc-name', name );
					card.querySelector( '.docsplorer-name' ).textContent = name;
				} else {
					alert( json.data && json.data.message ? json.data.message : 'Could not rename.' );
				}
			} );
		} );

		// --- Delete a document ---
		grid.addEventListener( 'click', function ( e ) {
			if ( ! e.target.classList.contains( 'docsplorer-delete' ) ) {
				return;
			}
			if ( ! confirm( '<?php echo esc_js( __( 'Move this document to the trash?', 'docsplorer' ) ); ?>' ) ) {
				return;
			}
			var docId = e.target.getAttribute( 'data-doc-id' );
			postAjax( { action: 'docsplorer_delete_doc', doc_id: docId } ).then( function ( json ) {
				if ( json.success ) {
					e.target.closest( '.docsplorer-manager-card' ).remove();
					if ( ! grid.querySelector( '.docsplorer-manager-card' ) && ! grid.querySelector( '.docsplorer-empty' ) ) {
						grid.innerHTML = '<p class="docsplorer-empty"><?php echo esc_js( __( 'No documents here yet. Drag files onto the drop zone above.', 'docsplorer' ) ); ?></p>';
					}
				} else {
					alert( json.data && json.data.message ? json.data.message : 'Could not delete.' );
				}
			} );
		} );

		// --- Folder tree as drop targets ---
		// --- Drag existing folders in the tree to re-parent them ---
		tree.addEventListener( 'dragstart', function ( e ) {
			if ( ! e.target.classList.contains( 'docsplorer-tree-row' ) ) {
				return;
			}
			var li = e.target.closest( '.docsplorer-tree-item' );
			if ( ! li || '0' === li.getAttribute( 'data-term-id' ) ) {
				return; // the "All" root row isn't a real, movable term
			}
			e.target.classList.add( 'is-dragging' );
			e.dataTransfer.setData( 'text/plain', 'folder:' + li.getAttribute( 'data-term-id' ) );
		} );
		tree.addEventListener( 'dragend', function ( e ) {
			if ( e.target.classList.contains( 'docsplorer-tree-row' ) ) {
				e.target.classList.remove( 'is-dragging' );
			}
		} );

		tree.querySelectorAll( '.docsplorer-tree-item' ).forEach( function ( li ) {
			li.addEventListener( 'dragover', function ( e ) {
				e.preventDefault();
				e.stopPropagation();
				li.classList.add( 'is-dragover' );
			} );
			li.addEventListener( 'dragleave', function ( e ) {
				e.stopPropagation();
				li.classList.remove( 'is-dragover' );
			} );
			li.addEventListener( 'drop', function ( e ) {
				e.preventDefault();
				e.stopPropagation();
				li.classList.remove( 'is-dragover' );

				var raw = e.dataTransfer.getData( 'text/plain' );
				if ( ! raw || raw.indexOf( ':' ) === -1 ) {
					return;
				}
				var parts        = raw.split( ':' );
				var kind         = parts[ 0 ];
				var id           = parts[ 1 ];
				var targetFolder = li.getAttribute( 'data-term-id' );

				if ( 'doc' === kind ) {
					postAjax( { action: 'docsplorer_move', doc_id: id, folder_id: targetFolder } ).then( function ( json ) {
						if ( json.success ) {
							var card = grid.querySelector( '[data-doc-id="' + id + '"]' );
							if ( card ) {
								card.remove();
							}
							if ( ! grid.querySelector( '.docsplorer-manager-card' ) && ! grid.querySelector( '.docsplorer-empty' ) ) {
								grid.innerHTML = '<p class="docsplorer-empty"><?php echo esc_js( __( 'No documents here yet. Drag files onto the drop zone above.', 'docsplorer' ) ); ?></p>';
							}
						} else {
							alert( json.data && json.data.message ? json.data.message : 'Could not move document.' );
						}
					} );
				} else if ( 'folder' === kind ) {
					if ( id === targetFolder ) {
						return; // dropped a folder onto itself
					}
					postAjax( { action: 'docsplorer_move_folder', term_id: id, new_parent_id: targetFolder } ).then( function ( json ) {
						if ( json.success ) {
							location.reload();
						} else {
							alert( json.data && json.data.message ? json.data.message : 'Could not move folder.' );
						}
					} );
				}
			} );
		} );

		// --- New folder ---
		document.getElementById( 'docsplorer-new-folder' ).addEventListener( 'click', function () {
			var name = prompt( '<?php echo esc_js( __( 'New folder name:', 'docsplorer' ) ); ?>' );
			if ( ! name ) {
				return;
			}
			var currentFolderId = dropzone.getAttribute( 'data-folder-id' ) || 0;
			postAjax( { action: 'docsplorer_create_folder', name: name, parent_id: currentFolderId } ).then( function ( json ) {
				if ( json.success ) {
					location.reload();
				} else {
					alert( json.data && json.data.message ? json.data.message : 'Could not create folder.' );
				}
			} );
		} );

		// --- Rename folder ---
		tree.addEventListener( 'click', function ( e ) {
			if ( ! e.target.classList.contains( 'docsplorer-tree-rename' ) ) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			var li      = e.target.closest( '.docsplorer-tree-item' );
			var link    = li.querySelector( ':scope > .docsplorer-tree-row > .docsplorer-tree-link' );
			var termId  = e.target.getAttribute( 'data-term-id' );
			var current = link.getAttribute( 'data-term-name' ) || link.textContent;
			var name    = prompt( '<?php echo esc_js( __( 'Rename folder to:', 'docsplorer' ) ); ?>', current );
			if ( ! name || name === current ) {
				return;
			}
			postAjax( { action: 'docsplorer_rename_folder', term_id: termId, name: name } ).then( function ( json ) {
				if ( json.success ) {
					link.setAttribute( 'data-term-name', name );
					link.textContent = name;
				} else {
					alert( json.data && json.data.message ? json.data.message : 'Could not rename folder.' );
				}
			} );
		} );

		// --- Delete folder ---
		tree.addEventListener( 'click', function ( e ) {
			if ( ! e.target.classList.contains( 'docsplorer-tree-delete' ) ) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			var termId = e.target.getAttribute( 'data-term-id' );
			if ( ! confirm( '<?php echo esc_js( __( 'Delete this folder? It must be empty (no subfolders or documents).', 'docsplorer' ) ); ?>' ) ) {
				return;
			}
			postAjax( { action: 'docsplorer_delete_folder', term_id: termId } ).then( function ( json ) {
				if ( json.success ) {
					var wasSelected = e.target.closest( '.docsplorer-tree-item' ).classList.contains( 'is-selected' );
					e.target.closest( '.docsplorer-tree-item' ).remove();
					if ( wasSelected ) {
						window.location.href = '<?php echo esc_js( $root_url ); ?>';
					}
				} else {
					alert( json.data && json.data.message ? json.data.message : 'Could not delete folder.' );
				}
			} );
		} );
	} )();
	</script>
	<?php
}

function docsplorer_ajax_upload() {
	check_ajax_referer( 'docsplorer_manager_nonce', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Not allowed.', 'docsplorer' ) ), 403 );
	}
	if ( empty( $_FILES['file'] ) ) {
		wp_send_json_error( array( 'message' => __( 'No file received.', 'docsplorer' ) ) );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$attachment_id = media_handle_upload( 'file', 0 );
	if ( is_wp_error( $attachment_id ) ) {
		wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
	}

	$title   = get_the_title( $attachment_id );
	$post_id = wp_insert_post(
		array(
			'post_type'   => 'docsplorer_document',
			'post_title'  => $title ? $title : __( 'Untitled document', 'docsplorer' ),
			'post_status' => 'publish',
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
	}

	update_post_meta( $post_id, '_docsplorer_file_id', $attachment_id );

	$folder_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
	if ( $folder_id && get_term( $folder_id, 'docsplorer_folder' ) && ! is_wp_error( get_term( $folder_id, 'docsplorer_folder' ) ) ) {
		wp_set_object_terms( $post_id, array( $folder_id ), 'docsplorer_folder' );
	}

	/**
	 * Fires after a document has been uploaded and filed, right before
	 * the AJAX response is sent. $folder_id is 0 if it was uploaded to
	 * the root ("no folder") view.
	 *
	 * @param int $post_id       The new docsplorer_document post ID.
	 * @param int $attachment_id The underlying WordPress attachment ID.
	 * @param int $folder_id     The docsplorer_folder term ID it was filed into, or 0.
	 */
	do_action( 'docsplorer_after_upload', $post_id, $attachment_id, $folder_id );

	wp_send_json_success( array( 'html' => docsplorer_render_one_manager_card( get_post( $post_id ) ) ) );
}
add_action( 'wp_ajax_docsplorer_upload', 'docsplorer_ajax_upload' );

function docsplorer_ajax_move() {
	check_ajax_referer( 'docsplorer_manager_nonce', 'nonce' );

	$doc_id = isset( $_POST['doc_id'] ) ? absint( $_POST['doc_id'] ) : 0;
	if ( ! $doc_id || 'docsplorer_document' !== get_post_type( $doc_id ) || ! current_user_can( 'edit_post', $doc_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Not allowed.', 'docsplorer' ) ), 403 );
	}

	$folder_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;

	if ( $folder_id ) {
		$term = get_term( $folder_id, 'docsplorer_folder' );
		if ( ! $term || is_wp_error( $term ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid folder.', 'docsplorer' ) ) );
		}
		wp_set_object_terms( $doc_id, array( $folder_id ), 'docsplorer_folder' );
	} else {
		wp_set_object_terms( $doc_id, array(), 'docsplorer_folder' );
	}

	/**
	 * Fires after a document has been re-filed into a (possibly
	 * different) folder. $folder_id is 0 if it was moved to the root
	 * ("no folder") view.
	 *
	 * @param int $doc_id    The docsplorer_document post ID that moved.
	 * @param int $folder_id The docsplorer_folder term ID it now belongs to, or 0.
	 */
	do_action( 'docsplorer_after_move', $doc_id, $folder_id );

	wp_send_json_success();
}
add_action( 'wp_ajax_docsplorer_move', 'docsplorer_ajax_move' );

function docsplorer_ajax_rename_doc() {
	check_ajax_referer( 'docsplorer_manager_nonce', 'nonce' );

	$doc_id = isset( $_POST['doc_id'] ) ? absint( $_POST['doc_id'] ) : 0;
	if ( ! $doc_id || 'docsplorer_document' !== get_post_type( $doc_id ) || ! current_user_can( 'edit_post', $doc_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Not allowed.', 'docsplorer' ) ), 403 );
	}

	$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	if ( ! $name ) {
		wp_send_json_error( array( 'message' => __( 'Name cannot be empty.', 'docsplorer' ) ) );
	}

	$result = wp_update_post(
		array(
			'ID'         => $doc_id,
			'post_title' => $name,
		),
		true
	);
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	/**
	 * Fires after a document has been renamed.
	 *
	 * @param int    $doc_id The docsplorer_document post ID that was renamed.
	 * @param string $name   Its new title.
	 */
	do_action( 'docsplorer_after_rename_doc', $doc_id, $name );

	wp_send_json_success( array( 'name' => $name ) );
}
add_action( 'wp_ajax_docsplorer_rename_doc', 'docsplorer_ajax_rename_doc' );

function docsplorer_ajax_move_folder() {
	check_ajax_referer( 'docsplorer_manager_nonce', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Not allowed.', 'docsplorer' ) ), 403 );
	}

	$term_id       = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
	$new_parent_id = isset( $_POST['new_parent_id'] ) ? absint( $_POST['new_parent_id'] ) : 0;

	$term = $term_id ? get_term( $term_id, 'docsplorer_folder' ) : null;
	if ( ! $term_id || ! $term || is_wp_error( $term ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid folder.', 'docsplorer' ) ) );
	}

	if ( $term_id === $new_parent_id ) {
		wp_send_json_error( array( 'message' => __( 'A folder cannot be moved into itself.', 'docsplorer' ) ) );
	}

	if ( $new_parent_id ) {
		$new_parent = get_term( $new_parent_id, 'docsplorer_folder' );
		if ( ! $new_parent || is_wp_error( $new_parent ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid target folder.', 'docsplorer' ) ) );
		}

		// Prevent moving a folder into one of its own descendants -- that
		// would create a cycle the taxonomy tree can't represent.
		$descendant_ids = get_terms(
			array(
				'taxonomy'   => 'docsplorer_folder',
				'child_of'   => $term_id,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		if ( ! is_wp_error( $descendant_ids ) && in_array( $new_parent_id, $descendant_ids, true ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot move a folder into one of its own subfolders.', 'docsplorer' ) ) );
		}
	}

	$result = wp_update_term( $term_id, 'docsplorer_folder', array( 'parent' => $new_parent_id ) );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	/**
	 * Fires after a folder has been re-parented.
	 *
	 * @param int $term_id       The docsplorer_folder term ID that moved.
	 * @param int $new_parent_id Its new parent term ID, or 0 for top-level.
	 */
	do_action( 'docsplorer_after_folder_moved', $term_id, $new_parent_id );

	wp_send_json_success();
}
add_action( 'wp_ajax_docsplorer_move_folder', 'docsplorer_ajax_move_folder' );

function docsplorer_ajax_create_folder() {
	check_ajax_referer( 'docsplorer_manager_nonce', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Not allowed.', 'docsplorer' ) ), 403 );
	}

	$name      = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$parent_id = isset( $_POST['parent_id'] ) ? absint( $_POST['parent_id'] ) : 0;

	if ( ! $name ) {
		wp_send_json_error( array( 'message' => __( 'Folder name is required.', 'docsplorer' ) ) );
	}

	$result = wp_insert_term( $name, 'docsplorer_folder', array( 'parent' => $parent_id ) );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	/**
	 * Fires after a new folder has been created.
	 *
	 * @param int $term_id   The new docsplorer_folder term ID.
	 * @param int $parent_id Its parent term ID, or 0 for top-level.
	 */
	do_action( 'docsplorer_after_folder_created', $result['term_id'], $parent_id );

	wp_send_json_success( $result );
}
add_action( 'wp_ajax_docsplorer_create_folder', 'docsplorer_ajax_create_folder' );

function docsplorer_ajax_rename_folder() {
	check_ajax_referer( 'docsplorer_manager_nonce', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Not allowed.', 'docsplorer' ) ), 403 );
	}

	$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
	$term    = $term_id ? get_term( $term_id, 'docsplorer_folder' ) : null;
	if ( ! $term_id || ! $term || is_wp_error( $term ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid folder.', 'docsplorer' ) ) );
	}

	$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	if ( ! $name ) {
		wp_send_json_error( array( 'message' => __( 'Folder name cannot be empty.', 'docsplorer' ) ) );
	}

	$result = wp_update_term( $term_id, 'docsplorer_folder', array( 'name' => $name ) );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	/**
	 * Fires after a folder has been renamed.
	 *
	 * @param int    $term_id The docsplorer_folder term ID that was renamed.
	 * @param string $name    Its new name.
	 */
	do_action( 'docsplorer_after_folder_renamed', $term_id, $name );

	wp_send_json_success( array( 'name' => $name ) );
}
add_action( 'wp_ajax_docsplorer_rename_folder', 'docsplorer_ajax_rename_folder' );

function docsplorer_ajax_delete_doc() {
	check_ajax_referer( 'docsplorer_manager_nonce', 'nonce' );

	$doc_id = isset( $_POST['doc_id'] ) ? absint( $_POST['doc_id'] ) : 0;
	if ( ! $doc_id || 'docsplorer_document' !== get_post_type( $doc_id ) || ! current_user_can( 'delete_post', $doc_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Not allowed.', 'docsplorer' ) ), 403 );
	}

	wp_trash_post( $doc_id );

	/**
	 * Fires after a document post has been moved to the trash.
	 *
	 * @param int $doc_id The docsplorer_document post ID that was trashed.
	 */
	do_action( 'docsplorer_after_delete_doc', $doc_id );

	wp_send_json_success();
}
add_action( 'wp_ajax_docsplorer_delete_doc', 'docsplorer_ajax_delete_doc' );

function docsplorer_ajax_delete_folder() {
	check_ajax_referer( 'docsplorer_manager_nonce', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Not allowed.', 'docsplorer' ) ), 403 );
	}

	$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
	$term    = $term_id ? get_term( $term_id, 'docsplorer_folder' ) : null;
	if ( ! $term_id || ! $term || is_wp_error( $term ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid folder.', 'docsplorer' ) ) );
	}

	// Refuse to delete a folder that still has subfolders.
	$children = get_terms(
		array(
			'taxonomy'   => 'docsplorer_folder',
			'parent'     => $term_id,
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);
	if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
		wp_send_json_error( array( 'message' => __( 'This folder still has subfolders inside it. Delete or move those first.', 'docsplorer' ) ) );
	}

	// Refuse to delete a folder that still has documents directly in it.
	$docs = get_posts(
		array(
			'post_type'      => 'docsplorer_document',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			// Scoped to one specific folder term, and capped at 1
			// result -- this is a cheap existence check, not a broad query.
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			'tax_query'      => array(
				array(
					'taxonomy'         => 'docsplorer_folder',
					'field'            => 'term_id',
					'terms'            => $term_id,
					'include_children' => false,
				),
			),
		)
	);
	if ( ! empty( $docs ) ) {
		wp_send_json_error( array( 'message' => __( 'This folder still has documents in it. Move or delete those first.', 'docsplorer' ) ) );
	}

	$deleted = wp_delete_term( $term_id, 'docsplorer_folder' );
	if ( is_wp_error( $deleted ) || ! $deleted ) {
		wp_send_json_error( array( 'message' => __( 'Could not delete folder.', 'docsplorer' ) ) );
	}

	/**
	 * Fires after a (now-empty) folder has been deleted.
	 *
	 * @param int $term_id The docsplorer_folder term ID that was deleted.
	 */
	do_action( 'docsplorer_after_folder_deleted', $term_id );

	wp_send_json_success();
}
add_action( 'wp_ajax_docsplorer_delete_folder', 'docsplorer_ajax_delete_folder' );
