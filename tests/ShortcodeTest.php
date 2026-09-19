<?php

require_once __DIR__ . '/TestCase.php';

class ShortcodeTest extends Docsplorer_TestCase {

	const TAXONOMY = 'docsplorer_folder';

	// ---------------------------------------------------------------
	// docsplorer_render_breadcrumb()
	// ---------------------------------------------------------------

	public function test_breadcrumb_at_root_only_shows_home_link() {
		$this->mock_escaping_functions();
		WP_Mock::userFunction( 'remove_query_arg' )->andReturn( 'https://example.test/docs' );

		$html = docsplorer_render_breadcrumb( false, self::TAXONOMY );

		$this->assertStringContainsString( 'docsplorer-breadcrumb', $html );
		$this->assertStringContainsString( 'Home', $html );
		$this->assertStringContainsString( 'https://example.test/docs', $html );
		// No current-folder marker should be printed when nothing is selected.
		$this->assertStringNotContainsString( 'docsplorer-current', $html );
	}

	public function test_breadcrumb_lists_ancestors_from_root_down_to_current() {
		$this->mock_escaping_functions();

		$decade = $this->make_term( 5, '2020s', '2020s' );
		$year   = $this->make_term( 10, '2023', '2023' );
		$q1     = $this->make_term( 20, 'Q1', 'q1' );

		WP_Mock::userFunction( 'remove_query_arg' )->andReturn( 'https://example.test/docs' );
		// Real WordPress returns ancestors nearest-first ([year, decade]);
		// the function is responsible for reversing that into root-first
		// order for display -- this test would fail if that reversal
		// were ever removed.
		WP_Mock::userFunction( 'get_ancestors' )
			->with( 20, self::TAXONOMY, 'taxonomy' )
			->andReturn( array( 10, 5 ) );
		WP_Mock::userFunction( 'get_term' )->with( 10, self::TAXONOMY )->andReturn( $year );
		WP_Mock::userFunction( 'get_term' )->with( 5, self::TAXONOMY )->andReturn( $decade );
		WP_Mock::userFunction( 'add_query_arg' )
			->andReturnUsing(
				function ( $key, $value, $url ) {
					return $url . '?' . $key . '=' . $value;
				}
			);

		$html = docsplorer_render_breadcrumb( $q1, self::TAXONOMY );

		$decade_pos  = strpos( $html, '2020s' );
		$year_pos    = strpos( $html, '2023' );
		$current_pos = strpos( $html, 'Q1' );

		$this->assertNotFalse( $decade_pos );
		$this->assertNotFalse( $year_pos );
		$this->assertNotFalse( $current_pos );
		$this->assertTrue( $decade_pos < $year_pos, 'Decade should appear before year in the breadcrumb.' );
		$this->assertTrue( $year_pos < $current_pos, 'Year should appear before the current folder.' );
		$this->assertStringContainsString( 'docsplorer-current', $html );
	}

	// ---------------------------------------------------------------
	// docsplorer_render_subfolders()
	// ---------------------------------------------------------------

	public function test_subfolders_returns_empty_string_when_there_are_none() {
		WP_Mock::userFunction( 'get_terms' )->andReturn( array() );

		$html = docsplorer_render_subfolders( false, self::TAXONOMY );

		$this->assertSame( '', $html );
	}

	public function test_subfolders_renders_a_card_per_folder() {
		$this->mock_escaping_functions();

		$decades = array(
			$this->make_term( 1, '2010s', '2010s' ),
			$this->make_term( 2, '2020s', '2020s' ),
		);

		WP_Mock::userFunction( 'get_terms' )->andReturn( $decades );
		WP_Mock::userFunction( 'remove_query_arg' )->andReturn( 'https://example.test/docs' );
		WP_Mock::userFunction( 'add_query_arg' )
			->andReturnUsing(
				function ( $key, $value, $url ) {
					return $url . '?' . $key . '=' . $value;
				}
			);

		$html = docsplorer_render_subfolders( false, self::TAXONOMY );

		$this->assertStringContainsString( '2010s', $html );
		$this->assertStringContainsString( '2020s', $html );
		$this->assertStringContainsString( 'docsplorer_folder=2010s', $html );
		$this->assertStringContainsString( 'docsplorer_folder=2020s', $html );
		$this->assertSame( 2, substr_count( $html, 'docsplorer-card--folder' ) );
	}

	// ---------------------------------------------------------------
	// docsplorer_folder_icon_svg()
	// ---------------------------------------------------------------

	public function test_folder_icon_is_an_svg() {
		$html = docsplorer_folder_icon_svg();

		$this->assertStringStartsWith( '<svg', trim( $html ) );
	}

	// ---------------------------------------------------------------
	// docsplorer_render_documents()
	// ---------------------------------------------------------------

	public function test_documents_returns_empty_string_at_root() {
		// No current folder selected at all (the top-level "All" view)
		// -- there's nothing to query yet.
		$html = docsplorer_render_documents( false, self::TAXONOMY );

		$this->assertSame( '', $html );
	}

	public function test_empty_message_shown_when_folder_has_no_subfolders_either() {
		$this->mock_escaping_functions();

		$folder = $this->make_term( 5, '2023', '2023' );

		WP_Mock::userFunction( 'get_posts' )->andReturn( array() );
		WP_Mock::userFunction( 'wp_count_terms' )->andReturn( 0 );

		$html = docsplorer_render_documents( $folder, self::TAXONOMY );

		$this->assertStringContainsString( 'No documents in this folder.', $html );
	}

	/**
	 * Regression test for the bug where a folder containing only
	 * subfolders (no documents of its own) still showed "No documents
	 * in this folder." underneath them.
	 */
	public function test_empty_message_hidden_when_folder_has_subfolders() {
		$folder = $this->make_term( 5, '2020s', '2020s' );

		WP_Mock::userFunction( 'get_posts' )->andReturn( array() );
		WP_Mock::userFunction( 'wp_count_terms' )->andReturn( 3 ); // e.g. 2021, 2022, 2023

		$html = docsplorer_render_documents( $folder, self::TAXONOMY );

		$this->assertSame( '', $html );
	}

	/**
	 * Regression test for the bug where a document filed under a child
	 * folder (e.g. "2023") also showed up when viewing the parent
	 * folder (e.g. "2020s"), because tax_query's include_children
	 * defaults to true.
	 */
	public function test_get_posts_is_called_with_include_children_disabled() {
		$folder        = $this->make_term( 5, '2023', '2023' );
		$captured_args = null;

		WP_Mock::userFunction( 'get_posts' )
			->once()
			->andReturnUsing(
				function ( $args ) use ( &$captured_args ) {
					$captured_args = $args;
					return array();
				}
			);
		WP_Mock::userFunction( 'wp_count_terms' )->andReturn( 0 );
		$this->mock_escaping_functions();

		docsplorer_render_documents( $folder, self::TAXONOMY );

		$this->assertIsArray( $captured_args, 'get_posts() should have been called.' );
		$this->assertFalse( $captured_args['tax_query'][0]['include_children'] );
	}

	public function test_documents_renders_a_card_with_download_link() {
		$this->mock_escaping_functions();

		$folder = $this->make_term( 5, '2023', '2023' );
		$post   = $this->make_post( 101 );

		WP_Mock::userFunction( 'get_posts' )->andReturn( array( $post ) );
		WP_Mock::userFunction( 'get_post_meta' )
			->with( 101, '_docsplorer_file_id', true )
			->andReturn( 42 );
		WP_Mock::userFunction( 'wp_get_attachment_url' )
			->with( 42 )
			->andReturn( 'https://example.test/uploads/2023/09/minutes.pdf' );
		WP_Mock::userFunction( 'wp_parse_url' )
			->andReturnUsing(
				function ( $url, $component = -1 ) {
					return parse_url( $url, $component );
				}
			);
		WP_Mock::userFunction( 'get_the_title' )
			->with( $post )
			->andReturn( 'Referat generalforsamling 2023' );

		$html = docsplorer_render_documents( $folder, self::TAXONOMY );

		$this->assertStringContainsString( 'https://example.test/uploads/2023/09/minutes.pdf', $html );
		$this->assertStringContainsString( 'Referat generalforsamling 2023', $html );
		$this->assertStringContainsString( 'docsplorer-card--file', $html );
	}

	public function test_documents_without_an_attached_file_are_skipped() {
		$this->mock_escaping_functions();

		$folder = $this->make_term( 5, '2023', '2023' );
		$post   = $this->make_post( 102 );

		WP_Mock::userFunction( 'get_posts' )->andReturn( array( $post ) );
		// No file was ever attached to this document post.
		WP_Mock::userFunction( 'get_post_meta' )
			->with( 102, '_docsplorer_file_id', true )
			->andReturn( 0 );

		$html = docsplorer_render_documents( $folder, self::TAXONOMY );

		$this->assertStringNotContainsString( 'docsplorer-card--file', $html );
	}
}
