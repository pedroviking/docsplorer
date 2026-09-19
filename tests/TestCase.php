<?php

/**
 * Common setup for all Docsplorer test cases.
 *
 * Extends WP_Mock's own TestCase, which handles calling
 * WP_Mock::setUp() / WP_Mock::tearDown() (and closing Mockery) around
 * every test for us.
 */
class Docsplorer_TestCase extends \WP_Mock\Tools\TestCase {

	public function setUp(): void {
		parent::setUp();

		// The plugin checks the return value of several WordPress
		// functions (get_terms(), get_ancestors(), wp_count_terms()...)
		// with is_wp_error() before using it. None of the tests are
		// simulating an actual WP_Error, so every test gets this same
		// safe default -- it means individual tests don't each need to
		// remember to stub it themselves.
		WP_Mock::userFunction( 'is_wp_error' )->andReturn( false );
	}

	/**
	 * Stub the escaping/translation functions that appear in almost
	 * every render function, but whose own behaviour isn't what we're
	 * testing here -- they just need to pass their input straight
	 * through so we can make assertions on the surrounding HTML.
	 *
	 * Call this from a test's setUp() (or at the top of an individual
	 * test) before invoking any docsplorer_* function.
	 */
	protected function mock_escaping_functions() {
		foreach ( array( 'esc_html', 'esc_url', 'esc_attr', 'esc_html__', 'esc_attr__', '__' ) as $function ) {
			WP_Mock::passthruFunction( $function );
		}
	}

	/**
	 * Build a lightweight stand-in for a WP_Term. The real class has
	 * far more properties, but the plugin only ever reads these.
	 */
	protected function make_term( $term_id, $name, $slug ) {
		$term          = new stdClass();
		$term->term_id = $term_id;
		$term->name    = $name;
		$term->slug    = $slug;
		$term->count   = 0; // present on a real WP_Term even if this plugin doesn't use it
		return $term;
	}

	/**
	 * Build a lightweight stand-in for a WP_Post. The plugin only ever
	 * reads ->ID directly; everything else goes through get_the_title()
	 * and get_post_meta(), which tests mock separately.
	 */
	protected function make_post( $id ) {
		$post     = new stdClass();
		$post->ID = $id;
		return $post;
	}
}
