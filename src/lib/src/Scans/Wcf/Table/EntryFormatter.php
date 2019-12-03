<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf\Table;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Table\BaseFileEntryFormatter;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf\ResultItem;

class EntryFormatter extends BaseFileEntryFormatter {

	/**
	 * @return array
	 */
	public function format() {
		/** @var ResultItem $oIt */
		$oIt = $this->getResultItem();

		$aE = $this->getBaseData();
		if ( $oIt->is_missing ) {
			$aE[ 'href_download' ] = false;
		}
		$aE[ 'status' ] = $oIt->is_checksumfail ? __( 'Modified', 'wp-simple-firewall' )
			: ( $oIt->is_missing ? __( 'Missing', 'wp-simple-firewall' ) : __( 'Unknown', 'wp-simple-firewall' ) );

		if ( $oIt->is_checksumfail ) {
			$aE[ 'explanation' ] = [
				__( 'This file is an official WordPress core file.', 'wp-simple-firewall' ),
				__( "But, it appears to have been modified when compared to the official WordPress distribution.", 'wp-simple-firewall' )
				.__( "This is not normal in the vast majority of cases.", 'wp-simple-firewall' ),
				__( "You may want to download it to ensure that the contents are what you expect.", 'wp-simple-firewall' )
				.' '.sprintf( __( "You can then click to '%s' or '%s' the file.", 'wp-simple-firewall' ),
					__( 'Ignore', 'wp-simple-firewall' ), __( 'Repair', 'wp-simple-firewall' ) ),
			];
		}
		elseif ( $oIt->is_missing ) {
			$aE[ 'explanation' ] = [
				__( 'This file is an official WordPress core file.', 'wp-simple-firewall' ),
				__( "But, it appears to be missing from your site.", 'wp-simple-firewall' ),
				__( "You may want to check why this might be missing.", 'wp-simple-firewall' )
				.' '.sprintf( __( "You can then click to '%s' or '%s' the file.", 'wp-simple-firewall' ),
					__( 'Ignore', 'wp-simple-firewall' ), __( 'Repair', 'wp-simple-firewall' ) ),
			];
		}

		return $aE;
	}
}