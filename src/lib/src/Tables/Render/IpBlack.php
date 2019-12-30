<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render;

class IpBlack extends IpBase {

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_details( $aItem ) {
		return implode( '<br/>', [
			sprintf( '%s: %s', __( 'Blocked', 'wp-simple-firewall' ), $aItem[ 'blocked' ] ),
			sprintf( '%s: %s', __( 'Offenses', 'wp-simple-firewall' ), $aItem[ 'transgressions' ] ),
			sprintf( '%s: %s', __( 'Last Offense', 'wp-simple-firewall' ), $aItem[ 'last_trans_at' ] ),
		] );
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		return [
			'ip'         => __( 'IP Address', 'wp-simple-firewall' ),
			'details'    => __( 'Details' ),
			'expires_at' => __( 'Auto Expires' ),
		];
	}
}