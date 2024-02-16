<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MfaEmailSendVerification;

class ModCon extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\ModCon {

	public const SLUG = 'login_protect';

	/**
	 * @var Lib\TwoFactor\MfaController
	 */
	private $mfaCon;

	public function getMfaController() :Lib\TwoFactor\MfaController {
		return $this->mfaCon ?? $this->mfaCon = new Lib\TwoFactor\MfaController();
	}

	public function onConfigChanged() :void {
		/** @var Options $opts */
		$opts = $this->opts();
		if ( $opts->isOptChanged( 'enable_email_authentication' ) ) {
			try {
				self::con()->action_router->action( MfaEmailSendVerification::class );
			}
			catch ( \Exception $e ) {
			}
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
		return \stripslashes( $this->getTextOpt( 'text_imahuman' ) );
	}

	public function getTextPleaseCheckBox() :string {
		return \stripslashes( $this->getTextOpt( 'text_pleasecheckbox' ) );
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
	 * @deprecated 19.1
	 */
	public function getDbH_Mfa() :FernleafSystems\Wordpress\Plugin\Shield\DBs\Mfa\Ops\Handler {
		return self::con()->db_con->loadDbH( 'mfa' );
	}
}