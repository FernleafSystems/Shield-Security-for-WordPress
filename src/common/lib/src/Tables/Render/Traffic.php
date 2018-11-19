<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render;

class Traffic extends Base {

	/**
	 * @return array
	 */
	public function get_columns() {
		return array(
			'path'         => 'Page',
			'visitor'      => 'Visitor Details',
			'request_info' => 'Response Info',
			'created_at'   => 'Date',
		);
	}
}