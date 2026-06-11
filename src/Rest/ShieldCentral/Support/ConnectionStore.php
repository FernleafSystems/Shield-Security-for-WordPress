<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support;

class ConnectionStore {

	public const OPTION_KEY = 'shield_central_connection';

	public const AUTH_MODE = 'hmac';

	public function create( string $siteUuid ) :array {
		$secret = $this->generateSecret();
		$baseEndpoint = $this->restBaseEndpoint();

		$this->write( [
			'site_uuid'             => $siteUuid,
			'shared_secret_hash'    => $this->hashSecret( $secret ),
			'preferred_auth_mode'   => self::AUTH_MODE,
			'established_auth_modes' => [ self::AUTH_MODE ],
			'trusted_endpoint_url'  => $baseEndpoint,
			'callback_endpoint_url' => $baseEndpoint,
			'paired_at'             => \time(),
		] );

		return [
			'preferred_auth_mode'    => self::AUTH_MODE,
			'established_auth_modes' => [ self::AUTH_MODE ],
			'connection_material'    => [
				'shared_secret' => $secret,
			],
			'trusted_endpoint_url'   => $baseEndpoint,
			'callback_endpoint_url'  => $baseEndpoint,
		];
	}

	public function verify( string $siteUuid, array $connectionMaterial ) :bool {
		$connection = $this->read();
		$secret = (string)( $connectionMaterial[ 'shared_secret' ] ?? '' );

		return $siteUuid !== ''
			   && $secret !== ''
			   && (string)( $connection[ 'site_uuid' ] ?? '' ) === $siteUuid
			   && !empty( $connection[ 'shared_secret_hash' ] )
			   && \hash_equals( (string)$connection[ 'shared_secret_hash' ], $this->hashSecret( $secret ) );
	}

	public function delete( string $siteUuid, array $connectionMaterial ) :bool {
		if ( !$this->verify( $siteUuid, $connectionMaterial ) ) {
			return false;
		}

		\delete_option( self::OPTION_KEY );
		return true;
	}

	public function read() :array {
		$stored = \get_option( self::OPTION_KEY, [] );
		return \is_array( $stored ) ? $stored : [];
	}

	private function write( array $connection ) :void {
		\update_option( self::OPTION_KEY, $connection, false );
	}

	private function hashSecret( string $secret ) :string {
		return \hash( 'sha256', $secret );
	}

	private function generateSecret() :string {
		return \bin2hex( \random_bytes( 32 ) );
	}

	private function restBaseEndpoint() :string {
		return \rtrim( \rest_url( 'shield/v1/shield-central' ), '/' );
	}
}
