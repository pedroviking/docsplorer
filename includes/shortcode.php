<?php
/**
 * Frontend [docsplorer_documents] shortcode: breadcrumb, subfolder
 * grid, document grid, and the styling for the public-facing browser.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * -----------------------------------------------------------------
 * 4. FRONTEND SHORTCODE: [docsplorer_documents]
 * -----------------------------------------------------------------
 * Renders the current folder's subfolders + documents, with a
 * breadcrumb trail built from the taxonomy's own parent/child data.
 * No filesystem paths are ever read from user input, only a term
 * slug that is looked up against the docsplorer_folder taxonomy -- so
 * there's no path-traversal surface here.
 */
function docsplorer_shortcode() {
	$taxonomy = 'docsplorer_folder';

	// Sanitize + validate the requested folder slug.
	$requested_slug = isset( $_GET['docsplorer_folder'] ) ? sanitize_title( wp_unslash( $_GET['docsplorer_folder'] ) ) : '';
	$current_term   = $requested_slug ? get_term_by( 'slug', $requested_slug, $taxonomy ) : false;

	ob_start();
	docsplorer_print_styles_once();
	?>
	<div class="docsplorer-browser">
		<?php echo docsplorer_render_breadcrumb( $current_term, $taxonomy ); ?>
		<?php echo docsplorer_render_subfolders( $current_term, $taxonomy ); ?>
		<?php echo docsplorer_render_documents( $current_term, $taxonomy ); ?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'docsplorer_documents', 'docsplorer_shortcode' );

function docsplorer_render_breadcrumb( $current_term, $taxonomy ) {
	$base_url = remove_query_arg( 'docsplorer_folder' );
	$crumbs   = array( '<a href="' . esc_url( $base_url ) . '">' . esc_html__( 'Home', 'docsplorer' ) . '</a>' );

	if ( $current_term && ! is_wp_error( $current_term ) ) {
		$ancestors = array_reverse( get_ancestors( $current_term->term_id, $taxonomy, 'taxonomy' ) );
		foreach ( $ancestors as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, $taxonomy );
			if ( $ancestor && ! is_wp_error( $ancestor ) ) {
				$url      = add_query_arg( 'docsplorer_folder', $ancestor->slug, $base_url );
				$crumbs[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $ancestor->name ) . '</a>';
			}
		}
		$crumbs[] = '<span class="docsplorer-current">' . esc_html( $current_term->name ) . '</span>';
	}

	return '<nav class="docsplorer-breadcrumb">' . implode( ' &raquo; ', $crumbs ) . '</nav>';
}

function docsplorer_render_subfolders( $current_term, $taxonomy ) {
	$parent_id = $current_term ? $current_term->term_id : 0;

	$subfolders = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'parent'     => $parent_id,
			'hide_empty' => false,
		)
	);

	if ( is_wp_error( $subfolders ) || empty( $subfolders ) ) {
		return '';
	}

	$base_url = remove_query_arg( 'docsplorer_folder' );
	$out      = '<div class="docsplorer-grid">';
	foreach ( $subfolders as $folder ) {
		$url    = add_query_arg( 'docsplorer_folder', $folder->slug, $base_url );
		$out   .= '<a class="docsplorer-card docsplorer-card--folder" href="' . esc_url( $url ) . '">'
				. docsplorer_folder_icon_svg()
				. '<span class="docsplorer-name">' . esc_html( $folder->name ) . '</span>'
				. '</a>';
	}
	$out .= '</div>';

	return $out;
}

function docsplorer_folder_icon_svg() {
	return '<svg class="docsplorer-icon" viewBox="0 0 56 44" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
		<path d="M2 7a4 4 0 0 1 4-4h13l4 5h27a4 4 0 0 1 4 4v27a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4Z" fill="#ffcf5c" stroke="#e0a52e" stroke-width="1.5"/>
		<path d="M2 14h52v22a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4Z" fill="#ffe08a" stroke="#e0a52e" stroke-width="1.5"/>
	</svg>';
}

function docsplorer_render_documents( $current_term, $taxonomy ) {
	// At the root (no folder selected) we don't list documents that
	// might be attached directly to top-level terms only -- adjust
	// this if you want root-level "loose" documents too.
	if ( ! $current_term ) {
		return '';
	}

	$documents = get_posts(
		array(
			'post_type'      => 'docsplorer_document',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'tax_query'      => array(
				array(
					'taxonomy'         => $taxonomy,
					'field'            => 'term_id',
					'terms'            => $current_term->term_id,
					'include_children' => false,
				),
			),
		)
	);

	if ( empty( $documents ) ) {
		// Only show the "empty" message if this folder is completely
		// empty -- if it has subfolders, those are enough to look at,
		// and this message would just be visual noise underneath them.
		$subfolder_count = wp_count_terms(
			array(
				'taxonomy'   => $taxonomy,
				'parent'     => $current_term->term_id,
				'hide_empty' => false,
			)
		);
		if ( ! is_wp_error( $subfolder_count ) && $subfolder_count > 0 ) {
			return '';
		}
		return '<p class="docsplorer-empty">' . esc_html__( 'No documents in this folder.', 'docsplorer' ) . '</p>';
	}

	$out = '<div class="docsplorer-grid">';
	foreach ( $documents as $doc ) {
		$attachment_id = (int) get_post_meta( $doc->ID, '_docsplorer_file_id', true );
		if ( ! $attachment_id ) {
			continue;
		}
		$url = wp_get_attachment_url( $attachment_id );
		if ( ! $url ) {
			continue;
		}
		$icon = docsplorer_get_file_type( $url );
		$out .= '<a class="docsplorer-card docsplorer-card--file" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">'
				. docsplorer_file_icon_svg( $icon['label'], $icon['color'] )
				. '<span class="docsplorer-name">' . esc_html( get_the_title( $doc ) ) . '</span>'
				. '</a>';
	}
	$out .= '</div>';

	return $out;
}

/**
 * -----------------------------------------------------------------
 * 5. MINIMAL INLINE STYLING (printed once per page load)
 * -----------------------------------------------------------------
 */
function docsplorer_print_styles_once() {
	static $printed = false;
	if ( $printed ) {
		return;
	}
	$printed = true;
	?>
	<style>
		.docsplorer-browser { max-width: 900px; }
		.docsplorer-breadcrumb {
			margin-bottom: 1.25em;
			font-size: 0.95em;
			color: #555;
		}
		.docsplorer-breadcrumb a { color: #2271b1; text-decoration: none; }
		.docsplorer-breadcrumb a:hover { text-decoration: underline; }
		.docsplorer-breadcrumb .docsplorer-current { font-weight: 600; color: #23282d; }

		.docsplorer-grid {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
			gap: 1.25em;
			margin: 0 0 1.75em;
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
			color: #333;
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
		.docsplorer-empty { font-style: italic; color: #777; }
	</style>
	<?php
}
