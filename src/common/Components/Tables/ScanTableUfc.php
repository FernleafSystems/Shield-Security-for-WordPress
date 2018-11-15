<?php

if ( !class_exists( 'ICWP_BaseTable' ) ) {
	require_once( __DIR__.'/ScanTableBase.php' );
}

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class ScanTableUfc extends ScanTableBase {

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_actions( $aItem ) {
		return $this->getActionButton_Delete( $aItem[ 'id' ] )
			   .$this->getActionButton_Ignore( $aItem[ 'id' ] );
	}
}