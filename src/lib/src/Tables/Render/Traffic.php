<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render;

class Traffic extends Base {

	/**
	 * @return array
	 */
	public function get_columns() {
		return [
			'path'         => __( 'Page' ),
			'visitor'      => __( 'Details' ),
			'request_info' => __( 'Response', 'wp-simple-firewall' ),
			'created_at'   => __( 'Date' ),
		];
	}
}