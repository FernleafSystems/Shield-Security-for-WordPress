<?php
if ( !class_exists('ICWP_Stats_WPSF') ):

class ICWP_Stats_WPSF {

	const Stats_Key = 'icwp_wpsf_stats';

	/**
	 * @var array
	 */
	private static $aStats;

	public function __construct() { }

	/**
	 * @return array
	 */
	public static function GetStatsData() {
		self::LoadStats();
		return self::$aStats;
	}

	/**
	 */
	protected static function LoadStats() {
		if ( isset( self::$aStats ) ) {
			return;
		}
		self::$aStats = get_option( self::Stats_Key, array() );
		if ( !is_array( self::$aStats ) ) {
			self::$aStats = array();
		}
	}

	/**
	 */
	public static function ClearStats() {
		if ( !empty(self::$aStats) ) {
			add_filter( 'icwp_wpsf_bypass_permission_to_manage', '__return_true' );
			delete_option( self::Stats_Key );
			remove_filter( 'icwp_wpsf_bypass_permission_to_manage', '__return_true' );
		}
	}
}

endif;