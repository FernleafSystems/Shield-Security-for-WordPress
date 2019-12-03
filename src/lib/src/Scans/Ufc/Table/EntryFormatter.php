<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc\Table;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Table\BaseFileEntryFormatter;

class EntryFormatter extends BaseFileEntryFormatter {

	/**
	 * @return array
	 */
	public function format() {
		$aE = $this->getBaseData();
		$aE[ 'status' ] = __( 'Unrecognised', 'wp-simple-firewall' );
		$aE[ 'explanation' ] = [
			__( 'This file was discovered within one of your core WordPress directories.', 'wp-simple-firewall' ),
			__( "But it isn't part of the official WordPress distribution for this version.", 'wp-simple-firewall' ),
			__( "You may want to download it to ensure that the contents are what you expect.", 'wp-simple-firewall' )
			.' '.sprintf( __( "You can then click to '%s' or '%s' the file.", 'wp-simple-firewall' ),
				__( 'Ignore', 'wp-simple-firewall' ), __( 'Delete', 'wp-simple-firewall' ) ),
		];
		return $aE;
	}
}