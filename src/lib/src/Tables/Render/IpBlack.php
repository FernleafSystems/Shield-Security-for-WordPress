<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render;

class IpBlack extends IpBase {

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_details( $aItem ) {
		$aDetails = array(
			sprintf( '%s: %s', _wpsf__( 'Blocked' ), $aItem[ 'blocked' ] ),
			sprintf( '%s: %s', _wpsf__( 'Transgressions' ), $aItem[ 'transgressions' ] ),
			sprintf( '%s: %s', _wpsf__( 'Last Transgression' ), $aItem[ 'last_trans_at' ] ),
			sprintf( '%s: %s', _wpsf__( 'IP' ), $this->getIpWhoisLookupLink( $aItem[ 'ip' ] ) ),
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