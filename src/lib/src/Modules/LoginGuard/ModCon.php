<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MfaEmailSendVerification;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Captcha\CaptchaConfigVO;

class ModCon extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\ModCon {

	public const SLUG = 'login_protect';

	/**
	 * @var Lib\TwoFactor\MfaController
	 */
	private $mfaCon;

	public function getMfaController() :Lib\TwoFactor\MfaController {
		return $this->mfaCon ?? $this->mfaCon = new Lib\TwoFactor\MfaController();
	}

	protected function preProcessOptions() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		if ( $opts->isOptChanged( 'enable_email_authentication' ) ) {
			$opts->setOpt( 'email_can_send_verified_at', 0 );
			try {
				$this->con()->action_router->action( MfaEmailSendVerification::class );
			}
			catch ( ActionException $e ) {
			}
		}

		$IDs = $opts->getOpt( 'antibot_form_ids', [] );
		foreach ( $IDs as $key => $id ) {
			$id = \trim( strip_tags( $id ) );
			if ( empty( $id ) ) {
				unset( $IDs[ $key ] );
			}
			else {
				$IDs[ $key ] = $id;
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

		$redirect = preg_replace( '#[^\da-z_\-/.]#i', '', (string)$opts->getOpt( 'rename_wplogin_redirect' ) );
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
		if ( $this->con()->isPremiumActive() ) {
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

	private function cleanLoginUrlPath() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$path = $opts->getCustomLoginPath();
		if ( !empty( $path ) ) {
			$path = preg_replace( '#[^\da-zA-Z-]#', '', trim( $path, '/' ) );
			$this->getOptions()->setOpt( 'rename_wplogin_path', $path );
		}
	}

	public function getGaspKey() :string {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$key = $opts->getOpt( 'gasp_key' );
		if ( empty( $key ) ) {
			$key = uniqid();
			$opts->setOpt( 'gasp_key', $key );
		}
		return $this->con()->prefix( $key );
	}

	public function getTextImAHuman() :string {
		return stripslashes( $this->getTextOpt( 'text_imahuman' ) );
	}

	public function getTextPleaseCheckBox() :string {
		return stripslashes( $this->getTextOpt( 'text_pleasecheckbox' ) );
	}

	public function isEnabledCaptcha() :bool {
		return !$this->getOptions()->isOpt( 'enable_google_recaptcha_login', 'disabled' )
			   && $this->getCaptchaCfg()->ready;
	}

	public function getCaptchaCfg() :CaptchaConfigVO {
		$cfg = parent::getCaptchaCfg();
		$style = $this->getOptions()->getOpt( 'enable_google_recaptcha_login' );
		if ( $style !== 'default' && $this->con()->isPremiumActive() ) {
			$cfg->theme = $style;
			$cfg->invisible = $cfg->theme == 'invisible';
		}
		return $cfg;
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
}