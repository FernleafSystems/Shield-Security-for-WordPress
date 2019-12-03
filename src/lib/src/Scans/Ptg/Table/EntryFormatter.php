<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg\Table;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Table\BaseFileEntryFormatter;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg\ResultItem;

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

		$aE[ 'status' ] = $oIt->is_different ? __( 'Modified', 'wp-simple-firewall' )
			: ( $oIt->is_missing ? __( 'Missing', 'wp-simple-firewall' ) : __( 'Unrecognised', 'wp-simple-firewall' ) );

		if ( $oIt->is_different ) {
			$aE[ 'explanation' ] = [
				__( "This file appears to have been modified from its original content.", 'wp-simple-firewall' )
				.' '.__( "This may be okay if you're editing files directly on your site.", 'wp-simple-firewall' ),
				__( "You may want to download it to ensure that the contents are as you expect.", 'wp-simple-firewall' )
				.' '.sprintf( __( "You can then click to '%s' the file.", 'wp-simple-firewall' ),
					__( 'Ignore', 'wp-simple-firewall' ) ),
			];
		}
		elseif ( $oIt->is_missing ) {
			$aE[ 'explanation' ] = [
				__( "This file appears to have been removed from your site.", 'wp-simple-firewall' )
				.' '.__( "This may be okay if you're editing files directly on your site.", 'wp-simple-firewall' ),
				__( "If you're unsure, you should check whether this is okay.", 'wp-simple-firewall' )
				.' '.sprintf( __( "You can then click to '%s' the file.", 'wp-simple-firewall' ),
					__( 'Ignore', 'wp-simple-firewall' ) ),
			];
		}
		else {
			$aE[ 'explanation' ] = [
				__( "This file appears to have been added to your site.", 'wp-simple-firewall' ),
				__( "This is not normal in the vast majority of cases.", 'wp-simple-firewall' ),
				__( "You may want to download it to ensure that the contents are what you expect.", 'wp-simple-firewall' )
				.' '.sprintf( __( "You can then click to '%s' or '%s' the file.", 'wp-simple-firewall' ),
					__( 'Ignore', 'wp-simple-firewall' ), __( 'Delete', 'wp-simple-firewall' ) ),
			];
		}
		return $aE;
	}
}