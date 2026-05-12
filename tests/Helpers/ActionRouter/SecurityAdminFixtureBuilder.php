<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	ModuleOptionsSave,
	SecurityAdminAuthClear,
	SecurityAdminCheck,
	SecurityAdminLogin,
	SecurityAdminRemove,
	SecurityAdminRequestRemoveByEmail
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\FormSecurityAdminLoginBox;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	PageConfigureLanding,
	PageDashboardOverview,
	PageSecurityAdminRestricted
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\SecadminEnabled;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone\Secadmin;
use FernleafSystems\Wordpress\Services\Services;
use WP_User;

/**
 * @phpstan-import-type RawOptionStoreState from RawOptionStoreSnapshot
 * @phpstan-type BrowserSessionSnapshot array{
 *   available:bool,
 *   user_id:int,
 *   username:string,
 *   token:string,
 *   session_existed:bool,
 *   session:array<string,mixed>|null,
 *   cookie_name:string,
 *   created_by_fixture:bool
 * }
 * @phpstan-type FixtureState array{
 *   scenario:string,
 *   option_store_snapshot:array<string,RawOptionStoreState>,
 *   selected_options_snapshot:array<string,mixed>,
 *   force_restrictions_option_present:bool,
 *   force_restrictions_option_value:mixed,
 *   session_snapshot:BrowserSessionSnapshot,
 *   this_req_is_security_admin:bool
 * }
 */
class SecurityAdminFixtureBuilder {

	private const FORCE_RESTRICTIONS_OPTION = 'shield_browser_fixture_security_admin_force_restrictions';
	private const OPTION_MISSING_SENTINEL = '__shield_browser_fixture_missing__';

	public const SCENARIO_PIN_UNSET = 'pin-unset';
	public const SCENARIO_LOCKED = 'locked';
	public const SCENARIO_ACTIVE_SESSION = 'active-session';
	public const SCENARIO_EXPIRED_SESSION = 'expired-session';

	private const VALID_PIN = '123456';
	private const INVALID_PIN = '654321';
	private const NEW_PIN = '246810';
	private const TIMEOUT_MINUTES = 1;
	private const EXPIRED_SESSION_GRACE_SECONDS = 5;

	private const OPTION_KEYS = [
		'global_enable_plugin_features',
		'admin_access_key',
		'admin_access_timeout',
		'sec_admin_users',
		'admin_access_restrict_options',
		'allow_email_override',
	];

	/**
	 * @return array{contract:array<string,mixed>,state:FixtureState}
	 */
	public function seed( string $scenario ) :array {
		$scenario = $this->normalizeScenario( $scenario );
		$state = $this->newFixtureState( $scenario );

		try {
			$this->applyScenario( $scenario, $state );

			return [
				'contract' => \array_merge( $this->baseContract( $scenario ), [
					'original_options' => $state[ 'selected_options_snapshot' ],
					'current'          => $this->currentState(),
				] ),
				'state'    => $state,
			];
		}
		catch ( \Throwable $throwable ) {
			$this->cleanup( $state );
			throw $throwable;
		}
	}

	/**
	 * @param array<string,mixed> $state
	 * @return array<string,mixed>
	 */
	public function inspect( array $state = [] ) :array {
		$state = $this->normalizePersistedState( $state );
		if ( $state === $this->emptyFixtureState() ) {
			return [
				'fixture_state_present' => false,
				'scenarios'             => $this->scenarios(),
				'option_keys'           => self::OPTION_KEYS,
				'current'               => $this->currentState(),
			];
		}

		$base = $this->baseContract( $state[ 'scenario' ] );

		return \array_merge( $base, [
			'fixture_state_present' => $state !== $this->emptyFixtureState(),
			'current'               => $this->currentState(),
			'original_options'      => $state[ 'selected_options_snapshot' ],
		] );
	}

	/**
	 * @param array<string,mixed> $state
	 */
	public function cleanup( array $state ) :void {
		$state = $this->normalizePersistedState( $state );
		if ( $state === $this->emptyFixtureState() ) {
			return;
		}

		$this->restoreSession( $state[ 'session_snapshot' ] );
		if ( (bool)$state[ 'force_restrictions_option_present' ] ) {
			\update_option( self::FORCE_RESTRICTIONS_OPTION, $state[ 'force_restrictions_option_value' ], false );
		}
		else {
			\delete_option( self::FORCE_RESTRICTIONS_OPTION );
		}
		$this->rawOptionStores()->restore( $state[ 'option_store_snapshot' ], 'Security Admin fixture' );
		RuntimeTestState::controller()->this_req->is_security_admin = $state[ 'this_req_is_security_admin' ];
		unset( RuntimeTestState::controller()->this_req->session );
	}

	/**
	 * @return FixtureState
	 */
	private function newFixtureState( string $scenario ) :array {
		$forceRestrictionsOption = \get_option( self::FORCE_RESTRICTIONS_OPTION, self::OPTION_MISSING_SENTINEL );

		return [
			'scenario'                            => $scenario,
			'option_store_snapshot'               => $this->rawOptionStores()->snapshot(),
			'selected_options_snapshot'           => $this->currentOptions(),
			'force_restrictions_option_present'   => $forceRestrictionsOption !== self::OPTION_MISSING_SENTINEL,
			'force_restrictions_option_value'     => $forceRestrictionsOption !== self::OPTION_MISSING_SENTINEL
				? $forceRestrictionsOption
				: null,
			'session_snapshot'                    => $this->snapshotCurrentSession(),
			'this_req_is_security_admin'          => (bool)RuntimeTestState::controller()->this_req->is_security_admin,
		];
	}

	/**
	 * @return FixtureState
	 */
	private function emptyFixtureState() :array {
		return [
			'scenario'                            => '',
			'option_store_snapshot'               => [],
			'selected_options_snapshot'           => [],
			'force_restrictions_option_present'   => false,
			'force_restrictions_option_value'     => null,
			'session_snapshot'                    => $this->emptySessionSnapshot(),
			'this_req_is_security_admin'          => false,
		];
	}

	/**
	 * @param array<string,mixed> $state
	 * @return FixtureState
	 */
	private function normalizePersistedState( array $state ) :array {
		if ( $state === [] ) {
			return $this->emptyFixtureState();
		}

		if ( !\is_string( $state[ 'scenario' ] ?? null ) ) {
			throw new \RuntimeException( 'Security Admin fixture state is missing scenario metadata.' );
		}
		if ( !\is_array( $state[ 'option_store_snapshot' ] ?? null ) ) {
			throw new \RuntimeException( 'Security Admin fixture state is missing raw option store metadata.' );
		}
		if ( !\is_array( $state[ 'selected_options_snapshot' ] ?? null ) ) {
			throw new \RuntimeException( 'Security Admin fixture state is missing selected option metadata.' );
		}
		if ( !\is_array( $state[ 'session_snapshot' ] ?? null ) ) {
			throw new \RuntimeException( 'Security Admin fixture state is missing session metadata.' );
		}
		foreach ( [ 'force_restrictions_option_present', 'force_restrictions_option_value', 'this_req_is_security_admin' ] as $requiredKey ) {
			if ( !\array_key_exists( $requiredKey, $state ) ) {
				throw new \RuntimeException( 'Security Admin fixture state is missing restoration metadata.' );
			}
		}

		return [
			'scenario'                            => $this->normalizeScenario( $state[ 'scenario' ] ),
			'option_store_snapshot'               => $this->rawOptionStores()->normalize(
				$state[ 'option_store_snapshot' ],
				'Security Admin fixture'
			),
			'selected_options_snapshot'           => $this->normalizeSelectedOptions(
				$state[ 'selected_options_snapshot' ]
			),
			'force_restrictions_option_present'   => (bool)$state[ 'force_restrictions_option_present' ],
			'force_restrictions_option_value'     => $state[ 'force_restrictions_option_value' ],
			'session_snapshot'                    => $this->normalizeSessionSnapshot( $state[ 'session_snapshot' ] ),
			'this_req_is_security_admin'          => (bool)$state[ 'this_req_is_security_admin' ],
		];
	}

	/**
	 * @param array<string,mixed> $snapshot
	 * @return array<string,mixed>
	 */
	private function normalizeSelectedOptions( array $snapshot ) :array {
		$normalized = [];
		foreach ( self::OPTION_KEYS as $key ) {
			if ( !\array_key_exists( $key, $snapshot ) ) {
				throw new \RuntimeException( 'Security Admin fixture state is missing selected option metadata.' );
			}
			$normalized[ $key ] = $snapshot[ $key ];
		}
		return $normalized;
	}

	private function normalizeScenario( string $scenario ) :string {
		$scenario = \trim( $scenario );
		if ( !\in_array( $scenario, $this->scenarios(), true ) ) {
			throw new \RuntimeException( 'Unknown Security Admin fixture scenario: '.$scenario );
		}

		return $scenario;
	}

	/**
	 * @return list<string>
	 */
	private function scenarios() :array {
		return [
			self::SCENARIO_PIN_UNSET,
			self::SCENARIO_LOCKED,
			self::SCENARIO_ACTIVE_SESSION,
			self::SCENARIO_EXPIRED_SESSION,
		];
	}

	/**
	 * @phpstan-param FixtureState $state
	 */
	private function applyScenario( string $scenario, array $state ) :void {
		\update_option( self::FORCE_RESTRICTIONS_OPTION, 'Y', false );

		$pinHash = $scenario === self::SCENARIO_PIN_UNSET
			? ''
			: \wp_hash_password( self::VALID_PIN );
		$updates = [
			'global_enable_plugin_features' => 'Y',
			'admin_access_key'              => $pinHash,
			'admin_access_timeout'          => self::TIMEOUT_MINUTES,
			'sec_admin_users'               => [],
			'admin_access_restrict_options' => 'Y',
			'allow_email_override'          => 'N',
		];
		$opts = RuntimeTestState::controller()->opts;
		foreach ( $updates as $key => $value ) {
			$opts->optSet( $key, $value );
		}
		$opts->store();
		RuntimeTestState::forcePersistOptions( $updates );
		RuntimeTestState::resetOptionsRuntimeCache();

		$timestamp = 0;
		if ( $scenario === self::SCENARIO_ACTIVE_SESSION ) {
			$timestamp = Services::Request()->ts();
		}
		elseif ( $scenario === self::SCENARIO_EXPIRED_SESSION ) {
			$timestamp = Services::Request()->ts() - $this->timeoutSeconds() - self::EXPIRED_SESSION_GRACE_SECONDS;
		}

		$this->setSessionSecAdminAt( $state[ 'session_snapshot' ], $timestamp );
		RuntimeTestState::controller()->this_req->is_security_admin = $scenario === self::SCENARIO_ACTIVE_SESSION;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function baseContract( string $scenario ) :array {
		return [
			'scenario'                   => $scenario,
			'scenarios'                  => $this->scenarios(),
			'routes'                     => [
				'dashboard'  => [
					PluginNavs::FIELD_NAV    => PluginNavs::NAV_DASHBOARD,
					PluginNavs::FIELD_SUBNAV => PluginNavs::SUBNAV_DASHBOARD_OVERVIEW,
				],
				'configure'  => [
					PluginNavs::FIELD_NAV    => PluginNavs::NAV_ZONES,
					PluginNavs::FIELD_SUBNAV => PluginNavs::SUBNAV_ZONES_OVERVIEW,
				],
				'protected'  => [
					PluginNavs::FIELD_NAV    => PluginNavs::NAV_DASHBOARD,
					PluginNavs::FIELD_SUBNAV => PluginNavs::SUBNAV_DASHBOARD_OVERVIEW,
				],
			],
			'configure_focus'            => [
				'zone_key'    => Secadmin::Slug(),
				'row_key'     => SecadminEnabled::Slug(),
				'config_item' => 'admin_access_key',
			],
			'action_slugs'               => [
				'module_options_save' => ModuleOptionsSave::SLUG,
				'sec_admin_login'     => SecurityAdminLogin::SLUG,
				'sec_admin_check'     => SecurityAdminCheck::SLUG,
				'sec_admin_auth_clear' => SecurityAdminAuthClear::SLUG,
			],
			'render_slugs'               => [
				'dashboard_page'    => PageDashboardOverview::SLUG,
				'configure_page'    => PageConfigureLanding::SLUG,
				'restricted_page'   => PageSecurityAdminRestricted::SLUG,
				'login_box'         => FormSecurityAdminLoginBox::SLUG,
			],
			'out_of_scope_action_slugs'  => [
				'secadmin_remove_confirm' => SecurityAdminRemove::SLUG,
				'req_email_remove'        => SecurityAdminRequestRemoveByEmail::SLUG,
			],
			'option_keys'                => self::OPTION_KEYS,
			'options'                    => [
				'admin_access_key' => [
					'key'                => 'admin_access_key',
					'type'               => 'password',
					'control_id'         => 'Opt-admin_access_key',
					'confirm_control_id' => 'Opt-admin_access_key_confirm',
				],
			],
			'pins'                       => [
				'valid'   => self::VALID_PIN,
				'invalid' => self::INVALID_PIN,
				'new'     => self::NEW_PIN,
			],
			'timeout'                    => [
				'minutes'                  => self::TIMEOUT_MINUTES,
				'seconds'                  => $this->timeoutSeconds(),
				'expired_grace_seconds'    => self::EXPIRED_SESSION_GRACE_SECONDS,
			],
			'selectors'                  => [
				'overlay'             => '#SecurityAdminOverlay',
				'overlay_form'        => '#SecurityAdminForm',
				'overlay_pin_input'   => '#sec_admin_key',
				'login_box_pin_input' => '#SecAdminPinInput',
				'pin_option'          => '#Opt-admin_access_key',
				'pin_option_confirm'  => '#Opt-admin_access_key_confirm',
				'configure_landing'   => '[data-configure-landing="1"]',
				'configure_row'       => '[data-configure-row-key="'.SecadminEnabled::Slug().'"]',
				'options_form'        => 'form.options_form_for',
				'page_action_menu'    => '.page-action-menu-toggle',
				'end_session_action'  => 'a[href*="'.SecurityAdminAuthClear::SLUG.'"]',
			],
			'expected'                   => [
				'enabled'                 => $scenario !== self::SCENARIO_PIN_UNSET,
				'session_active'          => $scenario === self::SCENARIO_ACTIVE_SESSION,
				'time_remaining_is_zero'  => \in_array( $scenario, [ self::SCENARIO_PIN_UNSET, self::SCENARIO_LOCKED, self::SCENARIO_EXPIRED_SESSION ], true ),
				'pin_hash_format'         => $scenario === self::SCENARIO_PIN_UNSET ? 'empty' : 'wp_hash',
			],
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function currentState() :array {
		unset( RuntimeTestState::controller()->this_req->session );
		$session = $this->resolveCurrentSessionContext();
		$secAdminAt = \is_array( $session ) ? $this->sessionSecAdminAt( $session ) : 0;
		$timeRemaining = $this->timeRemainingForSecAdminAt( $secAdminAt );
		$options = $this->currentOptions();

		return [
			'enabled'                        => RuntimeTestState::controller()->comps->sec_admin->isEnabledSecAdmin(),
			'session_active'                 => $timeRemaining > 0,
			'this_req_is_security_admin'     => (bool)RuntimeTestState::controller()->this_req->is_security_admin,
			'secadmin_at'                    => $secAdminAt,
			'time_remaining'                 => $timeRemaining,
			'timeout_seconds'                => RuntimeTestState::controller()->comps->sec_admin->getSecAdminTimeout(),
			'admin_access_key_present'       => (string)$options[ 'admin_access_key' ] !== '',
			'admin_access_key_hash_format'   => $this->classifyPinHash( (string)$options[ 'admin_access_key' ] ),
			'selected_options'               => $options,
			'force_restrictions_option'      => [
				'present' => \get_option( self::FORCE_RESTRICTIONS_OPTION, self::OPTION_MISSING_SENTINEL ) !== self::OPTION_MISSING_SENTINEL,
				'value'   => \get_option( self::FORCE_RESTRICTIONS_OPTION, self::OPTION_MISSING_SENTINEL ),
			],
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function currentOptions() :array {
		$values = [];
		$opts = RuntimeTestState::controller()->opts;
		foreach ( self::OPTION_KEYS as $key ) {
			$values[ $key ] = $opts->optGet( $key );
		}
		return $values;
	}

	private function classifyPinHash( string $hash ) :string {
		if ( $hash === '' ) {
			return 'empty';
		}
		if ( \preg_match( '/^[a-f0-9]{32}$/', $hash ) === 1 ) {
			return 'md5';
		}
		if ( \wp_check_password( self::VALID_PIN, $hash ) || \wp_check_password( self::NEW_PIN, $hash ) ) {
			return 'wp_hash';
		}
		return 'unknown';
	}

	private function timeoutSeconds() :int {
		return self::TIMEOUT_MINUTES*\MINUTE_IN_SECONDS;
	}

	private function rawOptionStores() :RawOptionStoreSnapshot {
		return new RawOptionStoreSnapshot();
	}

	/**
	 * @return BrowserSessionSnapshot
	 */
	private function snapshotCurrentSession() :array {
		$context = $this->resolveCurrentSessionContext();
		$createdByFixture = false;
		if ( !\is_array( $context ) ) {
			$context = $this->createCurrentUserSessionContext();
			$createdByFixture = true;
		}

		return [
			'available'          => true,
			'user_id'            => (int)$context[ 'user_id' ],
			'username'           => (string)$context[ 'username' ],
			'token'              => (string)$context[ 'token' ],
			'session_existed'    => !$createdByFixture,
			'session'            => $createdByFixture ? null : $context[ 'session' ],
			'cookie_name'        => (string)$context[ 'cookie_name' ],
			'created_by_fixture' => $createdByFixture,
		];
	}

	/**
	 * @return BrowserSessionSnapshot
	 */
	private function emptySessionSnapshot() :array {
		return [
			'available'          => false,
			'user_id'            => 0,
			'username'           => '',
			'token'              => '',
			'session_existed'    => false,
			'session'            => null,
			'cookie_name'        => '',
			'created_by_fixture' => false,
		];
	}

	/**
	 * @param array<string,mixed> $snapshot
	 * @return BrowserSessionSnapshot
	 */
	private function normalizeSessionSnapshot( array $snapshot ) :array {
		foreach ( [
			'available',
			'user_id',
			'username',
			'token',
			'session_existed',
			'session',
			'cookie_name',
			'created_by_fixture',
		] as $requiredKey ) {
			if ( !\array_key_exists( $requiredKey, $snapshot ) ) {
				throw new \RuntimeException( 'Security Admin fixture state is missing session metadata.' );
			}
		}

		$normalized = [
			'available'          => (bool)$snapshot[ 'available' ],
			'user_id'            => (int)$snapshot[ 'user_id' ],
			'username'           => (string)$snapshot[ 'username' ],
			'token'              => (string)$snapshot[ 'token' ],
			'session_existed'    => (bool)$snapshot[ 'session_existed' ],
			'session'            => \is_array( $snapshot[ 'session' ] ) ? $snapshot[ 'session' ] : null,
			'cookie_name'        => (string)$snapshot[ 'cookie_name' ],
			'created_by_fixture' => (bool)$snapshot[ 'created_by_fixture' ],
		];
		if ( $normalized[ 'available' ] && ( $normalized[ 'user_id' ] <= 0 || $normalized[ 'token' ] === '' ) ) {
			throw new \RuntimeException( 'Security Admin fixture state is missing session metadata.' );
		}
		if ( $normalized[ 'session_existed' ] && !\is_array( $normalized[ 'session' ] ) ) {
			throw new \RuntimeException( 'Security Admin fixture state is missing raw session metadata.' );
		}

		return $normalized;
	}

	/**
	 * @phpstan-param BrowserSessionSnapshot $snapshot
	 */
	private function setSessionSecAdminAt( array $snapshot, int $timestamp ) :void {
		if ( !$snapshot[ 'available' ] ) {
			throw new \RuntimeException( 'Security Admin fixture requires a current browser session.' );
		}

		$manager = \WP_Session_Tokens::get_instance( $snapshot[ 'user_id' ] );
		$session = $manager->get( $snapshot[ 'token' ] );
		if ( !\is_array( $session ) ) {
			throw new \RuntimeException( 'Security Admin fixture could not load the current browser session.' );
		}

		$shield = \is_array( $session[ 'shield' ] ?? null ) ? $session[ 'shield' ] : [];
		$shield[ 'user_id' ] = $snapshot[ 'user_id' ];
		$shield[ 'secadmin_at' ] = $timestamp;
		$session[ 'shield' ] = $shield;
		$manager->update( $snapshot[ 'token' ], $session );
		unset( RuntimeTestState::controller()->this_req->session );
	}

	/**
	 * @phpstan-param BrowserSessionSnapshot $snapshot
	 */
	private function restoreSession( array $snapshot ) :void {
		if ( !$snapshot[ 'available' ] ) {
			return;
		}

		$manager = \WP_Session_Tokens::get_instance( $snapshot[ 'user_id' ] );
		if ( $snapshot[ 'session_existed' ] ) {
			$manager->update( $snapshot[ 'token' ], $snapshot[ 'session' ] );
		}
		else {
			$manager->destroy( $snapshot[ 'token' ] );
		}

		if ( $snapshot[ 'created_by_fixture' ] && $snapshot[ 'cookie_name' ] !== '' ) {
			unset( $_COOKIE[ $snapshot[ 'cookie_name' ] ] );
		}
		unset( RuntimeTestState::controller()->this_req->session );
	}

	/**
	 * @return array{user_id:int,username:string,token:string,session:array<string,mixed>,cookie_name:string}|null
	 */
	private function resolveCurrentSessionContext() :?array {
		foreach ( [
			'logged_in'   => \defined( 'LOGGED_IN_COOKIE' ) ? \LOGGED_IN_COOKIE : '',
			'secure_auth' => \defined( 'SECURE_AUTH_COOKIE' ) ? \SECURE_AUTH_COOKIE : '',
			'auth'        => \defined( 'AUTH_COOKIE' ) ? \AUTH_COOKIE : '',
		] as $type => $cookieName ) {
			$parsed = \wp_parse_auth_cookie( '', $type );
			if ( !\is_array( $parsed ) || empty( $parsed[ 'username' ] ) || empty( $parsed[ 'token' ] ) ) {
				continue;
			}

			$user = \get_user_by( 'login', (string)$parsed[ 'username' ] );
			if ( !$user instanceof WP_User ) {
				continue;
			}

			$session = \WP_Session_Tokens::get_instance( (int)$user->ID )->get( (string)$parsed[ 'token' ] );
			if ( !\is_array( $session ) ) {
				continue;
			}

			return [
				'user_id'      => (int)$user->ID,
				'username'     => (string)$user->user_login,
				'token'        => (string)$parsed[ 'token' ],
				'session'      => $session,
				'cookie_name'  => (string)$cookieName,
			];
		}

		return null;
	}

	/**
	 * @return array{user_id:int,username:string,token:string,session:array<string,mixed>,cookie_name:string}
	 */
	private function createCurrentUserSessionContext() :array {
		$user = \wp_get_current_user();
		if ( !$user instanceof WP_User || (int)$user->ID <= 0 ) {
			throw new \RuntimeException( 'Security Admin fixture requires an authenticated WordPress user.' );
		}

		$expiration = Services::Request()->ts() + \DAY_IN_SECONDS;
		$token = \WP_Session_Tokens::get_instance( (int)$user->ID )->create( $expiration );
		$cookieName = \defined( 'LOGGED_IN_COOKIE' ) ? \LOGGED_IN_COOKIE : '';
		if ( $cookieName !== '' ) {
			$_COOKIE[ $cookieName ] = \wp_generate_auth_cookie( (int)$user->ID, $expiration, 'logged_in', $token );
		}

		RuntimeTestState::controller()->this_req->ip = RuntimeTestState::controller()->this_req->ip ?? '198.51.100.77';
		RuntimeTestState::controller()->this_req->host = RuntimeTestState::controller()->this_req->host ?? 'example.org';
		RuntimeTestState::controller()->this_req->useragent = RuntimeTestState::controller()->this_req->useragent ?? 'Shield Security Admin Fixture';
		$session = RuntimeTestState::controller()->comps->session->buildSession( (int)$user->ID, $token )->getRawData();

		return [
			'user_id'      => (int)$user->ID,
			'username'     => (string)$user->user_login,
			'token'        => $token,
			'session'      => $session,
			'cookie_name'  => (string)$cookieName,
		];
	}

	/**
	 * @param array{session:array<string,mixed>} $context
	 */
	private function sessionSecAdminAt( array $context ) :int {
		$session = $context[ 'session' ];
		$shield = \is_array( $session[ 'shield' ] ?? null ) ? $session[ 'shield' ] : [];
		return (int)( $shield[ 'secadmin_at' ] ?? 0 );
	}

	private function timeRemainingForSecAdminAt( int $secAdminAt ) :int {
		return $secAdminAt > 0
			? (int)\max( 0, RuntimeTestState::controller()->comps->sec_admin->getSecAdminTimeout() - ( Services::Request()->ts() - $secAdminAt ) )
			: 0;
	}
}
