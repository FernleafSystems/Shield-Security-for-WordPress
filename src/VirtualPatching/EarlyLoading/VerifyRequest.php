<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

use FernleafSystems\Wordpress\Services\Services;

/**
 * The loopback request the installer makes to learn whether the stub ran. It carries a short-lived token
 * so only Shield's own requests get an answer.
 */
class VerifyRequest {

	public const QUERY_KEY = 'shield_early_check';

	private const TOKEN_TTL = 120;

	private string $optionKey;

	public function __construct( string $optionKey ) {
		$this->optionKey = $optionKey;
	}

	public function issueToken() :string {
		$token = \bin2hex( \random_bytes( 16 ) );
		Services::WpGeneral()->updateOption( $this->optionKey, [
			'hash'    => \hash( 'sha256', $token ),
			'expires' => Services::Request()->ts() + self::TOKEN_TTL,
		] );
		return $token;
	}

	public function isValidToken( string $token ) :bool {
		$stored = Services::WpGeneral()->getOption( $this->optionKey );
		return $token !== ''
			   && \is_array( $stored )
			   && \is_string( $stored[ 'hash' ] ?? null )
			   && (int)( $stored[ 'expires' ] ?? 0 ) >= Services::Request()->ts()
			   && \hash_equals( $stored[ 'hash' ], \hash( 'sha256', $token ) );
	}

	public function respondIfRequested() :void {
		$token = $_GET[ self::QUERY_KEY ] ?? null;
		if ( \is_string( $token ) && $this->isValidToken( $token ) ) {
			\nocache_headers();
			\wp_send_json( [ 'shield_early' => [ 'loaded' => \defined( 'SHIELD_EARLY_LOADED' ) ] ] );
		}
	}

	/**
	 * Reads a verification response body; null when it is not one.
	 */
	public static function parseLoaded( string $body ) :?bool {
		$decoded = \json_decode( $body, true );
		$loaded = \is_array( $decoded ) ? ( $decoded[ 'shield_early' ][ 'loaded' ] ?? null ) : null;
		return \is_bool( $loaded ) ? $loaded : null;
	}
}
