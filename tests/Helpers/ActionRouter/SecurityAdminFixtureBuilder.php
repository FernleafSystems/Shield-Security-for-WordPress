<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;
use FernleafSystems\Wordpress\Services\Services;
use WP_User;

/**
 * @phpstan-type FixtureState array{
 *   options_snapshot:array<string,mixed>,
 *   force_restrictions_option_present:bool,
 *   force_restrictions_option_value:mixed
 * }
 */
class SecurityAdminFixtureBuilder {

	private const FORCE_RESTRICTIONS_OPTION = 'shield_browser_fixture_security_admin_force_restrictions';
	private const OPTION_MISSING_SENTINEL = '__shield_browser_fixture_missing__';

	private const OPTION_KEYS = [
		'global_enable_plugin_features',
		'admin_access_key',
		'admin_access_timeout',
		'sec_admin_users',
	];

	/**
	 * @return array{contract:array<string,mixed>,state:FixtureState}
	 */
	public function seed() :array {
		$forceRestrictionsOption = \get_option( self::FORCE_RESTRICTIONS_OPTION, self::OPTION_MISSING_SENTINEL );
		$state = [
			'options_snapshot'                    => RuntimeTestState::snapshotOptions( self::OPTION_KEYS ),
			'force_restrictions_option_present'   => $forceRestrictionsOption !== self::OPTION_MISSING_SENTINEL,
			'force_restrictions_option_value'     => $forceRestrictionsOption !== self::OPTION_MISSING_SENTINEL
				? $forceRestrictionsOption
				: null,
		];

		try {
			\update_option( self::FORCE_RESTRICTIONS_OPTION, 'Y', false );
			$pinHash = \wp_hash_password( '123456' );
			RuntimeTestState::controller()->opts
				->optSet( 'global_enable_plugin_features', 'Y' )
				->optSet( 'admin_access_key', $pinHash )
				->optSet( 'admin_access_timeout', 30 )
				->optSet( 'sec_admin_users', [] )
				->store();
			RuntimeTestState::forcePersistOptions( [
				'global_enable_plugin_features'    => 'Y',
				'admin_access_key'                => $pinHash,
				'admin_access_timeout'            => 30,
				'sec_admin_users'                 => [],
			] );

			if ( !$this->setCurrentBrowserSessionSecurityAdmin( true ) ) {
				throw new \RuntimeException( 'Unable to activate Security Admin for the browser session.' );
			}

			return [
				'contract' => [
					'enabled'        => true,
					'session_active' => true,
				],
				'state'    => $state,
			];
		}
		catch ( \Throwable $throwable ) {
			$this->cleanup( $state );
			throw $throwable;
		}
	}

	/**
	 * @phpstan-param FixtureState $state
	 */
	public function cleanup( array $state ) :void {
		$this->setCurrentBrowserSessionSecurityAdmin( false );
		if ( (bool)( $state[ 'force_restrictions_option_present' ] ?? false ) ) {
			\update_option( self::FORCE_RESTRICTIONS_OPTION, $state[ 'force_restrictions_option_value' ] ?? '', false );
		}
		else {
			\delete_option( self::FORCE_RESTRICTIONS_OPTION );
		}
		RuntimeTestState::restoreOptions(
			\is_array( $state[ 'options_snapshot' ] ?? null ) ? $state[ 'options_snapshot' ] : []
		);
		RuntimeTestState::controller()->this_req->is_security_admin = false;
	}

	private function setCurrentBrowserSessionSecurityAdmin( bool $enabled ) :bool {
		$parsed = null;
		foreach ( [ 'logged_in', 'secure_auth', 'auth' ] as $type ) {
			$parsed = \wp_parse_auth_cookie( '', $type );
			if ( \is_array( $parsed ) && !empty( $parsed[ 'username' ] ) && !empty( $parsed[ 'token' ] ) ) {
				break;
			}
		}

		if ( !\is_array( $parsed ) || empty( $parsed[ 'username' ] ) || empty( $parsed[ 'token' ] ) ) {
			return false;
		}

		$user = \get_user_by( 'login', (string)$parsed[ 'username' ] );
		if ( !$user instanceof WP_User ) {
			return false;
		}

		$manager = \WP_Session_Tokens::get_instance( (int)$user->ID );
		$session = $manager->get( (string)$parsed[ 'token' ] );
		if ( !\is_array( $session ) ) {
			return false;
		}

		$shield = \is_array( $session[ 'shield' ] ?? null ) ? $session[ 'shield' ] : [];
		$shield[ 'user_id' ] = (int)$user->ID;
		$shield[ 'secadmin_at' ] = $enabled ? Services::Request()->ts() : 0;
		$session[ 'shield' ] = $shield;
		$manager->update( (string)$parsed[ 'token' ], $session );
		RuntimeTestState::controller()->this_req->is_security_admin = $enabled;

		return true;
	}
}
