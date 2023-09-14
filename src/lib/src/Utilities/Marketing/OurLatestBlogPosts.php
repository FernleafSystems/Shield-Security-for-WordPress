<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Marketing;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class OurLatestBlogPosts {

	public function retrieve() :array {
		$posts = Transient::Get( 'apto-shield-latest-blog-posts' );
		if ( !\is_array( $posts ) ) {
			$rawPosts = @\json_decode(
				Services::HttpRequest()
						->getContent( 'https://getshieldsecurity.com/wp-json/wp/v2/posts?per_page=4&type=download' ),
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
						'excerpt' => wp_strip_all_tags( $post[ 'excerpt' ][ 'rendered' ] ?? 'Excerpt' ),
						'href'    => URL::Build( $post[ 'link' ], [
							'utm_source'   => 'in-plugin',
							'utm_medium'   => 'wp-admin',
							'utm_content'  => 'dashboard-widget',
							'utm_campaign' => 'shield-security-pro',
						] ),
					];
				},
				\is_array( $rawPosts ) ? $rawPosts : []
			) ), 0, 2 );

			Transient::Set( 'apto-shield-latest-blog-posts', $posts, \WEEK_IN_SECONDS );
		}
		return $posts;
	}
}