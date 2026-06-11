<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support;

class PairingSessionStore {

	private const TRANSIENT_PREFIX = 'shield_central_pairing_session_';

	private const TTL_SECONDS = 600;

	public function create( string $pairingToken, string $siteUrl = '' ) :string {
		$session = $this->generateSessionId();
		\set_site_transient( $this->transientKey( $session ), [
			'token_hash' => \hash( 'sha256', $pairingToken ),
			'site_url'   => $siteUrl,
			'created_at'  => \time(),
		], self::TTL_SECONDS );

		return $session;
	}

	public function exists( string $session ) :bool {
		return !empty( $this->read( $session ) );
	}

	public function read( string $session ) :array {
		if ( $session === '' ) {
			return [];
		}

		$stored = \get_site_transient( $this->transientKey( $session ) );
		return \is_array( $stored ) ? $stored : [];
	}

	public function delete( string $session ) :void {
		if ( $session !== '' ) {
			\delete_site_transient( $this->transientKey( $session ) );
		}
	}

	private function transientKey( string $session ) :string {
		return self::TRANSIENT_PREFIX.\preg_replace( '/[^a-zA-Z0-9_\-]/', '', $session );
	}

	private function generateSessionId() :string {
		return \function_exists( 'wp_generate_uuid4' ) ? \wp_generate_uuid4() : \bin2hex( \random_bytes( 16 ) );
	}
}
