<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MfaEmailSendIntent;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MfaEmailToggle;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\MfaLoginCode;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\SureSend\SendEmail;
use FernleafSystems\Wordpress\Services\Services;

class Email extends AbstractShieldProvider {

	protected const SLUG = 'email';

	public function getJavascriptVars() :array {
		return [
			'ajax' => [
				'profile_email2fa_toggle' => ActionData::Build( MfaEmailToggle::SLUG ),
			],
		];
	}

	/**
	 * If login nonce is provided, the OTP check is stricter and must be the same as that assigned to the nonce.
	 * Otherwise, we just check whether the OTP exists.
	 */
	protected function processOtp( string $otp ) :bool {
		$secret = $this->getSecret()[ $this->workingHashedLoginNonce ] ?? '';
		return !empty( $secret ) && wp_check_password( $otp, $secret );
	}

	/**
	 * @inheritDoc
	 */
	public function postSuccessActions() {
		parent::postSuccessActions();
		return $this->setSecret( [] );
	}

	public function getFormField() :array {
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		return [
			'slug'        => static::ProviderSlug(),
			'name'        => $this->getLoginIntentFormParameter(),
			'type'        => 'text',
			'value'       => $this->fetchOtpFromRequest(),
			'placeholder' => __( 'A1B2C3', 'wp-simple-firewall' ),
			'text'        => __( 'Email OTP', 'wp-simple-firewall' ),
			'description' => __( 'Enter code sent to your email', 'wp-simple-firewall' ),
			'help_link'   => 'https://shsec.io/3t',
			'size'        => 6,
			'datas'       => [
				'auto_send'              => $mod->getMfaController()->isAutoSend2faEmail( $this->getUser() ) ? 1 : 0,
				'ajax_intent_email_send' => ActionData::BuildJson( MfaEmailSendIntent::SLUG ),
			],
			'supp'        => [
				'send_email' => __( 'Send OTP Code', 'wp-simple-firewall' ),
			]
		];
	}

	public function hasValidatedProfile() :bool {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		$validated = parent::hasValidatedProfile();
		$this->setProfileValidated(
			$this->isEnforced() || ( $validated && $opts->isEnabledEmailAuthAnyUserSet() )
		);
		return parent::hasValidatedProfile();
	}

	public function isEnforced() :bool {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		return count( array_intersect( $opts->getEmail2FaRoles(), $this->getUser()->roles ) ) > 0;
	}

	protected function hasValidSecret() :bool {
		return true;
	}

	public function sendEmailTwoFactorVerify( string $plainNonce ) :bool {
		$con = $this->getCon();
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		$mfaCon = $mod->getMfaController();
		$user = $this->getUser();
		$userMeta = $con->getUserMeta( $user );
		$sureCon = $con->getModule_Comms()->getSureSendController();
		$useSureSend = $sureCon->isEnabled2Fa() && $sureCon->canUserSend( $user );

		$success = false;
		try {
			if ( !$mfaCon->verifyLoginNonce( $user, $plainNonce ) ) {
				throw new \Exception( 'No such login intent' );
			}

			$hashedNonce = $mfaCon->findHashedNonce( $user, $plainNonce );
			$intents = $mfaCon->getActiveLoginIntents( $user );
			$intents[ $hashedNonce ][ 'auto_email_sent' ] = true;
			$userMeta->login_intents = $intents;

			$otp = $this->generate2faCode( $hashedNonce );

			$success = ( $useSureSend && $this->send2faEmailSureSend( $otp ) )
					   || $this->getMod()
							   ->getEmailProcessor()
							   ->send(
								   $user->user_email,
								   __( 'Two-Factor Login Verification', 'wp-simple-firewall' ),
								   $con->action_router->render( MfaLoginCode::SLUG, [
									   'home_url' => Services::WpGeneral()->getHomeUrl(),
									   'ip'       => $con->this_req->ip,
									   'user_id'  => $user->ID,
									   'otp'      => $otp,
								   ] )
							   );
		}
		catch ( \Exception $e ) {
		}

		return $success;
	}

	private function send2faEmailSureSend( string $code ) :bool {
		return ( new SendEmail() )
			->setMod( $this->getMod() )
			->send2FA( $this->getUser(), $code );
	}

	protected function getUserProfileFormRenderData() :array {
		return Services::DataManipulation()->mergeArraysRecursive(
			parent::getUserProfileFormRenderData(),
			[
				'strings' => [
					'label_email_authentication'                => __( 'Email Authentication', 'wp-simple-firewall' ),
					'title'                                     => __( 'Email Authentication', 'wp-simple-firewall' ),
					'description_email_authentication_checkbox' => __( 'Toggle the option to enable/disable email-based login authentication.', 'wp-simple-firewall' ),
					'provided_by'                               => sprintf( __( 'Provided by %s', 'wp-simple-firewall' ),
						$this->getCon()->getHumanName() )
				]
			]
		);
	}

	public function isProviderEnabled() :bool {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		return $opts->isEmailAuthenticationActive();
	}

	public function isProviderAvailableToUser() :bool {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		return parent::isProviderAvailableToUser()
			   && ( $this->isEnforced() || $opts->isEnabledEmailAuthAnyUserSet() );
	}

	private function generate2faCode( string $hashedLoginNonce ) :string {
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();

		$secrets = $this->getSecret();
		if ( !is_array( $secrets ) ) {
			$secrets = [];
		}

		$otp = LoginGuard\Lib\TwoFactor\Utilties\OneTimePassword::Generate();
		$secrets[ $hashedLoginNonce ] = wp_hash_password( $otp );

		// Clean old secrets linked to expired login intents
		$this->setSecret( array_intersect_key(
			$secrets,
			$mod->getMfaController()->getActiveLoginIntents( $this->getUser() )
		) );
		return $otp;
	}

	public function getProviderName() :string {
		return 'Email';
	}
}