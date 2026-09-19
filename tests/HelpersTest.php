<?php

require_once __DIR__ . '/TestCase.php';

class HelpersTest extends Docsplorer_TestCase {

	protected function mock_wp_parse_url() {
		// wp_parse_url() is WordPress's own wrapper around PHP's native
		// parse_url() (it just smooths over some old-PHP edge cases we
		// don't need to care about here), so a real parse_url() call is
		// a perfectly accurate stand-in for it in tests.
		WP_Mock::userFunction( 'wp_parse_url' )
			->andReturnUsing(
				function ( $url, $component = -1 ) {
					return parse_url( $url, $component );
				}
			);
	}

	public function test_pdf_extension_gets_red_pdf_badge() {
		$this->mock_wp_parse_url();

		$result = docsplorer_get_file_type( 'https://example.test/uploads/2026/09/minutes.pdf' );

		$this->assertSame( 'PDF', $result['label'] );
		$this->assertSame( '#e2574c', $result['color'] );
	}

	public function test_extension_matching_is_case_insensitive() {
		$this->mock_wp_parse_url();

		$result = docsplorer_get_file_type( 'https://example.test/uploads/Budget.XLSX' );

		$this->assertSame( 'XLS', $result['label'] );
	}

	public function test_docx_and_doc_share_the_same_badge() {
		$this->mock_wp_parse_url();

		$doc  = docsplorer_get_file_type( 'https://example.test/uploads/report.doc' );
		$docx = docsplorer_get_file_type( 'https://example.test/uploads/report.docx' );

		$this->assertSame( $doc, $docx );
	}

	public function test_unknown_extension_falls_back_to_generic_grey_badge() {
		$this->mock_wp_parse_url();

		$result = docsplorer_get_file_type( 'https://example.test/uploads/archive.rar' );

		$this->assertSame( 'RAR', $result['label'] );
		$this->assertSame( '#607d8b', $result['color'] );
	}

	public function test_url_with_no_extension_returns_fil_placeholder() {
		$this->mock_wp_parse_url();

		$result = docsplorer_get_file_type( 'https://example.test/uploads/README' );

		$this->assertSame( 'FIL', $result['label'] );
	}

	public function test_query_string_on_the_url_is_ignored() {
		$this->mock_wp_parse_url();

		// The extension check should look at the URL path only, so a
		// cache-busting query string shouldn't confuse it.
		$result = docsplorer_get_file_type( 'https://example.test/uploads/minutes.pdf?ver=abc123' );

		$this->assertSame( 'PDF', $result['label'] );
	}

	public function test_file_icon_svg_embeds_the_label_and_color() {
		$this->mock_escaping_functions();

		$svg = docsplorer_file_icon_svg( 'PDF', '#e2574c' );

		$this->assertStringStartsWith( '<svg', trim( $svg ) );
		$this->assertStringContainsString( 'PDF', $svg );
		$this->assertStringContainsString( '#e2574c', $svg );
	}
}
