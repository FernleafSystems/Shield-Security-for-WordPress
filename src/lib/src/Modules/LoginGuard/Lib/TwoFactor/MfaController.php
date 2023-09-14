<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Utilities\Data\Response\StdResponse;
use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\HookTimings;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;

class MfaController {

	use ExecOnce;
	use LoginGuard\ModConsumer;

	/**
	 * These values MUST align with the option 'mfa_verify_page'
	 */
	public const LOGIN_INTENT_PAGE_FORMAT_SHIELD = 'custom_shield';
	public const LOGIN_INTENT_PAGE_FORMAT_WP = 'wp_login';

	/**
	 * @var Provider\Provider2faInterface[]
	 */
	private $providers;

	private $mfaProfilesCon;

	protected function run() {
		add_action( 'init', [ $this, 'onWpInit' ], HookTimings::INIT_LOGIN_INTENT_REQUEST_CAPTURE ); // Login Intent
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ] ); // Profile handling
		add_filter( 'login_message', [ $this, 'onLoginMessage' ], 11 );
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

			$providers = \array_map(
				function ( $provider ) {
					return $provider->getProviderName();
				},
				$this->getProvidersActiveForUser( $user )
			);
			$content[] = sprintf( '<em>%s</em>: %s', __( 'Active 2FA', 'wp-simple-firewall' ),
				empty( $providers ) ? __( 'None', 'wp-simple-firewall' ) : \implode( ', ', $providers ) );

			return $content;
		}, 10, 2 );
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

	public function useLoginIntentPage() :bool {
		return $this->opts()->isOpt( 'mfa_verify_page', 'custom_shield' );
	}

	public function getMfaProfilesCon() :MfaProfilesController {
		return $this->mfaProfilesCon ?? $this->mfaProfilesCon = new MfaProfilesController();
	}

	public function onWpLoaded() {
		$this->getMfaProfilesCon()->execute();
		$this->addToUserStatusColumn();
	}

	/**
	 * @return Provider\Provider2faInterface[]
	 */
	public function getProviders() :array {
		if ( !\is_array( $this->providers ) ) {
			$this->providers = [];
			foreach ( $this->collateMfaProviderClasses() as $providerClass ) {
				$this->providers[ $providerClass::ProviderSlug() ] = new $providerClass();
			}
		}
		return $this->providers;
	}

	/**
	 * @return Provider\Provider2faInterface[]
	 */
	private function collateMfaProviderClasses() :array {
		$shieldProviders = [
			Provider\Email::class,
			Provider\GoogleAuth::class,
			Provider\Yubikey::class,
			Provider\BackupCodes::class,
			Provider\U2F::class,
		];
		$finalProviders = apply_filters( 'shield/2fa_providers', $shieldProviders );

		/**
		 * Ensure we have a valid data structure before proceeding.
		 */
		if ( !\is_array( $finalProviders ) ) {
			$finalProviders = $shieldProviders;
		}

		$finalValid = \array_filter( $finalProviders, function ( string $providerClass ) {
			/** @var Provider\Provider2faInterface $providerClass - not really, but helps with intelli */
			return isset( class_implements( $providerClass )[ Provider\Provider2faInterface::class ] )
				   && \preg_match( '#^[a-z]+$#', $providerClass::ProviderSlug() );
		} );

		// Filter out any duplicate slugs.
		$duplicateSlugs = \array_filter(
			\array_count_values( \array_map(
				function ( $provider ) {
					/** @var Provider\Provider2faInterface $provider */
					return \strtolower( $provider::ProviderSlug() );
				},
				$finalValid
			) ),
			function ( $count ) {
				return $count > 1;
			}
		);
		if ( !empty( $duplicateSlugs ) ) {
			error_log( sprintf( 'Duplicate 2FA Provider Slugs: %s', \implode( ', ', \array_keys( $duplicateSlugs ) ) ) );
			$finalValid = \array_filter(
				$finalValid,
				function ( $providerClass ) use ( $duplicateSlugs ) {
					/** @var Provider\Provider2faInterface $providerClass */
					return !array_key_exists( $providerClass::ProviderSlug(), $duplicateSlugs );
				}
			);
		}

		return $finalValid;
	}

	/**
	 * Ensures that BackupCode provider isn't supplied on its own, and the user profile is setup for each.
	 * @return Provider\Provider2faInterface[]
	 */
	public function getProvidersForUser( \WP_User $user, bool $onlyActive = false ) :array {
		$Ps = \array_filter(
			$this->getProviders(),
			function ( $provider ) use ( $user, $onlyActive ) {
				$provider->setUser( $user );
				return $provider->isProviderAvailableToUser() && ( !$onlyActive || $provider->isProfileActive() );
			}
		);

		// If you have only 1 provider, and it's not a standalone provider, we don't offer any providers.
		if ( \count( $Ps ) === 1 && !reset( $Ps )->isProviderStandalone() ) {
			$Ps = [];
		}
		return $Ps;
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
			function ( $intent ) {
				$opts = $this->opts();

				$active = false;
				if ( \is_array( $intent ) ) {
					$active = $intent[ 'start' ] > ( Services::Request()->ts() - $opts->getLoginIntentMinutes()*60 )
							  &&
							  $intent[ 'attempts' ] < $opts->getLoginIntentMaxAttempts();
				}

				return $active;
			}
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
}