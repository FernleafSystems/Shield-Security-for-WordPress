<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Captcha\CaptchaConfigVO;
use FernleafSystems\Wordpress\Services\Services;

class ModCon extends BaseShield\ModCon {

	/**
	 * @var Lib\TwoFactor\MfaController
	 */
	private $mfaCon;

	protected function preProcessOptions() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		/**
		 * $oWp = $this->loadWpFunctionsProcessor();
		 * $sCustomLoginPath = $this->cleanLoginUrlPath();
		 * if ( !empty( $sCustomLoginPath ) && $oWp->getIsPermalinksEnabled() ) {
		 * $oWp->resavePermalinks();
		 * }
		 */
		if ( $this->isModuleOptionsRequest() && $opts->isEnabledEmailAuth() && !$opts->getIfCanSendEmailVerified() ) {
			$this->setIfCanSendEmail( false )
				 ->sendEmailVerifyCanSend();
		}

		$IDs = $opts->getOpt( 'antibot_form_ids', [] );
		foreach ( $IDs as $nKey => $id ) {
			$id = trim( strip_tags( $id ) );
			if ( empty( $id ) ) {
				unset( $IDs[ $nKey ] );
			}
			else {
				$IDs[ $nKey ] = $id;
			}
		}
		$opts->setOpt( 'antibot_form_ids', array_values( array_unique( $IDs ) ) );

		$this->cleanLoginUrlPath();
		$this->ensureCorrectCaptchaConfig();

		if ( $opts->isEnabledAntiBot() ) {
			$opts->setOpt( 'enable_google_recaptcha_login', 'disabled' );
			$opts->setOpt( 'enable_login_gasp_check', 'N' );
		}

		$opts->setOpt( 'two_factor_auth_user_roles', $opts->getEmail2FaRoles() );

		if ( !$opts->isOpt( 'mfa_verify_page', 'custom_shield' )
			 && !Services::WpGeneral()->getWordpressIsAtLeastVersion( '4.0' ) ) {
			$opts->resetOptToDefault( 'mfa_verify_page' );
		}

		$redirect = preg_replace( '#[^0-9a-z_\-/.]#i', '', (string)$opts->getOpt( 'rename_wplogin_redirect' ) );
		if ( !empty( $redirect ) ) {

			$redirect = preg_replace( '#^http(s)?//.*/#iU', '', $redirect );
			if ( !empty( $redirect ) ) {
				$redirect = '/'.ltrim( $redirect, '/' );
			}
		}
		$opts->setOpt( 'rename_wplogin_redirect', $redirect );

		if ( empty( $opts->getOpt( 'mfa_user_setup_pages' ) ) ) {
			$opts->setOpt( 'mfa_user_setup_pages', [ 'profile' ] );
		}
	}

	public function ensureCorrectCaptchaConfig() {
		/** @var Options $opts */
		$opts = $this->getOptions();

		$style = $opts->getOpt( 'enable_google_recaptcha_login' );
		if ( $this->isPremium() ) {
			$cfg = $this->getCaptchaCfg();
			if ( $cfg->provider == $cfg::PROV_GOOGLE_RECAP2 ) {
				if ( !$cfg->invisible && $style == 'invisible' ) {
					$opts->setOpt( 'enable_google_recaptcha_login', 'default' );
				}
			}
		}
		elseif ( !in_array( $style, [ 'disabled', 'default' ] ) ) {
			$opts->setOpt( 'enable_google_recaptcha_login', 'default' );
		}
	}

	protected function handleModAction( string $action ) {
		switch ( $action ) {
			case 'email_send_verify':
				$this->processEmailSendVerify();
				break;
			default:
				parent::handleModAction( $action );
				break;
		}
	}

	/**
	 * @uses wp_redirect()
	 */
	private function processEmailSendVerify() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$this->setIfCanSendEmail( true );
		$this->saveModOptions();

		if ( $opts->getIfCanSendEmailVerified() ) {
			$success = true;
			$msg = __( 'Email verification completed successfully.', 'wp-simple-firewall' );
		}
		else {
			$success = false;
			$msg = __( 'Email verification could not be completed.', 'wp-simple-firewall' );
		}
		$this->setFlashAdminNotice( $msg, null, !$success );
		if ( Services::WpUsers()->isUserLoggedIn() ) {
			Services::Response()->redirect( $this->getUrl_AdminPage() );
		}
	}

	/**
	 * @param string $to
	 * @param bool   $bSendAsLink
	 * @return bool
	 */
	public function sendEmailVerifyCanSend( $to = null, $bSendAsLink = true ) {

		if ( !Services::Data()->validEmail( $to ) ) {
			$to = get_bloginfo( 'admin_email' );
		}

		$msg = [
			__( 'Before enabling 2-factor email authentication for your WordPress site, you must verify you can receive this email.', 'wp-simple-firewall' ),
			__( 'This verifies your website can send email and that your account can receive emails sent from your site.', 'wp-simple-firewall' ),
			''
		];

		if ( $bSendAsLink ) {
			$msg[] = sprintf(
				__( 'Click the verify link: %s', 'wp-simple-firewall' ),
				add_query_arg( $this->getModActionParams( 'email_send_verify' ), Services::WpGeneral()->getHomeUrl() )
			);
		}
		else {
			$msg[] = sprintf( __( "Here's your code for the guided wizard: %s", 'wp-simple-firewall' ), $this->getCanEmailVerifyCode() );
		}

		return $this->getEmailProcessor()
					->sendEmailWithWrap(
						$to,
						__( 'Email Sending Verification', 'wp-simple-firewall' ),
						$msg
					);
	}

	private function cleanLoginUrlPath() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$path = $opts->getCustomLoginPath();
		if ( !empty( $path ) ) {
			$path = preg_replace( '#[^0-9a-zA-Z-]#', '', trim( $path, '/' ) );
			$this->getOptions()->setOpt( 'rename_wplogin_path', $path );
		}
	}

	public function getGaspKey() :string {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$sKey = $opts->getOpt( 'gasp_key' );
		if ( empty( $sKey ) ) {
			$sKey = uniqid();
			$opts->setOpt( 'gasp_key', $sKey );
		}
		return $this->prefix( $sKey );
	}

	public function getTextImAHuman() :string {
		return stripslashes( $this->getTextOpt( 'text_imahuman' ) );
	}

	public function getTextPleaseCheckBox() :string {
		return stripslashes( $this->getTextOpt( 'text_pleasecheckbox' ) );
	}

	/**
	 * @return string
	 */
	public function getCanEmailVerifyCode() {
		return strtoupper( substr( $this->getCon()->getSiteInstallationId(), 10, 6 ) );
	}

	public function isEnabledCaptcha() :bool {
		return !$this->getOptions()->isOpt( 'enable_google_recaptcha_login', 'disabled' )
			   && $this->getCaptchaCfg()->ready;
	}

	public function getCaptchaCfg() :CaptchaConfigVO {
		$cfg = parent::getCaptchaCfg();
		$sStyle = $this->getOptions()->getOpt( 'enable_google_recaptcha_login' );
		if ( $sStyle !== 'default' && $this->isPremium() ) {
			$cfg->theme = $sStyle;
			$cfg->invisible = $cfg->theme == 'invisible';
		}
		return $cfg;
	}

	public function getMfaController() :Lib\TwoFactor\MfaController {
		if ( !isset( $this->mfaCon ) ) {
			$this->mfaCon = ( new Lib\TwoFactor\MfaController() )->setMod( $this );
		}
		return $this->mfaCon;
	}

	/**
	 * @param bool $bCan
	 * @return $this
	 */
	public function setIfCanSendEmail( $bCan ) {
		$this->getOptions()->setOpt( 'email_can_send_verified_at', $bCan ? Services::Request()->ts() : 0 );
		return $this;
	}

	public function setEnabled2FaEmail( bool $enable ) {
		$this->getOptions()->setOpt( 'enable_email_authentication', $enable ? 'Y' : 'N' );
	}

	public function setEnabled2FaGoogleAuthenticator( bool $enable ) {
		$this->getOptions()->setOpt( 'enable_google_authenticator', $enable ? 'Y' : 'N' );
	}

	public function getTextOptDefault( string $key ) :string {

		switch ( $key ) {
			case 'text_imahuman':
				$text = __( "I'm a human.", 'wp-simple-firewall' );
				break;

			case 'text_pleasecheckbox':
				$text = __( "Please check the box to show us you're a human.", 'wp-simple-firewall' );
				break;

			default:
				$text = parent::getTextOptDefault( $key );
				break;
		}
		return $text;
	}

	public function setEnabledAntiBotDetection( bool $enable ) {
		$this->getOptions()->setOpt( 'enable_antibot_check', $enable ? 'Y' : 'N' );
	}

	public function getScriptLocalisations() :array {
		$locals = parent::getScriptLocalisations();
		$locals[] = [
			'global-plugin',
			'icwp_wpsf_vars_lg',
			[
				'ajax' => [
					'profile_backup_codes_gen' => $this->getAjaxActionData( 'profile_backup_codes_gen' ),
					'profile_backup_codes_del' => $this->getAjaxActionData( 'profile_backup_codes_del' ),
				],
			]
		];
		return $locals;
	}

	public function getCustomScriptEnqueues() :array {
		$enqs = [];
		if ( is_admin() || is_network_admin() ) {
			$enqs[ Enqueue::CSS ] = [
				'wp-wp-jquery-ui-dialog'
			];
			$enqs[ Enqueue::JS ] = [
				'wp-jquery-ui-dialog'
			];
		}
		return $enqs;
	}
}