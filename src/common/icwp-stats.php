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
	 * @param $sKey
	 */
	public static function DoStatIncrement( $sKey ) {
		self::LoadStats();
		self::$aStats[$sKey] = isset( self::$aStats[$sKey] )? self::$aStats[$sKey] + 1 : 1;
		self::SaveStats();
	}

	/**
	 * The provided key is an array, and the value to count is the key of the array that should be incremented.
	 *
	 * @param $sKey
	 * @param $sValueToCount
	 */
	public static function DoStatIncrementKeyValue( $sKey, $sValueToCount ) {
		self::LoadStats();
		if ( !isset( self::$aStats[$sKey] ) || !is_array( self::$aStats[$sKey] ) ) {
			self::$aStats[$sKey] = array();
		}
		self::$aStats[$sKey][$sValueToCount] = isset( self::$aStats[$sKey][$sValueToCount] )? self::$aStats[$sKey][$sValueToCount] + 1 : 1;
		self::SaveStats();
	}

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
		self::$aStats = get_option( self::Stats_Key );
		if ( !is_array( self::$aStats ) ) {
			self::$aStats = array();
			self::SaveStats();
		}
	}

	/**
	 */
	public static function SaveStats() {
		if ( !empty(self::$aStats) ) {
			update_option( self::Stats_Key, self::$aStats );
		}
	}
}

endif;