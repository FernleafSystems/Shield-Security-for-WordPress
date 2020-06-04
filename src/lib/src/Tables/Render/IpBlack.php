<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render;

class IpBlack extends IpBase {

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_details( $aItem ) {
		$bAutoBlock = $aItem[ 'list' ] === \ICWP_WPSF_FeatureHandler_Ips::LIST_AUTO_BLACK;
		return implode( '<br/>', [
			sprintf( '%s: %s', __( 'Blocked', 'wp-simple-firewall' ), $aItem[ 'blocked' ] ),
			sprintf( '%s / %s',
				$aItem[ 'is_range' ] ? __( 'IP Range', 'wp-simple-firewall' ) : __( 'Single IP', 'wp-simple-firewall' ),
				$bAutoBlock ? __( 'Automatic', 'wp-simple-firewall' ) : __( 'Manual', 'wp-simple-firewall' )
			),
			sprintf( '%s - %s',
				sprintf( _n( '%s Offense', '%s Offenses', $aItem[ 'transgressions' ], 'wp-simple-firewall' ), $aItem[ 'transgressions' ] ),
				sprintf( '%s: %s', __( 'Last Access', 'wp-simple-firewall' ), $aItem[ 'last_trans_at' ] )
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