<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

class ModCon extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\ModCon {

	public const SLUG = 'login_protect';

	/**
	 * @var Lib\TwoFactor\MfaController
	 */
	private $mfaCon;

	/**
	 * @deprecated 19.1
	 */
	public function getMfaController() :Lib\TwoFactor\MfaController {
		return isset( self::con()->comps ) ? self::con()->comps->mfa :
			( $this->mfaCon ?? $this->mfaCon = new Lib\TwoFactor\MfaController() );
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

	/**
	 * @deprecated 19.1
	 */
	public function getTextImAHuman() :string {
		return \stripslashes( $this->getTextOpt( 'text_imahuman' ) );
	}

	/**
	 * @deprecated 19.1
	 */
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
	public function getDbH_Mfa() :\FernleafSystems\Wordpress\Plugin\Shield\DBs\Mfa\Ops\Handler {
		return self::con()->db_con->loadDbH( 'mfa' );
	}
}