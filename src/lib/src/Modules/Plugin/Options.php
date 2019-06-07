<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Options extends Base\Options {

	/**
	 * @return string
	 */
	public function getDbTable_GeoIp() {
		return $this->getCon()->prefixOption( $this->getDef( 'geoip_table_name' ) );
	}


	/**
	 * @return string
	 */
	public function getDbTable_Notes() {
		return $this->getCon()->prefixOption( $this->getDef( 'db_notes_name' ) );
	}

	/**
	 * @return string[]
	 */
	public function getDbColumns_GeoIp() {
		return $this->getDef( 'geoip_table_columns' );
	}
	/**
	 * @return string[]
	 */
	public function getDbColumns_Notes() {
		return $this->getDef( 'db_notes_table_columns' );
	}
}