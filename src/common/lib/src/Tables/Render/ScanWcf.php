<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class ScanWcf extends ScanBase {

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_actions( $aItem ) {
		return $this->getActionButton_Repair( $aItem[ 'id' ] )
			   .$this->getActionButton_Ignore( $aItem[ 'id' ] );
	}

	/**
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'repair' => 'Repair',
			'ignore' => 'Ignore',
		);
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		return array_merge(
			[ 'cb' => '&nbsp;' ],
			parent::get_columns()
		);
	}
}