<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render;

class AuditTrail extends Base {

	/**
	 * @param int $nId
	 * @return string
	 */
	protected function getActionButton_AddParam( $nId ) {
		return $this->buildActionButton_Custom(
			__( 'Whitelist Param', 'wp-simple-firewall' ),
			[ 'custom-action' ],
			[
				'rid'           => $nId,
				'custom-action' => 'item_addparamwhite'
			],
			__( 'Add Parameter To Whitelist', 'wp-simple-firewall' )
		);
	}

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_message( $aItem ) {
		return sprintf( '<textarea readonly rows="%s">%s</textarea>',
			max( 2, (int)( strlen( $aItem[ 'message' ] )/50 ) ),
			sanitize_textarea_field( $aItem[ 'message' ] )
		);
	}

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_details( $aItem ) {
		$sContent = sprintf( '%s<br />%s%s',
			$aItem[ 'wp_username' ],
			$this->getIpWhoisLookupLink( $aItem[ 'ip' ] ),
			$aItem[ 'your_ip' ]
		);
		if ( isset( $aItem[ 'meta' ][ 'param' ] ) ) {
			$sContent .= $this->buildActions( $this->getActionButton_AddParam( $aItem[ 'id' ] ) );
		}
		return $sContent;
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		return [
			'details'    => 'Details',
			'message'    => 'Message',
			//			'event'      => 'Event',
			'created_at' => 'Date',
		];
	}
}