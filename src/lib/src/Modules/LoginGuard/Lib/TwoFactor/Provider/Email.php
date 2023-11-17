<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MfaEmailAutoLogin;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MfaEmailToggle;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\MfaLoginCode;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Email\EmailVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties\MfaRecordsHandler;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\SureSend\SendEmail;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\PasswordGenerator;
use FernleafSystems\Wordpress\Services\Services;

class Email extends AbstractShieldProviderMfaDB {

	protected const SLUG = 'email';

	protected function maybeMigrate() :void {
		$meta = self::con()->user_metas->for( $this->getUser() );
		$legacyEnabled = $meta->email_validated;
		if ( $legacyEnabled ) {
			$this->toggleEmail2FA( $legacyEnabled );
			unset( $meta->email_validated );
			unset( $meta->email_secret );
		}
	}

	public function getJavascriptVars() :array {
		return Services::DataManipulation()->mergeArraysRecursive(
			parent::getJavascriptVars(),
			[
				'ajax' => [
					'profile_email2fa_toggle' => ActionData::Build( MfaEmailToggle::class ),
				],
			]
		);
	}

	/**
	 * If login nonce is provided, the OTP check is stricter and must be the same as that assigned to the nonce.
	 * Otherwise, we just check whether the OTP exists.
	 */
	protected function processOtp( string $otp ) :bool {
		$valid = false;
		foreach ( $this->loadMfaRecords() as $record ) {
			if ( $record->data[ 'hashed_login_nonce' ] === $this->workingHashedLoginNonce
				 && wp_check_password( $otp, $record->unique_id ) ) {
				$valid = true;
				$this->deleteAllSecrets();
			}
		}
		return $valid;
	}

	public function postSuccessActions() :void {
		parent::postSuccessActions();
		( new MfaRecordsHandler() )->clearForUser( $this->getUser() );
	}

	public function getFormField() :array {
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
				'auto_send' => $this->mod()
									->getMfaController()
									->isAutoSend2faEmail( $this->getUser() ) ? 1 : 0,
			],
			'supp'        => [
				'send_email' => __( 'Send OTP Code', 'wp-simple-firewall' ),
			]
		];
	}

	public function toggleEmail2FA( bool $onOrOff ) :void {
		self::con()->user_metas->for( $this->getUser() )->email_2fa_enabled = $onOrOff;
	}

	public function hasValidatedProfile() :bool {
		$meta = self::con()->user_metas->for( $this->getUser() );
		return $this->isEnforced()
			   || ( $this->opts()->isEnabledEmailAuthAnyUserSet() && $meta->email_2fa_enabled );
	}

	public function isEnforced() :bool {
		return \count( \array_intersect( $this->opts()->getEmail2FaRoles(), $this->getUser()->roles ) ) > 0;
	}

	public function sendEmailTwoFactorVerify( string $plainNonce, string $autoRedirect = '' ) :bool {
		$con = self::con();
		$mfaCon = $this->mod()->getMfaController();
		$user = $this->getUser();
		$useSureSend = $con->getModule_Comms()->getSureSendController()->can_2FA( $user );

		$success = false;
		try {
			if ( !$mfaCon->verifyLoginNonce( $user, $plainNonce ) ) {
				throw new \Exception( 'No such login intent' );
			}

			$hashedNonce = $mfaCon->findHashedNonce( $user, $plainNonce );
			$intents = $mfaCon->getActiveLoginIntents( $user );
			$intents[ $hashedNonce ][ 'auto_email_sent' ] = true;
			$con->user_metas->for( $user )->login_intents = $intents;

			$otp = $this->generate2faCode( $hashedNonce );

			$success = ( $useSureSend && ( new SendEmail() )->send2FA( $this->getUser(), $otp ) )
					   ||
					   $con->email_con->sendVO(
						   EmailVO::Factory(
							   $user->user_email,
							   __( 'Two-Factor Login Verification', 'wp-simple-firewall' ),
							   $con->action_router->render( MfaLoginCode::SLUG, [
								   'home_url'       => Services::WpGeneral()->getHomeUrl(),
								   'ip'             => $con->this_req->ip,
								   'user_id'        => $user->ID,
								   'otp'            => $otp,
								   'url_auto_login' => $con->plugin_urls->noncedPluginAction(
									   MfaEmailAutoLogin::class,
									   null,
									   [
										   $this->getLoginIntentFormParameter() => $otp,
										   'login_nonce'                        => $plainNonce,
										   'user_id'                            => $user->ID,
										   // breaks without encoding.
										   'redirect_to'                        => \base64_encode( $autoRedirect ),
									   ]
								   ),
							   ] )
						   )
					   );
		}
		catch ( \Exception $e ) {
		}

		return $success;
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
						self::con()->getHumanName() )
				]
			]
		);
	}

	public function isProviderEnabled() :bool {
		return $this->opts()->isEmailAuthenticationActive();
	}

	public function isProviderAvailableToUser() :bool {
		return parent::isProviderAvailableToUser()
			   && ( $this->isEnforced() || $this->opts()->isEnabledEmailAuthAnyUserSet() );
	}

	private function generate2faCode( string $hashedLoginNonce ) :string {
		$otp = apply_filters( 'shield/2fa_email_otp', PasswordGenerator::Gen( 6, true, false, false ) );
		$this->createNewSecretRecord( wp_hash_password( $otp ), 'Email 2FA', [
			'hashed_login_nonce' => $hashedLoginNonce
		] );
		return $otp;
	}

	public function getProviderName() :string {
		return 'Email';
	}

	public function removeFromProfile() :void {
		parent::removeFromProfile();
		$this->toggleEmail2FA( false );
	}
}