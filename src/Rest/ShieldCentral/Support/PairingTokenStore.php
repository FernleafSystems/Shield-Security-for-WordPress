<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support;

class PairingTokenStore {

	public const OPTION_KEY = 'shield_central_pairing_tokens';

	public function storeToken( string $token, int $ttlSeconds = 900 ) :void {
		$tokens = $this->read();
		$tokens[ $this->hashToken( $token ) ] = [
			'expires_at' => \time() + $ttlSeconds,
			'created_at' => \time(),
		];

		$this->write( $this->pruneExpired( $tokens ) );
	}

	public function isValid( string $token ) :bool {
		$tokens = $this->pruneExpired( $this->read() );
		$this->write( $tokens );

		$hash = $this->hashToken( $token );
		return $token !== '' && isset( $tokens[ $hash ] );
	}

	public function forget( string $token ) :void {
		$tokens = $this->read();
		unset( $tokens[ $this->hashToken( $token ) ] );
		$this->write( $tokens );
	}

	private function read() :array {
		$stored = \get_option( self::OPTION_KEY, [] );
		return \is_array( $stored ) ? $stored : [];
	}

	private function write( array $tokens ) :void {
		\update_option( self::OPTION_KEY, $tokens, false );
	}

	private function hashToken( string $token ) :string {
		return \hash( 'sha256', $token );
	}

	private function pruneExpired( array $tokens ) :array {
		$now = \time();
		return \array_filter(
			$tokens,
			static fn( $token ) :bool => \is_array( $token ) && (int)( $token[ 'expires_at' ] ?? 0 ) > $now
		);
	}
}
