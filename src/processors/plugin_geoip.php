<?php

use FernleafSystems\Wordpress\Plugin\Shield\Databases\GeoIp;

/**
 * Class ICWP_WPSF_Processor_Plugin_Geoip
 * @deprecated 7.5
 */
class ICWP_WPSF_Processor_Plugin_Geoip extends ICWP_WPSF_BaseDbProcessor {

	const DAYS_TO_KEEP = 30;

	/**
	 * @param ICWP_WPSF_FeatureHandler_Plugin $oModCon
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_Plugin $oModCon ) {
		parent::__construct( $oModCon, $oModCon->getDef( 'geoip_table_name' ) );
	}

	public function run() {
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

	/**
	 * @return int
	 */
	protected function getAutoExpirePeriod() {
		return DAY_IN_SECONDS*self::DAYS_TO_KEEP;
	}
}