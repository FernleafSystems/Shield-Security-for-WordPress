<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg\Table;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Table\BaseFileEntryFormatter;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;
use FernleafSystems\Wordpress\Services\Services;

class EntryFormatter extends BaseFileEntryFormatter {

	/**
	 * @return array
	 */
	public function format() {
		/** @var Ptg\ResultItem $oIt */
		$oIt = $this->getResultItem();

		$aE = $this->getBaseData();
		$aE[ 'status' ] = $oIt->is_different ? __( 'Modified', 'wp-simple-firewall' )
			: ( $oIt->is_missing ? __( 'Missing', 'wp-simple-firewall' ) : __( 'Unrecognised', 'wp-simple-firewall' ) );
		return $aE;
	}

	/**
	 * @inheritDoc
	 */
	protected function getActionDefinitions() {
		/** @var Ptg\ResultItem $oIt */
		$oIt = $this->getResultItem();
		$sAssetType = ( $oIt->context == 'plugins' ? __( 'Plugin', 'wp-simple-firewall' ) : __( 'Theme', 'wp-simple-firewall' ) );
		return array_merge(
			parent::getActionDefinitions(),
			[
				'accept_asset' => [
					'text'    => sprintf( __( 'Accept %s', 'wp-simple-firewall' ), $sAssetType ),
					'title'   => sprintf( __( 'Accept all current scan results for this %s.' ), $sAssetType ),
					'classes' => [ 'accept' ],
					'data'    => [],
				],
				'reinstall'    => [
					'text'    => sprintf( __( 'Re-Install %s', 'wp-simple-firewall' ), $sAssetType ),
					'classes' => [ 'reinstall' ],
					'data'    => []
				],
			]
		);
	}

	/**
	 * @return string[]
	 */
	protected function getExplanation() {
		/** @var Ptg\ResultItem $oIt */
		$oIt = $this->getResultItem();

		if ( $oIt->is_different ) {
			$aExpl = [
				__( "This file appears to have been modified from its original content.", 'wp-simple-firewall' )
				.' '.__( "This may be okay if you're editing files directly on your site.", 'wp-simple-firewall' ),
				__( "You may want to download it to ensure that the contents are as you expect.", 'wp-simple-firewall' )
				.' '.sprintf( __( "You can then click to '%s' the file.", 'wp-simple-firewall' ),
					__( 'Ignore', 'wp-simple-firewall' ) ),
			];
		}
		elseif ( $oIt->is_missing ) {
			$aExpl = [
				__( "This file appears to have been removed from your site.", 'wp-simple-firewall' )
				.' '.__( "This may be okay if you're editing files directly on your site.", 'wp-simple-firewall' ),
				__( "If you're unsure, you should check whether this is okay.", 'wp-simple-firewall' )
				.' '.sprintf( __( "You can then click to '%s' the file.", 'wp-simple-firewall' ),
					__( 'Ignore', 'wp-simple-firewall' ) ),
			];
		}
		else {
			$aExpl = [
				__( "This file appears to have been added to your site.", 'wp-simple-firewall' ),
				__( "This is not normal in the vast majority of cases.", 'wp-simple-firewall' ),
				__( "You may want to download it to ensure that the contents are what you expect.", 'wp-simple-firewall' )
				.' '.sprintf( __( "You can then click to '%s' or '%s' the file.", 'wp-simple-firewall' ),
					__( 'Ignore', 'wp-simple-firewall' ), __( 'Delete', 'wp-simple-firewall' ) ),
			];
		}

		return $aExpl;
	}

	/**
	 * @inheritDoc
	 */
	protected function getSupportedActions() {
		/** @var Ptg\ResultItem $oIt */
		$oIt = $this->getResultItem();

		$aExtras = [
			'accept_asset'
		];

		if ( $oIt->context == 'plugins' ) {
			$oAsset = Services::WpPlugins()->getPluginAsVo( $oIt->slug );
		}
		else {
			$oAsset = Services::WpThemes()->getThemeAsVo( $oIt->slug );
		}

		$bCanRepair = ( new Ptg\Repair() )->canRepair( $oIt );
		$bHasUpdate = $oAsset->hasUpdate();

		if ( $bHasUpdate ) {
			$aExtras[] = 'update';
		}

		if ( $oIt->is_unrecognised ) {
			$aExtras[] = 'delete';
		}
		elseif ( $bCanRepair ) {
			$aExtras[] = 'repair';
		}

		if ( $bCanRepair && !$bHasUpdate ) {
			$aExtras[] = 'reinstall';
		}

		if ( !$oIt->is_missing ) {
			$aExtras[] = 'download';
		}

		return array_merge( parent::getSupportedActions(), $aExtras );
	}
}