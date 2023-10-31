<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MfaEmailSendVerification;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;

class ModCon extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\ModCon {

	public const SLUG = 'login_protect';

	/**
	 * @var Lib\TwoFactor\MfaController
	 */
	private $mfaCon;

	public function getDbH_Mfa() :DB\Mfa\Ops\Handler {
		return self::con()->db_con->loadDbH( 'mfa' );
	}

	public function getMfaController() :Lib\TwoFactor\MfaController {
		return $this->mfaCon ?? $this->mfaCon = new Lib\TwoFactor\MfaController();
	}

	public function preProcessOptions() {
		/** @var Options $opts */
		$opts = $this->opts();
		if ( $opts->isOptChanged( 'enable_email_authentication' ) ) {
			$opts->setOpt( 'email_can_send_verified_at', 0 );
			try {
				self::con()->action_router->action( MfaEmailSendVerification::class );
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
		$opts->setOpt( 'antibot_form_ids', \array_values( \array_unique( $IDs ) ) );

		$this->cleanLoginUrlPath();

		if ( $opts->isEnabledAntiBot() ) {
			$opts->setOpt( 'enable_login_gasp_check', 'N' );
		}

		$opts->setOpt( 'two_factor_auth_user_roles', $opts->getEmail2FaRoles() );

		$redirect = \preg_replace( '#[^\da-z_\-/.]#i', '', (string)$opts->getOpt( 'rename_wplogin_redirect' ) );
		if ( !empty( $redirect ) ) {

			$redirect = \preg_replace( '#^http(s)?//.*/#iU', '', $redirect );
			if ( !empty( $redirect ) ) {
				$redirect = '/'.\ltrim( $redirect, '/' );
			}
		}
		$opts->setOpt( 'rename_wplogin_redirect', $redirect );

		if ( empty( $opts->getOpt( 'mfa_user_setup_pages' ) ) ) {
			$opts->setOpt( 'mfa_user_setup_pages', [ 'profile' ] );
		}
	}

	private function cleanLoginUrlPath() {
		/** @var Options $opts */
		$opts = $this->opts();
		$path = $opts->getCustomLoginPath();
		if ( !empty( $path ) ) {
			$path = \preg_replace( '#[^\da-zA-Z-]#', '', \trim( $path, '/' ) );
			$this->opts()->setOpt( 'rename_wplogin_path', $path );
		}
	}

	public function getGaspKey() :string {
		/** @var Options $opts */
		$opts = $this->opts();
		$key = $opts->getOpt( 'gasp_key' );
		if ( empty( $key ) ) {
			$key = \uniqid();
			$opts->setOpt( 'gasp_key', $key );
		}
		return self::con()->prefix( $key );
	}

	public function getTextImAHuman() :string {
		return stripslashes( $this->getTextOpt( 'text_imahuman' ) );
	}

	public function getTextPleaseCheckBox() :string {
		return stripslashes( $this->getTextOpt( 'text_pleasecheckbox' ) );
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

	/**
	 * @deprecated 18.5
	 */
	public function ensureCorrectCaptchaConfig() {
	}

	/**
	 * @deprecated 18.5
	 */
	public function isEnabledCaptcha() :bool {
		return false;
	}

	/**
	 * @deprecated 18.5
	 */
	public function getCaptchaCfg() {
		return parent::getCaptchaCfg();
	}
}