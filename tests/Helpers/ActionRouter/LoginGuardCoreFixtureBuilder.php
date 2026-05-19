<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use Dolondro\GoogleAuthenticator\GoogleAuthenticator as OtpGenerator;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\{
	BackupCodes,
	Email,
	GoogleAuth
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties\MfaRecordsHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\{
	RuntimeTestState,
	TestDataFactory
};
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-type FixtureState array{
 *   scenario:string,
 *   options_snapshot:array<string,mixed>,
 *   force_restrictions_option_present:bool,
 *   force_restrictions_option_value:mixed,
 *   permalink_snapshot:string,
 *   created_user_ids:list<int>,
 *   mfa_record_ids:list<int>,
 *   user_id:int,
 *   user_login:string,
 *   user_pass:string,
 *   ga_secret:string,
 *   backup_code:string
 * }
 */
class LoginGuardCoreFixtureBuilder {

	public const RUNTIME_OPTION = 'shield_browser_fixture_login_guard_core_runtime';
	private const FORCE_RESTRICTIONS_OPTION = 'shield_browser_fixture_force_restrictions';
	private const OPTION_MISSING_SENTINEL = '__shield_browser_fixture_missing__';

	private const OPTION_KEYS = [
		'allow_backupcodes',
		'bot_protection_locations',
		'email_any_user_set',
		'email_can_send_verified_at',
		'enable_email_auto_login',
		'enable_email_authentication',
		'enable_google_authenticator',
		'global_enable_plugin_features',
		'license_activated_at',
		'license_data',
		'license_deactivated_at',
		'mfa_skip',
		'mfa_verify_page',
		'rename_wplogin_path',
		'rename_wplogin_redirect',
		'suresend_emails',
		'two_factor_auth_user_roles',
	];

	/**
	 * @return array{contract:array<string,mixed>,state:FixtureState}
	 */
	public function seed( string $scenario ) :array {
		RuntimeTestState::ensureDb( [ 'mfa' ] );
		$forceRestrictionsOption = \get_option( self::FORCE_RESTRICTIONS_OPTION, self::OPTION_MISSING_SENTINEL );
		$state = [
			'scenario'                           => $scenario,
			'options_snapshot'                   => RuntimeTestState::snapshotOptions( self::OPTION_KEYS ),
			'force_restrictions_option_present'  => $forceRestrictionsOption !== self::OPTION_MISSING_SENTINEL,
			'force_restrictions_option_value'    => $forceRestrictionsOption !== self::OPTION_MISSING_SENTINEL
				? $forceRestrictionsOption
				: null,
			'permalink_snapshot'                 => (string)\get_option( 'permalink_structure' ),
			'created_user_ids'                   => [],
			'mfa_record_ids'                     => [],
			'user_id'                            => 0,
			'user_login'                         => '',
			'user_pass'                          => '',
			'ga_secret'                          => '',
			'backup_code'                        => '',
		];

		try {
			\update_option( 'permalink_structure', '/%postname%/' );
			\update_option( self::FORCE_RESTRICTIONS_OPTION, 'Y', false );
			$this->setRuntimeState( [
				'capture_events' => true,
				'capture_mail'   => \in_array(
					$scenario,
					[ 'email-auth-login', 'email-plus-ga-login', 'email-plus-backup-login' ],
					true
				),
				'events'         => [],
				'mails'          => [],
			] );
			RuntimeTestState::controller()->opts
				->optSet( 'bot_protection_locations', [] )
				->optSet( 'global_enable_plugin_features', 'Y' )
				->store();
			RuntimeTestState::forcePersistOptions( [
				'bot_protection_locations'      => [],
				'global_enable_plugin_features' => 'Y',
			] );

			switch ( $scenario ) {
				case 'hide-login':
					$contract = $this->seedHideLogin();
					break;

				case 'hide-login-disabled':
					$contract = $this->seedHideLoginDisabled();
					break;

				case 'remember-me':
					$contract = $this->seedRememberMe( $state );
					break;

				case 'email-auth-login':
					$contract = $this->seedEmailAuthLogin( $state );
					break;

				case 'email-plus-ga-login':
					$contract = $this->seedEmailPlusGaLogin( $state );
					break;

				case 'email-plus-backup-login':
					$contract = $this->seedEmailPlusBackupLogin( $state );
					break;

				default:
					throw new \RuntimeException( 'Unknown login guard core fixture scenario: '.$scenario );
			}

			return [
				'contract' => $contract,
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
	 * @return array<string,mixed>
	 */
	public function inspect( array $state ) :array {
		RuntimeTestState::ensureDb( [ 'mfa' ] );

		$userID = (int)( $state[ 'user_id' ] ?? 0 );
		$user = $userID > 0 ? Services::WpUsers()->getUserById( $userID ) : null;
		$meta = $user instanceof \WP_User ? RuntimeTestState::controller()->user_metas->for( $user ) : null;
		$runtime = $this->runtimeState();
		$emailProvider = $user instanceof \WP_User ? new Email( $user ) : null;
		$activeProviders = $user instanceof \WP_User
			? RuntimeTestState::controller()->comps->mfa->getProvidersActiveForUser( $user )
			: [];

		return [
			'scenario'              => (string)( $state[ 'scenario' ] ?? '' ),
			'user_id'               => $userID,
			'option_state'          => [
				'rename_wplogin_path'           => RuntimeTestState::controller()->opts->optGet( 'rename_wplogin_path' ),
				'global_enable_plugin_features' => RuntimeTestState::controller()->opts->optGet( 'global_enable_plugin_features' ),
				'bot_protection_locations'      => RuntimeTestState::controller()->opts->optGet( 'bot_protection_locations' ),
				'mfa_skip'                      => RuntimeTestState::controller()->opts->optGet( 'mfa_skip' ),
				'enable_email_authentication'   => RuntimeTestState::controller()->opts->optGet( 'enable_email_authentication' ),
				'allow_backupcodes'             => RuntimeTestState::controller()->opts->optGet( 'allow_backupcodes' ),
			],
			'login_intents_count'   => $meta === null ? 0 : \count( \is_array( $meta->login_intents ) ? $meta->login_intents : [] ),
			'hash_loginmfa_count'   => $meta === null ? 0 : \count( \is_array( $meta->hash_loginmfa ) ? $meta->hash_loginmfa : [] ),
			'mfa_record_counts'     => $userID > 0 ? $this->recordCountsForUser( $userID ) : [],
			'active_provider_slugs' => \array_keys( $activeProviders ),
			'subject_to_mfa_login'  => $user instanceof \WP_User
				&& RuntimeTestState::controller()->comps->mfa->isSubjectToLoginIntent( $user ),
			'current_otp'           => $this->currentGoogleOtp( (string)( $state[ 'ga_secret' ] ?? '' ) ),
			'events'                => \is_array( $runtime[ 'events' ] ?? null ) ? $runtime[ 'events' ] : [],
			'event_counts'          => $this->eventCounts( \is_array( $runtime[ 'events' ] ?? null ) ? $runtime[ 'events' ] : [] ),
			'mail_count'            => \count( \is_array( $runtime[ 'mails' ] ?? null ) ? $runtime[ 'mails' ] : [] ),
			'mail_recipients'       => $this->mailRecipients( \is_array( $runtime[ 'mails' ] ?? null ) ? $runtime[ 'mails' ] : [] ),
			'latest_email_query'    => $this->latestEmailQuery( \is_array( $runtime[ 'mails' ] ?? null ) ? $runtime[ 'mails' ] : [] ),
			'email_otp_field_name'  => $emailProvider instanceof Email ? $emailProvider->getLoginIntentFormParameter() : '',
		];
	}

	/**
	 * @phpstan-param FixtureState $state
	 */
	public function cleanup( array $state ) :void {
		RuntimeTestState::ensureDb( [ 'mfa' ] );
		if ( $state === [] ) {
			\delete_option( self::RUNTIME_OPTION );
			return;
		}

		$con = RuntimeTestState::controller();

		foreach ( \is_array( $state[ 'mfa_record_ids' ] ?? null ) ? $state[ 'mfa_record_ids' ] : [] as $recordID ) {
			$recordID = (int)$recordID;
			if ( $recordID <= 0 ) {
				continue;
			}
			$record = $con->db_con->mfa->getQuerySelector()->byId( $recordID );
			if ( $record ) {
				$con->db_con->mfa->getQueryDeleter()->deleteRecord( $record );
			}
		}

		foreach ( \is_array( $state[ 'created_user_ids' ] ?? null ) ? $state[ 'created_user_ids' ] : [] as $userID ) {
			$userID = (int)$userID;
			if ( $userID <= 0 ) {
				continue;
			}
			$user = Services::WpUsers()->getUserById( $userID );
			if ( $user instanceof \WP_User ) {
				$meta = $con->user_metas->for( $user );
				$meta->login_intents = [];
				$meta->hash_loginmfa = [];
				( new MfaRecordsHandler() )->clearForUser( $user );
			}
			if ( !\function_exists( 'wp_delete_user' ) ) {
				require_once ABSPATH.'wp-admin/includes/user.php';
			}
			\wp_delete_user( $userID );
		}

		RuntimeTestState::restoreOptions(
			\is_array( $state[ 'options_snapshot' ] ?? null ) ? $state[ 'options_snapshot' ] : []
		);
		\update_option( 'permalink_structure', (string)( $state[ 'permalink_snapshot' ] ?? '' ) );
		if ( (bool)( $state[ 'force_restrictions_option_present' ] ?? false ) ) {
			\update_option( self::FORCE_RESTRICTIONS_OPTION, $state[ 'force_restrictions_option_value' ] ?? '', false );
		}
		else {
			\delete_option( self::FORCE_RESTRICTIONS_OPTION );
		}
		\delete_option( self::RUNTIME_OPTION );
		\wp_set_current_user( 0 );
		$con->this_req->is_security_admin = false;
		RuntimeTestState::resetMfaProviderCache();
	}

	/**
	 * @return array<string,mixed>
	 */
	private function seedHideLogin() :array {
		RuntimeTestState::controller()->opts
			->optSet( 'rename_wplogin_path', 'shield-browser-login' )
			->optSet( 'rename_wplogin_redirect', '' )
			->store();
		RuntimeTestState::forcePersistOptions( [
			'rename_wplogin_path'     => 'shield-browser-login',
			'rename_wplogin_redirect' => '',
		] );
		RuntimeTestState::resetOptionsRuntimeCache();

		return [
			'custom_login_path' => '/shield-browser-login',
			'old_login_path'    => '/wp-login.php',
			'admin_path'        => '/wp-admin/',
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function seedHideLoginDisabled() :array {
		RuntimeTestState::controller()->opts
			->optSet( 'rename_wplogin_path', '' )
			->optSet( 'rename_wplogin_redirect', '' )
			->store();
		RuntimeTestState::forcePersistOptions( [
			'rename_wplogin_path'     => '',
			'rename_wplogin_redirect' => '',
		] );
		RuntimeTestState::resetOptionsRuntimeCache();

		return [
			'old_login_path' => '/wp-login.php',
			'admin_path'     => '/wp-admin/',
		];
	}

	/**
	 * @phpstan-param FixtureState $state
	 * @return array<string,mixed>
	 */
	private function seedRememberMe( array &$state ) :array {
		$user = $this->createFixtureUser( 'remember' );
		$state[ 'user_id' ] = $user->ID;
		$state[ 'created_user_ids' ][] = $user->ID;
		$state[ 'user_login' ] = $user->user_login;
		$state[ 'user_pass' ] = 'shield-browser-pass';
		$state[ 'ga_secret' ] = 'JBSWY3DPEHPK3PXP';

		RuntimeTestState::applyPremiumCapabilities( [
			'2fa_remember_me',
		] );
		RuntimeTestState::controller()->opts
			->optSet( 'enable_google_authenticator', 'Y' )
			->optSet( 'enable_email_authentication', 'N' )
			->optSet( 'allow_backupcodes', 'N' )
			->optSet( 'mfa_skip', 1 )
			->optSet( 'mfa_verify_page', 'custom_shield' )
			->store();
		RuntimeTestState::forcePersistOptions( [
			'enable_google_authenticator' => 'Y',
			'enable_email_authentication' => 'N',
			'allow_backupcodes'           => 'N',
			'mfa_skip'                    => 1,
			'mfa_verify_page'             => 'custom_shield',
		] );
		$state[ 'mfa_record_ids' ][] = TestDataFactory::insertMfaRecord( $user->ID, GoogleAuth::ProviderSlug(), [], [
			'label'     => 'Browser GA',
			'unique_id' => $state[ 'ga_secret' ],
		] );
		$this->clearMfaCache( $user );
		RuntimeTestState::resetMfaProviderCache();

		return [
			'user_id'        => $user->ID,
			'user_login'     => $state[ 'user_login' ],
			'user_pass'      => $state[ 'user_pass' ],
			'login_path'     => '/wp-login.php',
			'otp_field_name' => ( new GoogleAuth( $user ) )->getLoginIntentFormParameter(),
			'current_otp'    => $this->currentGoogleOtp( $state[ 'ga_secret' ] ),
		];
	}

	/**
	 * @phpstan-param FixtureState $state
	 * @return array<string,mixed>
	 */
	private function seedEmailAuthLogin( array &$state ) :array {
		$user = $this->createFixtureUser( 'email' );
		$verifiedAt = \time();
		$state[ 'user_id' ] = $user->ID;
		$state[ 'created_user_ids' ][] = $user->ID;
		$state[ 'user_login' ] = $user->user_login;
		$state[ 'user_pass' ] = 'shield-browser-pass';

		$this->configureEmailLoginOptions( $verifiedAt, false, false );
		$this->clearMfaCache( $user );
		RuntimeTestState::resetMfaProviderCache();

		return [
			'user_id'        => $user->ID,
			'user_login'     => $state[ 'user_login' ],
			'user_pass'      => $state[ 'user_pass' ],
			'login_path'     => '/wp-login.php',
			'otp_field_name' => ( new Email( $user ) )->getLoginIntentFormParameter(),
		];
	}

	/**
	 * @phpstan-param FixtureState $state
	 * @return array<string,mixed>
	 */
	private function seedEmailPlusGaLogin( array &$state ) :array {
		$user = $this->createFixtureUser( 'email-ga' );
		$verifiedAt = \time();
		$state[ 'user_id' ] = $user->ID;
		$state[ 'created_user_ids' ][] = $user->ID;
		$state[ 'user_login' ] = $user->user_login;
		$state[ 'user_pass' ] = 'shield-browser-pass';
		$state[ 'ga_secret' ] = 'JBSWY3DPEHPK3PXP';

		$this->configureEmailLoginOptions( $verifiedAt, true, false );
		$state[ 'mfa_record_ids' ][] = TestDataFactory::insertMfaRecord( $user->ID, GoogleAuth::ProviderSlug(), [], [
			'label'     => 'Browser GA',
			'unique_id' => $state[ 'ga_secret' ],
		] );
		$this->clearMfaCache( $user );
		RuntimeTestState::resetMfaProviderCache();

		return [
			'user_id'              => $user->ID,
			'user_login'           => $state[ 'user_login' ],
			'user_pass'            => $state[ 'user_pass' ],
			'login_path'           => '/wp-login.php',
			'email_otp_field_name' => ( new Email( $user ) )->getLoginIntentFormParameter(),
			'ga_otp_field_name'    => ( new GoogleAuth( $user ) )->getLoginIntentFormParameter(),
			'current_ga_otp'       => $this->currentGoogleOtp( $state[ 'ga_secret' ] ),
		];
	}

	/**
	 * @phpstan-param FixtureState $state
	 * @return array<string,mixed>
	 */
	private function seedEmailPlusBackupLogin( array &$state ) :array {
		$user = $this->createFixtureUser( 'email-backup' );
		$verifiedAt = \time();
		$state[ 'user_id' ] = $user->ID;
		$state[ 'created_user_ids' ][] = $user->ID;
		$state[ 'user_login' ] = $user->user_login;
		$state[ 'user_pass' ] = 'shield-browser-pass';
		$state[ 'backup_code' ] = 'abc123def456';

		$this->configureEmailLoginOptions( $verifiedAt, false, true );
		$state[ 'mfa_record_ids' ][] = TestDataFactory::insertMfaRecord(
			$user->ID,
			BackupCodes::ProviderSlug(),
			[],
			[
				'label'     => 'Browser Backup Code',
				'unique_id' => \wp_hash_password( $state[ 'backup_code' ] ),
			]
		);
		$this->clearMfaCache( $user );
		RuntimeTestState::resetMfaProviderCache();

		return [
			'user_id'               => $user->ID,
			'user_login'            => $state[ 'user_login' ],
			'user_pass'             => $state[ 'user_pass' ],
			'login_path'            => '/wp-login.php',
			'email_otp_field_name'  => ( new Email( $user ) )->getLoginIntentFormParameter(),
			'backup_otp_field_name' => ( new BackupCodes( $user ) )->getLoginIntentFormParameter(),
			'backup_code'           => $state[ 'backup_code' ],
		];
	}

	private function configureEmailLoginOptions(
		int $verifiedAt,
		bool $enableGoogleAuth,
		bool $allowBackupCodes
	) :void {
		$enableGoogleAuthOpt = $enableGoogleAuth ? 'Y' : 'N';
		$allowBackupCodesOpt = $allowBackupCodes ? 'Y' : 'N';

		RuntimeTestState::applyPremiumCapabilities( [
			'2fa_webauthn',
		] );
		RuntimeTestState::controller()->opts
			->optSet( 'enable_email_authentication', 'Y' )
			->optSet( 'enable_email_auto_login', 'Y' )
			->optSet( 'email_can_send_verified_at', $verifiedAt )
			->optSet( 'email_any_user_set', 'Y' )
			->optSet( 'two_factor_auth_user_roles', [ 'administrator' ] )
			->optSet( 'enable_google_authenticator', $enableGoogleAuthOpt )
			->optSet( 'allow_backupcodes', $allowBackupCodesOpt )
			->optSet( 'suresend_emails', [] )
			->optSet( 'mfa_skip', 0 )
			->optSet( 'mfa_verify_page', 'custom_shield' )
			->store();
		RuntimeTestState::forcePersistOptions( [
			'enable_email_authentication' => 'Y',
			'enable_email_auto_login'     => 'Y',
			'email_can_send_verified_at'  => $verifiedAt,
			'email_any_user_set'          => 'Y',
			'two_factor_auth_user_roles'  => [ 'administrator' ],
			'enable_google_authenticator' => $enableGoogleAuthOpt,
			'allow_backupcodes'           => $allowBackupCodesOpt,
			'suresend_emails'             => [],
			'mfa_skip'                    => 0,
			'mfa_verify_page'             => 'custom_shield',
		] );
	}

	private function createFixtureUser( string $prefix ) :\WP_User {
		$login = 'shield-browser-'.$prefix.'-'.\wp_generate_password( 8, false );
		$userID = \wp_insert_user( [
			'user_login' => $login,
			'user_email' => $login.'@example.test',
			'user_pass'  => 'shield-browser-pass',
			'role'       => 'administrator',
		] );
		if ( \is_wp_error( $userID ) ) {
			throw new \RuntimeException( $userID->get_error_message() );
		}

		$user = Services::WpUsers()->getUserById( (int)$userID );
		if ( !$user instanceof \WP_User ) {
			throw new \RuntimeException( 'Could not load fixture user.' );
		}

		return $user;
	}

	/**
	 * @return array<string,int>
	 */
	private function recordCountsForUser( int $userID ) :array {
		$counts = [];
		foreach ( RuntimeTestState::controller()->db_con->mfa->getQuerySelector()->filterByUserID( $userID )->queryWithResult() as $record ) {
			$slug = (string)$record->slug;
			$counts[ $slug ] = ( $counts[ $slug ] ?? 0 ) + 1;
		}
		return $counts;
	}

	private function currentGoogleOtp( string $secret ) :string {
		return $secret === '' ? '' : ( new OtpGenerator() )->calculateCode( $secret );
	}

	/**
	 * @param list<string> $events
	 * @return array<string,int>
	 */
	private function eventCounts( array $events ) :array {
		$counts = [];
		foreach ( $events as $event ) {
			$event = (string)$event;
			$counts[ $event ] = ( $counts[ $event ] ?? 0 ) + 1;
		}
		return $counts;
	}

	/**
	 * @param list<array<string,mixed>> $mails
	 * @return list<string>
	 */
	private function mailRecipients( array $mails ) :array {
		$recipients = [];
		foreach ( $mails as $mail ) {
			foreach ( (array)( $mail[ 'to' ] ?? [] ) as $recipient ) {
				$recipients[] = (string)$recipient;
			}
		}
		return \array_values( \array_unique( \array_filter( $recipients ) ) );
	}

	/**
	 * @param list<array<string,mixed>> $mails
	 * @return array<string,mixed>
	 */
	private function latestEmailQuery( array $mails ) :array {
		$latest = \end( $mails );
		return \is_array( $latest ) && \is_array( $latest[ 'auto_login_query' ] ?? null ) ? $latest[ 'auto_login_query' ] : [];
	}

	private function clearMfaCache( \WP_User $user ) :void {
		( new MfaRecordsHandler() )->clearForUser( $user );
	}

	/**
	 * @param array<string,mixed> $runtime
	 */
	private function setRuntimeState( array $runtime ) :void {
		\update_option( self::RUNTIME_OPTION, $runtime, false );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function runtimeState() :array {
		$runtime = \get_option( self::RUNTIME_OPTION, [] );
		return \is_array( $runtime ) ? $runtime : [];
	}
}
