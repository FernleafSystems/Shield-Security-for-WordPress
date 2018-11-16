<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class ScanTablePtg extends ScanTableBase {

	/**
	 * @return array
	 */
	public function get_columns() {
		return array(
			'path'       => 'File',
			'status'     => 'Status',
			'created_at' => 'Discovered',
		);
	}
}