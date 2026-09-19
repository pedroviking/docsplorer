<?php
/**
 * Plugin Name:       Docsplorer
 * Description:       Nested-folder document library (e.g. Decade > Year) with drag-and-drop admin upload and a frontend breadcrumb browser shortcode [docsplorer_documents].
 * Version:           1.6.1
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Peder Møller
 * License:           GPL v2 or later
 * Text Domain:       docsplorer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'DOCSPLORER_PATH', plugin_dir_path( __FILE__ ) );
define( 'DOCSPLORER_URL', plugin_dir_url( __FILE__ ) );
define( 'DOCSPLORER_VERSION', '1.6.1' );

/**
 * Module map, in load order:
 *  - helpers.php      shared file-type/icon helpers (no dependencies)
 *  - post-type.php    the docsplorer_document post type + docsplorer_folder taxonomy
 *  - shortcode.php     [docsplorer_documents] frontend browser
 *  - admin-manager.php the "Docsplorer" admin screen + its AJAX endpoints
 */
require_once DOCSPLORER_PATH . 'includes/helpers.php';
require_once DOCSPLORER_PATH . 'includes/post-type.php';
require_once DOCSPLORER_PATH . 'includes/shortcode.php';
require_once DOCSPLORER_PATH . 'includes/admin-manager.php';
