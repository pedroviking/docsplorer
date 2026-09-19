<?php
/**
 * Small shared helpers used by both the frontend shortcode and the
 * admin Document Manager screen -- mapping a file URL to a short
 * type badge (PDF/DOC/XLS/...) and rendering the icon SVGs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Map a file extension to a short label + colour, so files get a
 * recognisable little badge instead of a generic icon.
 */
function docsplorer_get_file_type( $url ) {
	$ext = strtolower( pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );

	$map = array(
		'pdf'  => array( 'label' => 'PDF', 'color' => '#e2574c' ),
		'doc'  => array( 'label' => 'DOC', 'color' => '#2b579a' ),
		'docx' => array( 'label' => 'DOC', 'color' => '#2b579a' ),
		'xls'  => array( 'label' => 'XLS', 'color' => '#217346' ),
		'xlsx' => array( 'label' => 'XLS', 'color' => '#217346' ),
		'ppt'  => array( 'label' => 'PPT', 'color' => '#d24726' ),
		'pptx' => array( 'label' => 'PPT', 'color' => '#d24726' ),
		'zip'  => array( 'label' => 'ZIP', 'color' => '#8a8a8a' ),
		'jpg'  => array( 'label' => 'IMG', 'color' => '#9c27b0' ),
		'jpeg' => array( 'label' => 'IMG', 'color' => '#9c27b0' ),
		'png'  => array( 'label' => 'IMG', 'color' => '#9c27b0' ),
	);

	if ( isset( $map[ $ext ] ) ) {
		return $map[ $ext ];
	}

	return array(
		'label' => $ext ? strtoupper( substr( $ext, 0, 4 ) ) : 'FIL',
		'color' => '#607d8b',
	);
}

function docsplorer_file_icon_svg( $label, $color ) {
	return '<svg class="docsplorer-icon" viewBox="0 0 48 60" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
		<path d="M4 2h24l16 16v36a4 4 0 0 1-4 4H4a4 4 0 0 1-4-4V6a4 4 0 0 1 4-4Z" fill="#ffffff" stroke="#cfd4d8" stroke-width="1.5"/>
		<path d="M28 2v12a4 4 0 0 0 4 4h12Z" fill="#e9edf0" stroke="#cfd4d8" stroke-width="1.5"/>
		<rect x="0" y="42" width="48" height="18" rx="3" fill="' . esc_attr( $color ) . '"/>
		<text x="24" y="55" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="12" font-weight="bold" fill="#ffffff">' . esc_html( $label ) . '</text>
	</svg>';
}
