<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg\Table;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Table\BaseFileEntryFormatter;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg\ResultItem;
use FernleafSystems\Wordpress\Services\Core\VOs;
use FernleafSystems\Wordpress\Services\Services;

class EntryFormatter extends BaseFileEntryFormatter {

	/**
	 * @return array
	 */
	public function format() {
		/** @var ResultItem $oIt */
		$oIt = $this->getResultItem();

		$aE = $this->getBaseData();

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

	/**
	 * @inheritDoc
	 */
	protected function getSupportedActions() {
		/** @var ResultItem $oIt */
		$oIt = $this->getResultItem();

		$aExtras = [
			'ignore_asset'
		];
		if ( !$oIt->is_missing ) {
			$aExtras[] = 'download';
		}

		if ( $oIt->is_unrecognised ) {
			$aExtras[] = 'delete';
		}
		else {
			if ( $oIt->context == 'plugins' ) {
				$oAsset = Services::WpPlugins()->getPluginAsVo( $oIt->slug );
				$bCanRepair = ( $oAsset instanceof VOs\WpPluginVo && $oAsset->isWpOrg() && $oAsset->svn_uses_tags );
				$bHasUpdate = $oAsset->hasUpdate();
			}
			else {
				$oAsset = Services::WpThemes()->getThemeAsVo( $oIt->slug );
				$bCanRepair = ( $oAsset instanceof VOs\WpThemeVo && $oAsset->isWpOrg() );
				$bHasUpdate = $oAsset->hasUpdate();
			}

			if ( $bHasUpdate ) {
				$aExtras[] = 'update';
			}
			if ( $bCanRepair ) {
				$aExtras[] = 'repair';
			}
			if ( $bCanRepair && !$bHasUpdate ) {
				$aExtras[] = 'repair_asset';
			}
		}

		return array_merge( parent::getSupportedActions(), $aExtras );
	}
}