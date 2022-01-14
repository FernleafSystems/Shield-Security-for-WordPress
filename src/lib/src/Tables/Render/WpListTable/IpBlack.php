<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render\WpListTable;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;

class IpBlack extends IpBase {

	/**
	 * @param array $item
	 * @return string
	 */
	public function column_details( $item ) {
		$autoBlock = $item[ 'list' ] === ModCon::LIST_AUTO_BLACK;
		return implode( '<br/>', [
			sprintf( '%s: %s', __( 'Blocked', 'wp-simple-firewall' ), $item[ 'blocked' ] ),
			sprintf( '%s / %s',
				$item[ 'is_range' ] ? __( 'IP Range', 'wp-simple-firewall' ) : __( 'Single IP', 'wp-simple-firewall' ),
				$autoBlock ? __( 'Automatic', 'wp-simple-firewall' ) : __( 'Manual', 'wp-simple-firewall' )
			),
			sprintf( '%s - %s',
				sprintf( _n( '%s Offense', '%s Offenses', $item[ 'transgressions' ], 'wp-simple-firewall' ), $item[ 'transgressions' ] ),
				sprintf( '%s: %s', __( 'Last Access', 'wp-simple-firewall' ), $item[ 'last_trans_at' ] )
			),
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