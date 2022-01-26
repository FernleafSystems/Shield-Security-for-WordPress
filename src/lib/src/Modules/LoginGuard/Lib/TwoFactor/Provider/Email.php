<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\SureSend\SendEmail;
use FernleafSystems\Wordpress\Services\Services;

class Email extends BaseProvider {

	const SLUG = 'email';

	private $secretToDelete = '';

	public function getJavascriptVars() :array {
		return [
			'ajax' => [
				'user_email2fa_toggle' => $this->getMod()->getAjaxActionData( 'user_email2fa_toggle' ),
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	public function postSuccessActions() {
		parent::postSuccessActions();
		if ( !empty( $this->secretToDelete ) ) {
			$secrets = $this->getAllCodes();
			unset( $secrets[ $this->secretToDelete ] );
			$this->storeCodes( $secrets );
		}
		return $this;
	}

	protected function processOtp( string $otp ) :bool {
		$valid = false;
		foreach ( array_keys( $this->getAllCodes() ) as $secret ) {
			if ( wp_check_password( $otp, $secret ) ) {
				$valid = true;
				$this->secretToDelete = $secret;
				break;
			}
		}
		return $valid;
	}

	public function getFormField() :array {
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		return [
			'slug'        => static::SLUG,
			'name'        => $this->getLoginFormParameter(),
			'type'        => 'text',
			'value'       => $this->fetchCodeFromRequest(),
			'placeholder' => __( 'A1B2C3', 'wp-simple-firewall' ),
			'text'        => __( 'Email OTP', 'wp-simple-firewall' ),
			'description' => __( 'Enter code sent to your email', 'wp-simple-firewall' ),
			'help_link'   => 'https://shsec.io/3t',
			'size'        => 6,
			'datas'       => [
				'auto_send'              => $mod->getMfaController()->isAutoSend2faEmail( $this->getUser() ) ? 1 : 0,
				'ajax_intent_email_send' => $mod->getAjaxActionData( 'intent_email_send', true ),
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

	protected function isEnforced() :bool {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		return count( array_intersect( $opts->getEmail2FaRoles(), $this->getUser()->roles ) ) > 0;
	}

	protected function hasValidSecret() :bool {
		return true;
	}

	public function sendEmailTwoFactorVerify() :bool {
		$user = $this->getUser();
		$sureCon = $this->getCon()->getModule_Comms()->getSureSendController();
		$useSureSend = $sureCon->isEnabled2Fa() && $sureCon->canUserSend( $user );

		$success = false;
		try {
			$code = $this->genNewCode();

			$success = ( $useSureSend && $this->send2faEmailSureSend( $code ) )
					   || $this->getMod()
							   ->getEmailProcessor()
							   ->sendEmailWithTemplate(
								   '/email/lp_2fa_email_code',
								   $user->user_email,
								   __( 'Two-Factor Login Verification', 'wp-simple-firewall' ),
								   [
									   'flags'   => [
										   'show_login_link' => !$this->getCon()->isRelabelled()
									   ],
									   'vars'    => [
										   'code' => $code
									   ],
									   'hrefs'   => [
										   'login_link' => 'https://shsec.io/96',
									   ],
									   'strings' => [
										   'someone'          => __( 'Someone attempted to login into this WordPress site using your account.', 'wp-simple-firewall' ),
										   'requires'         => __( 'Login requires verification with the following code.', 'wp-simple-firewall' ),
										   'verification'     => __( 'Verification Code', 'wp-simple-firewall' ),
										   'login_link'       => __( 'Why no login link?', 'wp-simple-firewall' ),
										   'details_heading'  => __( 'Login Details', 'wp-simple-firewall' ),
										   'details_url'      => sprintf( '%s: %s', __( 'URL', 'wp-simple-firewall' ),
											   Services::WpGeneral()->getHomeUrl() ),
										   'details_username' => sprintf( '%s: %s', __( 'Username', 'wp-simple-firewall' ), $user->user_login ),
										   'details_ip'       => sprintf( '%s: %s', __( 'IP Address', 'wp-simple-firewall' ),
											   Services::IP()->getRequestIp() ),
									   ]
								   ]
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

	protected function getProviderSpecificRenderData() :array {
		return [
			'strings' => [
				'label_email_authentication'                => __( 'Email Authentication', 'wp-simple-firewall' ),
				'title'                                     => __( 'Email Authentication', 'wp-simple-firewall' ),
				'description_email_authentication_checkbox' => __( 'Toggle the option to enable/disable email-based login authentication.', 'wp-simple-firewall' ),
				'provided_by'                               => sprintf( __( 'Provided by %s', 'wp-simple-firewall' ),
					$this->getCon()->getHumanName() )
			]
		];
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

	private function genNewCode() :string {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();

		$secrets = $this->getAllCodes();
		$new = $this->generateSimpleOTP();
		$secrets[ wp_hash_password( $new ) ] = Services::Request()
													   ->carbon()
													   ->addMinutes( $opts->getLoginIntentMinutes() )->timestamp;

		$this->storeCodes( array_slice( $secrets, -10 ) );
		return $new;
	}

	private function getAllCodes() :array {
		$secrets = $this->getSecret();
		return array_filter(
			is_array( $secrets ) ? $secrets : [],
			function ( $ts ) {
				return $ts >= Services::Request()->ts();
			}
		);
	}

	private function storeCodes( array $codes ) {
		$this->setSecret( $codes );
	}

	public function getProviderName() :string {
		return 'Email';
	}
}