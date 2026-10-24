<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Delivery;

/**
 * Asks the API for one plugin's or theme's rule file under the installed rule schema. A plain wp_remote_get, so the
 * status can be told apart: a 200 is a candidate file; a 404 marked `none` means no rules are published; a 404
 * marked `schema-retired` means this Shield no longer receives rules; anything else leaves the stored file as it is.
 * No token or query string is sent, so every site shares the edge's copy, and the user agent never names the site.
 */
class FeedClient {

	public const BASE_URL = 'https://api.orlesecurity.com/api/apto/v1/virtual-patches';

	public const MARKER_HEADER = 'x-orle-virtual-patches';

	private const TIMEOUT = 10;

	private string $shieldVersion;

	public function __construct( string $shieldVersion ) {
		$this->shieldVersion = $shieldVersion;
	}

	public function fetch( string $kind, string $slug, int $schema ) :FeedResponse {
		$response = wp_remote_get( self::BASE_URL.'/'.$schema.'/'.$kind.'/'.\rawurlencode( $slug ), [
			'timeout'             => self::TIMEOUT,
			'redirection'         => 0,
			'user-agent'          => 'Shield/'.$this->shieldVersion,
			// One byte over the limit, so an oversized file is refused rather than silently cut short.
			'limit_response_size' => RuleFileVerifier::MAX_BYTES + 1,
		] );
		if ( is_wp_error( $response ) ) {
			return new FeedResponse( FeedResponse::TRANSPORT );
		}
		$code = (int)wp_remote_retrieve_response_code( $response );
		$marker = \strtolower( (string)wp_remote_retrieve_header( $response, self::MARKER_HEADER ) );
		if ( $code === 200 ) {
			return new FeedResponse( FeedResponse::FILE, (string)wp_remote_retrieve_body( $response ) );
		}
		if ( $code === 404 && $marker === 'none' ) {
			return new FeedResponse( FeedResponse::NONE );
		}
		if ( $code === 404 && $marker === 'schema-retired' ) {
			return new FeedResponse( FeedResponse::SCHEMA_RETIRED );
		}
		return new FeedResponse( FeedResponse::UNCHANGED );
	}
}
