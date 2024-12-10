<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Utilities\Data\Response\StdResponse;
use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\HookTimings;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class MfaController {

	use ExecOnce;
	use PluginControllerConsumer;

	/**
	 * These values MUST align with the option 'mfa_verify_page'
	 */
	public const LOGIN_INTENT_PAGE_FORMAT_SHIELD = 'custom_shield';
	public const LOGIN_INTENT_PAGE_FORMAT_WP = 'wp_login';

	/**
	 * @var Provider\AbstractProvider[][]
	 */
	private array $providers;

	private MfaProfilesController $mfaProfilesCon;

	protected function canRun() :bool {
		return !self::con()->this_req->wp_is_xmlrpc;
	}

	protected function run() {
		add_action( 'init', [ $this, 'onWpInit' ], HookTimings::INIT_LOGIN_INTENT_REQUEST_CAPTURE ); // Login Intent
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ] ); // Profile handling
		add_action( 'admin_init', [ $this, 'onAdminInit' ] );
		add_filter( 'login_message', [ $this, 'onLoginMessage' ], 11 );
	}

	public function getLoginIntentMinutes() :int {
		return (int)\max( 1,
			apply_filters( 'shield/login_intent_timeout', self::con()->cfg->configuration->def( 'login_intent_timeout' ) )
		);
	}

	/**
	 * We only want to auto send email if:
	 * - email is the only provider
	 * - it's the first time loading the 2FA page (we don't auto-send reloading the page after failure)
	 */
	public function isAutoSend2faEmail( \WP_User $user ) :bool {
		$auto = false;

		$providers = $this->getProvidersActiveForUser( $user );
		unset( $providers[ Provider\BackupCodes::ProviderSlug() ] );

		/** @var Provider\Email|null $emailProvider */
		$emailProvider = $providers[ Provider\Email::ProviderSlug() ] ?? null;
		if ( \count( $providers ) === 1 && !empty( $emailProvider ) ) {
			$intents = $this->getActiveLoginIntents( $user );
			$latest = \array_pop( $intents );
			$auto = !empty( $latest ) && empty( $latest[ 'auto_email_sent' ] );
		}
		return $auto;
	}

	public function onLoginMessage( $msg ) {
		switch ( (string)Services::Request()->query( 'shield_msg' ) ) {

			case 'too_many_attempts':
				$shieldMsg = __( 'Too many 2FA verification attempts - please login again.', 'wp-simple-firewall' );
				break;

			case 'no_user_login_intent':
				$shieldMsg = __( 'No 2FA login intent found - your login may have expired.', 'wp-simple-firewall' );
				break;

			case 'no_providers':
				$shieldMsg = __( 'No 2FA login providers found.', 'wp-simple-firewall' );
				break;

			default:
				$shieldMsg = '';
				break;
		}

		if ( !empty( $shieldMsg ) ) {
			$msg = sprintf( '<div id="login_error">%s</div>', esc_html( $shieldMsg ) );
		}

		return $msg;
	}

	public function onWpInit() {
		( new LoginRequestCapture() )->execute();
	}

	public function getMfaProfilesCon() :MfaProfilesController {
		return $this->mfaProfilesCon ??= new MfaProfilesController();
	}

	public function onWpLoaded() {
		$this->getMfaProfilesCon()->execute();
	}

	public function onAdminInit() {
		$this->addToUserStatusColumn();
	}

	private function addToUserStatusColumn() {
		// Display manually suspended on the user list table; TODO: add auto suspended
		add_filter( 'shield/user_status_column', function ( array $content, \WP_User $user ) {

			$twoFAat = self::con()->user_metas->for( $user )->record->last_2fa_verified_at;
			$carbon = Services::Request()
							  ->carbon()
							  ->setTimestamp( $twoFAat );

			$content[] = sprintf( '<em title="%s">%s</em>: %s',
				$twoFAat > 0 ? $carbon->toIso8601String() : __( 'Not Recorded', 'wp-simple-firewall' ),
				__( '2FA At', 'wp-simple-firewall' ),
				$twoFAat > 0 ? $carbon->diffForHumans() : __( 'Not Recorded', 'wp-simple-firewall' )
			);

			$providers = \array_map( fn( $p ) => $p->getProviderName(), $this->getProvidersActiveForUser( $user ) );
			$content[] = sprintf( '<em>%s</em>: %s', __( 'Active 2FA', 'wp-simple-firewall' ),
				empty( $providers ) ? __( 'None', 'wp-simple-firewall' ) : \implode( ', ', $providers ) );

			return $content;
		}, 10, 2 );
	}

	/**
	 * @return Provider\AbstractProvider[]
	 */
	public function collateMfaProviderClasses() :array {

		$enum = apply_filters( 'shield/2fa_providers', $this->enumShieldProviders() );
		$providerClasses = \array_filter(
			\array_filter( \is_array( $enum ) ? $enum : $this->enumShieldProviders(), '\is_string' ),
			/** @var Provider\Provider2faInterface|string $providerClass */
			fn( string $provider ) => isset( \class_implements( $provider )[ Provider\Provider2faInterface::class ] )
									  && \preg_match( '#^[a-z0-9]+$#', $provider::ProviderSlug() )
		);

		// Find duplicate slugs.
		$duplicateSlugs = \array_filter(
			\array_count_values( \array_map(
			/** @var Provider\Provider2faInterface|string $provider */
				fn( string $provider ) => \strtolower( $provider::ProviderSlug() ),
				$providerClasses
			) ),
			fn( $count ) => $count > 1
		);

		return empty( $duplicateSlugs ) ?
			$providerClasses :
			\array_filter(
				$providerClasses,
				/** @var Provider\Provider2faInterface|string $provider */
				fn( string $provider ) => !\array_key_exists( $provider::ProviderSlug(), $duplicateSlugs )
			);
	}

	/**
	 * Ensures that BackupCode provider isn't supplied on its own, and the user profile is setup for each.
	 * @return Provider\Provider2faInterface[]
	 */
	public function getProvidersForUser( \WP_User $user, bool $onlyActive = false ) :array {
		$this->providers ??= [];

		if ( !isset( $this->providers[ $user->ID ] ) ) {
			$this->providers[ $user->ID ] = [];
			foreach ( $this->collateMfaProviderClasses() as $providerClass ) {
				$this->providers[ $user->ID ][ $providerClass::ProviderSlug() ] = new $providerClass( $user );
			}
		}

		$userProviders = \array_filter(
			$this->providers[ $user->ID ],
			fn( $provider ) => $provider->isProviderAvailableToUser() && ( !$onlyActive || $provider->isProfileActive() )
		);

		// If you have only 1 provider, and it's not a standalone provider, we don't offer any providers.
		if ( \count( $userProviders ) === 1 && !\reset( $userProviders )->isProviderStandalone() ) {
			$userProviders = [];
		}
		return $userProviders;
	}

	/**
	 * @return Provider\Provider2faInterface[]
	 */
	public function getProvidersActiveForUser( \WP_User $user ) :array {
		return $this->getProvidersForUser( $user, true );
	}

	/**
	 * @return Provider\Provider2faInterface[]
	 */
	public function getProvidersAvailableToUser( \WP_User $user ) :array {
		return $this->getProvidersForUser( $user );
	}

	public function getMfaSkip() :int { // seconds
		return \DAY_IN_SECONDS*( self::con()->opts->optGet( 'mfa_skip' ) );
	}

	public function isSubjectToLoginIntent( \WP_User $user ) :bool {
		return !self::con()->this_req->request_bypasses_all_restrictions
			   && \count( $this->getProvidersActiveForUser( $user ) ) > 0;
	}

	public function removeAllFactorsForUser( int $userID ) :StdResponse {
		$result = new StdResponse();

		$user = Services::WpUsers()->getUserById( $userID );
		if ( $user instanceof \WP_User ) {
			foreach ( $this->getProvidersActiveForUser( $user ) as $provider ) {
				$provider->removeFromProfile();
			}
			$result->success = true;
			$result->msg_text = sprintf( __( 'All MFA providers removed from user with ID %s.' ),
				$userID );
		}
		else {
			$result->success = false;
			$result->error_text = sprintf( __( "User doesn't exist with ID %s." ),
				$userID );
		}

		return $result;
	}

	/**
	 * @return array[]
	 */
	public function getActiveLoginIntents( \WP_User $user ) :array {
		$meta = self::con()->user_metas->for( $user );
		return \array_filter(
			\is_array( $meta->login_intents ) ? $meta->login_intents : [],
			fn( $intent ) => \is_array( $intent )
							 && $intent[ 'start' ] > ( Services::Request()->ts() - $this->getLoginIntentMinutes()*60 )
							 && $intent[ 'attempts' ] < self::con()->cfg->configuration->def( 'login_intent_max_attempts' )
		);
	}

	public function findHashedNonce( \WP_User $user, string $plainNonce ) :string {
		$hashedNonce = '';
		foreach ( \array_keys( $this->getActiveLoginIntents( $user ) ) as $maybeHash ) {
			if ( wp_check_password( $plainNonce.$user->ID, $maybeHash ) ) {
				$hashedNonce = $maybeHash;
				break;
			}
		}
		return $hashedNonce;
	}

	public function verifyLoginNonce( \WP_User $user, string $plainNonce ) :bool {
		$valid = !empty( $this->findHashedNonce( $user, $plainNonce ) );
		if ( !$valid ) {
			self::con()->fireEvent( '2fa_nonce_verify_fail', [
				'audit_params' => [
					'user_login' => $user->user_login,
				]
			] );
		}
		return $valid;
	}

	private function enumShieldProviders() :array {
		return [
			Provider\Email::class,
			Provider\GoogleAuth::class,
			Provider\Yubikey::class,
			Provider\BackupCodes::class,
			Provider\Passkey::class,
		];
	}
}