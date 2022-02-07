<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Utilities\Data\Response\StdResponse;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;
use FernleafSystems\Wordpress\Services\Services;

class MfaController extends Shield\Modules\Base\Common\ExecOnceModConsumer {

	/**
	 * @var Provider\BaseProvider[]
	 */
	private $providers;

	protected function run() {
		add_action( 'init', [ $this, 'onWpInit' ] ); // Login Intent handling
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ] ); // Profile handling
		add_filter( 'login_message', [ $this, 'onLoginMessage' ], 11 );
	}

	private function addToUserStatusColumn() {
		// Display manually suspended on the user list table; TODO: at auto suspended
		add_filter( 'shield/user_status_column', function ( array $content, \WP_User $user ) {

			$last2fa = $this->getCon()->getUserMeta( $user )->record->last_2fa_verified_at;
			$content[] = sprintf( '<em>%s</em>: %s', __( '2FA At', 'wp-simple-firewall' ),
				empty( $last2fa ) ? __( 'Never', 'wp-simple-firewall' ) : Services::Request()
																				  ->carbon()
																				  ->setTimestamp( $last2fa )
																				  ->diffForHumans()
			);

			$providers = array_map(
				function ( $provider ) {
					return $provider->getProviderName();
				},
				$this->getProvidersForUser( $user, true )
			);
			$content[] = sprintf( '<em>%s</em>: %s', __( 'Active 2FA', 'wp-simple-firewall' ),
				empty( $providers ) ? __( 'None', 'wp-simple-firewall' ) : implode( ', ', $providers ) );

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

		$providers = $this->getProvidersForUser( $user, true );
		unset( $providers[ Provider\BackupCodes::SLUG ] );

		/** @var Provider\Email|null $emailProvider */
		$emailProvider = $providers[ Provider\Email::SLUG ] ?? null;
		if ( count( $providers ) === 1 && !empty( $emailProvider ) ) {
			$nonces = array_keys( $this->getActiveLoginIntents( $user ) );
			$latestNonce = (string)array_pop( $nonces );
			$auto = !$emailProvider->hasOtpForNonce( $latestNonce );
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
		( new LoginRequestCapture() )
			->setMod( $this->getMod() )
			->execute();
		( new LoginIntentRequestCapture() )
			->setMod( $this->getMod() )
			->execute();
	}

	public function useLoginIntentPage() :bool {
		return $this->getOptions()->isOpt( 'mfa_verify_page', 'custom_shield' );
	}

	public function onWpLoaded() {
		( new MfaProfilesController() )
			->setMod( $this->getMod() )
			->setMfaController( $this ) // TODO: remove
			->execute();
		$this->addToUserStatusColumn();
	}

	/**
	 * @return Provider\BaseProvider[]
	 */
	public function getProviders() :array {
		if ( !is_array( $this->providers ) ) {
			$this->providers = array_map(
				function ( $provider ) {
					return $provider->setMod( $this->getMod() );
				},
				[
					Provider\Email::SLUG       => new Provider\Email(),
					Provider\GoogleAuth::SLUG  => new Provider\GoogleAuth(),
					Provider\Yubikey::SLUG     => new Provider\Yubikey(),
					Provider\BackupCodes::SLUG => new Provider\BackupCodes(),
					Provider\U2F::SLUG         => new Provider\U2F(),
				]
			);
		}
		return $this->providers;
	}

	/**
	 * Ensures that BackupCode provider isn't supplied on its own, and the user profile is setup for each.
	 * @return Provider\BaseProvider[]
	 */
	public function getProvidersForUser( \WP_User $user, bool $onlyActive = false ) :array {
		$Ps = array_filter(
			$this->getProviders(),
			function ( $provider ) use ( $user, $onlyActive ) {
				$provider->setUser( $user );
				return $provider->isProviderAvailableToUser()
					   && ( !$onlyActive || $provider->isProfileActive() );
			}
		);

		// BackupCode should NEVER be the only 1 provider available.
		if ( count( $Ps ) === 1 ) {
			/** @var Provider\BaseProvider $first */
			$first = reset( $Ps );
			if ( !$first::STANDALONE ) {
				$Ps = [];
			}
		}
		return $Ps;
	}

	public function isSubjectToLoginIntent( \WP_User $user ) :bool {
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		return !$mod->isVisitorWhitelisted() && count( $this->getProvidersForUser( $user, true ) ) > 0;
	}

	public function removeAllFactorsForUser( int $userID ) :StdResponse {
		$result = new StdResponse();

		$user = Services::WpUsers()->getUserById( $userID );
		if ( $user instanceof \WP_User ) {
			foreach ( $this->getProvidersForUser( $user, true ) as $provider ) {
				$provider->setUser( $user )
						 ->remove();
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

	public function getActiveLoginIntents( \WP_User $user ) :array {
		$meta = $this->getCon()->getUserMeta( $user );
		return array_filter(
			is_array( $meta->login_intents ) ? $meta->login_intents : [],
			function ( $intent ) {
				/** @var LoginGuard\Options $opts */
				$opts = $this->getOptions();

				$active = false;
				if ( is_array( $intent ) ) {
					$active = $intent[ 'start' ] > ( Services::Request()->ts() - $opts->getLoginIntentMinutes()*60 )
							  &&
							  $intent[ 'attempts' ] < $opts->getLoginIntentMaxAttempts();
				}

				return $active;
			}
		);
	}
}