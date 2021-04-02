<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf\Table;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Table\BaseFileEntryFormatter;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf\ResultItem;

class EntryFormatter extends BaseFileEntryFormatter {

	public function format() :array {
		/** @var ResultItem $item */
		$item = $this->getResultItem();

		$e = $this->getBaseData();

		$e[ 'status' ] = $item->is_checksumfail ? __( 'Modified', 'wp-simple-firewall' )
			: ( $item->is_missing ? __( 'Missing', 'wp-simple-firewall' ) : __( 'Unknown', 'wp-simple-firewall' ) );

		if ( $item->is_checksumfail ) {
			$e[ 'explanation' ] = [
				__( 'This file is an official WordPress core file.', 'wp-simple-firewall' ),
				__( "But, it appears to have been modified when compared to the official WordPress distribution.", 'wp-simple-firewall' )
				.' '.__( "This is not normal in the vast majority of cases.", 'wp-simple-firewall' ),
				__( "You may want to download it to ensure that the contents are what you expect.", 'wp-simple-firewall' )
				.' '.sprintf( __( "You can then click to '%s' or '%s' the file.", 'wp-simple-firewall' ),
					__( 'Ignore', 'wp-simple-firewall' ), __( 'Repair', 'wp-simple-firewall' ) ),
			];
		}
		elseif ( $item->is_missing ) {
			$e[ 'explanation' ] = [
				__( 'This file is an official WordPress core file.', 'wp-simple-firewall' ),
				__( "But, it appears to be missing from your site.", 'wp-simple-firewall' ),
				__( "You may want to check why this might be missing.", 'wp-simple-firewall' )
				.' '.sprintf( __( "You can then click to '%s' or '%s' the file.", 'wp-simple-firewall' ),
					__( 'Ignore', 'wp-simple-firewall' ), __( 'Repair', 'wp-simple-firewall' ) ),
			];
		}

		return $e;
	}

	/**
	 * @return string[]
	 */
	protected function getExplanation() :array {
		/** @var ResultItem $oIt */
		$oIt = $this->getResultItem();

		if ( $oIt->is_checksumfail ) {
			$expl = [
				__( 'This file is an official WordPress core file.', 'wp-simple-firewall' ),
				__( "But, it appears to have been modified when compared to the official WordPress distribution.", 'wp-simple-firewall' )
				.' '.__( "This is not normal in the vast majority of cases.", 'wp-simple-firewall' ),
				__( "You may want to download it to ensure that the contents are what you expect.", 'wp-simple-firewall' )
				.' '.sprintf( __( "You can then click to '%s' or '%s' the file.", 'wp-simple-firewall' ),
					__( 'Ignore', 'wp-simple-firewall' ), __( 'Repair', 'wp-simple-firewall' ) ),
			];
		}
		elseif ( $oIt->is_missing ) {
			$expl = [
				__( 'This file is an official WordPress core file.', 'wp-simple-firewall' ),
				__( "But, it appears to be missing from your site.", 'wp-simple-firewall' ),
				__( "You may want to check why this might be missing.", 'wp-simple-firewall' )
				.' '.sprintf( __( "You can then click to '%s' or '%s' the file.", 'wp-simple-firewall' ),
					__( 'Ignore', 'wp-simple-firewall' ), __( 'Repair', 'wp-simple-firewall' ) ),
			];
		}
		else {
			$expl = [];
		}

		return $expl;
	}

	/**
	 * @inheritDoc
	 */
	protected function getSupportedActions() :array {
		$extras = [ 'repair' ];

		/** @var ResultItem $oIt */
		$oIt = $this->getResultItem();
		if ( $oIt->is_checksumfail ) {
			$extras[] = 'download';
		}

		return array_merge( parent::getSupportedActions(), $extras );
	}
}