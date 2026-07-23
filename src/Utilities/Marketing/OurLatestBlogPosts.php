<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Marketing;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;
use FernleafSystems\Wordpress\Services\Utilities\URL;

/**
 *  https://plugins.svn.wordpress.org/wpuntexturize/trunk/wpuntexturize.php
 */
class OurLatestBlogPosts {

	private const TRANSIENT_KEY = 'apto-shield-latest-blog-posts';

	public function retrieve( int $limit = 2 ) :array {
		$cached = Transient::Get( self::TRANSIENT_KEY );
		if ( \is_array( $cached ) ) {
			if ( $cached === [] ) {
				return [];
			}

			$posts = $this->normalisePosts( $cached, false );
			if ( $posts !== [] ) {
				if ( $posts !== $cached ) {
					Transient::Set( self::TRANSIENT_KEY, $posts, \DAY_IN_SECONDS*2 );
				}
				return \array_slice( $posts, 0, $limit );
			}

			Transient::Delete( self::TRANSIENT_KEY );
		}

		$rawPosts = @\json_decode(
			Services::HttpRequest()->getContent(
				URL::Build( 'https://getshieldsecurity.com/wp-json/wp/v2/posts', [ 'per_page' => '5' ] )
			),
			true
		);
		$posts = $this->normalisePosts( \is_array( $rawPosts ) ? $rawPosts : [], true );
		Transient::Set( self::TRANSIENT_KEY, $posts, \DAY_IN_SECONDS*2 );

		return \array_slice( $posts, 0, $limit );
	}

	private function normalisePosts( array $posts, bool $isFresh ) :array {
		$normalised = [];
		foreach ( $posts as $post ) {
			$normalisedPost = $this->normalisePost( $post, $isFresh );
			if ( $normalisedPost !== null ) {
				$normalised[] = $normalisedPost;
			}
		}
		return $normalised;
	}

	private function normalisePost( $post, bool $isFresh ) :?array {
		if ( !\is_array( $post ) ) {
			return null;
		}

		$id = $post[ 'id' ] ?? null;
		$href = $post[ $isFresh ? 'link' : 'href' ] ?? '';
		if ( ( $isFresh && ( $post[ 'type' ] ?? '' ) !== 'post' ) || empty( $id ) || !\is_scalar( $id )
			 || !\is_string( $href ) || $href === '' ) {
			return null;
		}

		if ( $isFresh ) {
			$title = \is_array( $post[ 'title' ] ?? null ) ? ( $post[ 'title' ][ 'rendered' ] ?? 'Unknown title' ) : 'Unknown title';
			$excerpt = \is_array( $post[ 'excerpt' ] ?? null ) ? ( $post[ 'excerpt' ][ 'rendered' ] ?? 'Excerpt' ) : 'Excerpt';
			$href = URL::Build( $href, [
				'utm_source'   => 'in-plugin',
				'utm_medium'   => 'wp-admin',
				'utm_content'  => 'dashboard-widget',
				'utm_campaign' => 'shield-security-pro',
			] );
		}
		else {
			$title = $post[ 'title' ] ?? 'Unknown title';
			$excerpt = $post[ 'excerpt' ] ?? 'Excerpt';
		}

		return [
			'id'      => $id,
			'title'   => \is_scalar( $title ) ? (string)$title : 'Unknown title',
			'excerpt' => $isFresh ?
				esc_js( wp_strip_all_tags( \is_scalar( $excerpt ) ? (string)$excerpt : 'Excerpt' ) ) :
				( \is_scalar( $excerpt ) ? (string)$excerpt : 'Excerpt' ),
			'href'    => $href,
		];
	}
}
