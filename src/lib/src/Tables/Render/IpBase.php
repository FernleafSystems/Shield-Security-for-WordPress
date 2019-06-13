<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render;

class IpBase extends Base {

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_ip( $aItem ) {
		return $this->getIpWhoisLookupLink( $aItem[ 'ip' ] )
			   .$this->buildActions( [ $this->getActionButton_Delete( $aItem[ 'id' ] ) ] );
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		return [
			'ip'             => __( 'IP Address' ),
			'label'          => __( 'Label', 'wp-simple-firewall' ),
			'transgressions' => __( 'Offenses', 'wp-simple-firewall' ),
			'list'           => __( 'List', 'wp-simple-firewall' ),
			'last_access_at' => __( 'Last Access', 'wp-simple-firewall' ),
			'created_at'     => __( 'Date' ),
		];
	}
}