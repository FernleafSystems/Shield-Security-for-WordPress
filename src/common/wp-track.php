<?php
if ( class_exists( 'ICWP_WPSF_WpTrack', false ) ) {
	return;
}

class ICWP_WPSF_WpTrack extends ICWP_WPSF_Foundation {

	/**
	 * @var ICWP_WPSF_WpTrack
	 */
	protected static $oInstance = null;

	/**
	 * @var array
	 */
	protected $aFiredWpActions = array();

	private function __construct() {
		$aActions = array( 'plugins_loaded', 'init', 'admin_init', 'wp_loaded', 'wp', 'wp_head', 'shutdown' );
		foreach ( $aActions as $sAction ) {
			add_action( $sAction, array( $this, 'trackAction' ), 0 );
		}
	}

	/**
	 * @return ICWP_WPSF_WpTrack
	 */
	public static function GetInstance() {
		if ( is_null( self::$oInstance ) ) {
			self::$oInstance = new self();
		}
		return self::$oInstance;
	}

	/**
	 * Pass null to get the state of all tracked actions as an assoc array
	 * @param string|null $sAction
	 * @return array|bool
	 */
	public function getWpActionHasFired( $sAction = null ) {
		return ( empty( $sAction ) ? $this->aFiredWpActions : isset( $this->aFiredWpActions[ $sAction ] ) );
	}

	/**
	 * @param string $sAction
	 * @return $this
	 */
	public function setWpActionHasFired( $sAction ) {
		if ( !isset( $this->aFiredWpActions ) || !is_array( $this->aFiredWpActions ) ) {
			$this->aFiredWpActions = array();
		}
		$this->aFiredWpActions[ $sAction ] = microtime();
		return $this;
	}

	/**
	 * @return $this
	 */
	public function trackAction() {
		return $this->setWpActionHasFired( current_filter() );
	}
}