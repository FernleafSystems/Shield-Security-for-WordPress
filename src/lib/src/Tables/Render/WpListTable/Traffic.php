<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render\WpListTable;

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