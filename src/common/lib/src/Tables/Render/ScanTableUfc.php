<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render;

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