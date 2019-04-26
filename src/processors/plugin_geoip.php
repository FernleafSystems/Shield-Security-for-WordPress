<?php

use FernleafSystems\Wordpress\Plugin\Shield\Databases\GeoIp;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_Plugin_Geoip extends ICWP_WPSF_BaseDbProcessor {

	/**
	 * @param ICWP_WPSF_FeatureHandler_Plugin $oModCon
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_Plugin $oModCon ) {
		parent::__construct( $oModCon, $oModCon->getDef( 'geoip_table_name' ) );
	}

	public function run() {
	}

	/**
	 * @param string $sIp
	 * @return GeoIp\EntryVO|null
	 */
	public function lookupIp( $sIp ) {
		/** @var GeoIp\Handler $oDbH */
		$oDbH = $this->getDbHandler();
		/** @var GeoIp\Select $oSel */
		$oSel = $oDbH->getQuerySelector();
		$oIp = $oSel->byIp( $sIp );

		/**
		 * We look up the IP and if the request fails, we store it any way so that we don't repeatedly
		 * bombard the API. The address will eventually be expired over time and lookup will process again at
		 * a later date as required
		 */
		if ( empty( $oIp ) ) {
			$oIp = new GeoIp\EntryVO();
			$oIp->ip = $sIp;
			$aIpData = $this->redirectliIpLookup( $sIp );
			if ( !empty( $aIpData ) && is_array( $aIpData ) ) {
				$oIp->meta = array_intersect_key(
					$aIpData,
					[
						'countryCode' => '',
						'timeZone'    => '',
						'latitude'    => '',
						'longitude'   => '',
					]
				);
			}
			/** @var GeoIp\Insert $oIsrt */
			$oIsrt = $oDbH->getQueryInserter();
			$oIsrt->insert( $oIp );
		}
		return $oIp;
	}

	/**
	 * @param string $sIp
	 * @return bool
	 */
	private function redirectliIpLookup( $sIp ) {
		$oHttp = Services::HttpRequest();
		return @json_decode( $oHttp->getContent( $this->getMod()->getDef( 'url_geoip' ).$sIp ), true );
	}

	/**
	 * @return string
	 */
	public function getCreateTableSql() {
		return "CREATE TABLE %s (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			ip varbinary(16) DEFAULT NULL COMMENT 'IP Address',
			meta TEXT,
			created_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0,
 			PRIMARY KEY  (id)
		) %s;";
	}

	/**
	 * @return array
	 */
	protected function getTableColumnsByDefinition() {
		$aDef = $this->getMod()->getDef( 'geoip_table_columns' );
		return is_array( $aDef ) ? $aDef : [];
	}

	/**
	 * @return GeoIp\Handler
	 */
	protected function createDbHandler() {
		return new GeoIp\Handler();
	}
}