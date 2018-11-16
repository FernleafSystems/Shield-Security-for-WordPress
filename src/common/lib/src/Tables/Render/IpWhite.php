<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render;

class IpWhite extends IpBase {

	/**
	 * @return array
	 */
	public function get_columns() {
		return array(
			'ip'         => 'IP Address',
			'label'      => 'Label',
			//			'last_access_at' => 'Last Access',
			'created_at' => 'Added',
			'actions'    => $this->getColumnHeader_Actions(),
		);
	}
}