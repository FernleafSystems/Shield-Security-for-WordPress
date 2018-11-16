<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render;

class IpBlackTable extends IpBaseTable {

	/**
	 * @return array
	 */
	public function get_columns() {
		return array(
			'ip'             => 'IP Address',
			'transgressions' => 'Transgressions',
			'last_access_at' => 'Last Access',
			'actions'        => $this->getColumnHeader_Actions(),
		);
	}
}