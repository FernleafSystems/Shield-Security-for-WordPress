<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class ScanUfc extends ScanBase {

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_path( $aItem ) {
		return parent::column_path( $aItem )
			   .$this->buildActions(
				[
					$this->getActionButton_Ignore( $aItem[ 'id' ] ),
					$this->getActionButton_Delete( $aItem[ 'id' ] ),
				]
			);
	}

	/**
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'delete' => 'Delete',
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