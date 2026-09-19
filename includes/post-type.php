<?php
/**
 * Post type + hierarchical taxonomy registration, and the
 * "Select File" meta box on the (now hidden) document edit screen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * -----------------------------------------------------------------
 * 1. CUSTOM POST TYPE: one post = one document
 * -----------------------------------------------------------------
 * We use a real post type (not a raw attachment) so we get the
 * standard WP admin list table, Quick Edit, and Bulk Edit for free.
 */
function docsplorer_register_post_type() {
	register_post_type(
		'docsplorer_document',
		array(
			'label'              => __( 'Documents', 'docsplorer' ),
			'labels'             => array(
				'name'          => __( 'Documents', 'docsplorer' ),
				'singular_name' => __( 'Document', 'docsplorer' ),
				'add_new_item'  => __( 'Add New Document', 'docsplorer' ),
				'edit_item'     => __( 'Edit Document', 'docsplorer' ),
			),
			'public'             => false,      // no single document pages, we render via shortcode
			'show_ui'            => true,
			'show_in_menu'       => false,     // we add our own single top-level "Documents" menu instead
			'menu_icon'          => 'dashicons-media-document',
			'supports'           => array( 'title' ),
			'capability_type'    => 'post',      // reuse normal Editor/Admin capabilities
			'map_meta_cap'       => true,
			'hierarchical'       => false,
			'show_in_rest'       => false,
		)
	);
}
add_action( 'init', 'docsplorer_register_post_type' );

/**
 * -----------------------------------------------------------------
 * 2. HIERARCHICAL TAXONOMY: the "folders" (Decade > Year)
 * -----------------------------------------------------------------
 * Hierarchical, so WP still manages parent/child relationships,
 * counts, get_ancestors(), etc. for us -- we just don't show its
 * own admin screen, since folder management now happens entirely
 * inside the Document Manager screen.
 */
function docsplorer_register_taxonomy() {
	register_taxonomy(
		'docsplorer_folder',
		'docsplorer_document',
		array(
			'label'             => __( 'Folders', 'docsplorer' ),
			'hierarchical'      => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_menu'      => false,     // hide the separate "Folders" term-admin screen
			'show_in_quick_edit'=> true,
			'query_var'         => false,
			'rewrite'           => false,
			'show_in_rest'      => false,
		)
	);
}
add_action( 'init', 'docsplorer_register_taxonomy' );

/**
 * -----------------------------------------------------------------
 * 3. FILE-ATTACH META BOX (admin side)
 * -----------------------------------------------------------------
 * Adds a "Select File" button on the document edit screen that opens
 * the normal WP Media uploader and stores the chosen attachment ID.
 */
function docsplorer_add_file_meta_box() {
	add_meta_box(
		'docsplorer_file_box',
		__( 'Document File', 'docsplorer' ),
		'docsplorer_render_file_meta_box',
		'docsplorer_document',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'docsplorer_add_file_meta_box' );

function docsplorer_render_file_meta_box( $post ) {
	wp_nonce_field( 'docsplorer_save_file', 'docsplorer_file_nonce' );
	$attachment_id = (int) get_post_meta( $post->ID, '_docsplorer_file_id', true );
	$file_url      = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
	$file_name     = $attachment_id ? basename( get_attached_file( $attachment_id ) ) : '';
	?>
	<p>
		<button type="button" class="button" id="docsplorer_select_file"><?php esc_html_e( 'Select File', 'docsplorer' ); ?></button>
		<span id="docsplorer_file_name"><?php echo esc_html( $file_name ); ?></span>
	</p>
	<input type="hidden" name="docsplorer_file_id" id="docsplorer_file_id" value="<?php echo esc_attr( $attachment_id ); ?>" />
	<?php if ( $file_url ) : ?>
		<p><a href="<?php echo esc_url( $file_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View current file', 'docsplorer' ); ?></a></p>
	<?php endif; ?>
	<script>
	jQuery(function ($) {
		var frame;
		$('#docsplorer_select_file').on('click', function (e) {
			e.preventDefault();
			if (frame) { frame.open(); return; }
			frame = wp.media({
				title: '<?php echo esc_js( __( 'Select or upload a document', 'docsplorer' ) ); ?>',
				button: { text: '<?php echo esc_js( __( 'Use this file', 'docsplorer' ) ); ?>' },
				multiple: false
			});
			frame.on('select', function () {
				var att = frame.state().get('selection').first().toJSON();
				$('#docsplorer_file_id').val(att.id);
				$('#docsplorer_file_name').text(att.filename || att.title);
			});
			frame.open();
		});
	});
	</script>
	<?php
}

function docsplorer_save_file_meta( $post_id ) {
	if ( ! isset( $_POST['docsplorer_file_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['docsplorer_file_nonce'] ) ), 'docsplorer_save_file' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( isset( $_POST['docsplorer_file_id'] ) ) {
		update_post_meta( $post_id, '_docsplorer_file_id', absint( $_POST['docsplorer_file_id'] ) );
	}
}
add_action( 'save_post_docsplorer_document', 'docsplorer_save_file_meta' );

function docsplorer_admin_enqueue( $hook ) {
	global $post_type;
	if ( 'docsplorer_document' === $post_type && in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		wp_enqueue_media();
	}
}
add_action( 'admin_enqueue_scripts', 'docsplorer_admin_enqueue' );
