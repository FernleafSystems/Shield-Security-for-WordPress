<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Marketing;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class OurLatestBlogPosts {

	public function retrieve( int $limit = 2 ) :array {
		$posts = Transient::Get( 'apto-shield-latest-blog-posts' );
		if ( !\is_array( $posts ) ) {
			$rawPosts = @\json_decode(
				Services::HttpRequest()->getContent(
					URL::Build( 'https://getshieldsecurity.com/wp-json/wp/v2/posts', [ 'per_page' => '5' ] )
				),
				true
			);

			$posts = \array_slice( \array_filter( \array_map(
				function ( $post ) {
					if ( !\is_array( $post ) || $post[ 'type' ] !== 'post' || empty( $post[ 'id' ] )
						 || empty( $post[ 'link' ] ) ) {
						return null;
					}
					return [
						'id'      => $post[ 'id' ],
						'title'   => $post[ 'title' ][ 'rendered' ] ?? 'Unknown title',
						'excerpt' => esc_js( wp_strip_all_tags( $post[ 'excerpt' ][ 'rendered' ] ?? 'Excerpt' ) ),
						'href'    => URL::Build( $post[ 'link' ], [
							'utm_source'   => 'in-plugin',
							'utm_medium'   => 'wp-admin',
							'utm_content'  => 'dashboard-widget',
							'utm_campaign' => 'shield-security-pro',
						] ),
					];
				},
				\is_array( $rawPosts ) ? $rawPosts : []
			) ), 0, $limit );

			Transient::Set( 'apto-shield-latest-blog-posts', $posts, \DAY_IN_SECONDS*2 );
		}
		return $posts;
	}

	/**
	 * https://plugins.svn.wordpress.org/wpuntexturize/trunk/wpuntexturize.php
	 */
	private function getUntexturiseReplacements() :array {
		return [
			'&#8216;' => "'", // left single quotation mark
			'&#8217;' => "'", // right single quotation mark
			'&#8218;' => "'", // single low 9 quotation mark
			'&#8220;' => '"', // left double quotation mark
			'&#8221;' => '"', // right double quotation mark
			'&#8222;' => '"', // double low 9 quotation mark
			'&#8242;' => "'", // prime mark
			'&#8243;' => '"', // double prime mark
		];
	}
}