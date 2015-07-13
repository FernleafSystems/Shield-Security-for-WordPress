<?php
/**
 * Copyright (c) 2015 iControlWP <support@icontrolwp.com>
 * All rights reserved.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

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