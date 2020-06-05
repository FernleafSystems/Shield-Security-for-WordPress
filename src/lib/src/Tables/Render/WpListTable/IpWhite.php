<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render\WpListTable;

class IpWhite extends IpBase {

	/**
	 * @return array
	 */
	public function get_columns() {
		return [
			'ip'         => __( 'IP Address', 'wp-simple-firewall' ),
			'label'      => __( 'Label', 'wp-simple-firewall' ),
			'created_at' => __( 'Added', 'wp-simple-firewall' ),
		];
	}
}