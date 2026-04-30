<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Sessions;

class SessionActivityPersistencePolicy {

	public const ACTIVITY_PERSIST_INTERVAL = 60;

	private const STABLE_SESSION_FIELDS = [
		'ip',
		'ua',
		'login',
		'expiration',
	];

	private const STABLE_SHIELD_FIELDS = [
		'user_id',
		'expires_at',
		'host',
		'unique',
		'useragent',
		'ip',
		'session_started_at',
		'token_started_at',
	];

	public function shouldPersist( array $storedSession, array $candidateSession, int $now ) :bool {
		$storedShield = \is_array( $storedSession[ 'shield' ] ?? null ) ? $storedSession[ 'shield' ] : [];
		$candidateShield = \is_array( $candidateSession[ 'shield' ] ?? null ) ? $candidateSession[ 'shield' ] : [];

		if ( empty( $storedShield ) ) {
			return true;
		}

		foreach ( self::STABLE_SESSION_FIELDS as $field ) {
			if ( ( $storedSession[ $field ] ?? null ) !== ( $candidateSession[ $field ] ?? null ) ) {
				return true;
			}
		}

		foreach ( self::STABLE_SHIELD_FIELDS as $field ) {
			if ( ( $storedShield[ $field ] ?? null ) !== ( $candidateShield[ $field ] ?? null ) ) {
				return true;
			}
		}

		$storedActivityAt = (int)( $storedShield[ 'last_activity_at' ] ?? 0 );
		return $storedActivityAt <= 0 || $now - $storedActivityAt >= self::ACTIVITY_PERSIST_INTERVAL;
	}
}
