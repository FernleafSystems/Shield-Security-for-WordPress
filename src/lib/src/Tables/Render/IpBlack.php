<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render;

class IpBlack extends IpBase {

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_details( $aItem ) {
		$aDetails = array(
			sprintf( '%s: %s', __( 'Blocked', 'wp-simple-firewall' ), $aItem[ 'blocked' ] ),
			sprintf( '%s: %s', __( 'Transgressions', 'wp-simple-firewall' ), $aItem[ 'transgressions' ] ),
			sprintf( '%s: %s', __( 'Last Transgression', 'wp-simple-firewall' ), $aItem[ 'last_trans_at' ] ),
			sprintf( '%s: %s', __( 'IP', 'wp-simple-firewall' ), $this->getIpWhoisLookupLink( $aItem[ 'ip' ] ) ),
			$this->buildActions( [ $this->getActionButton_Delete( $aItem[ 'id' ] ) ] )
		);
		return implode( '<br/>', $aDetails );
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		return array(
			'details'    => 'Details',
			'expires_at' => 'Auto Expires',
		);
	}
}