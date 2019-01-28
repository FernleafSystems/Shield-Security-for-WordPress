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
			'created_at' => 'Added',
		);
	}
}