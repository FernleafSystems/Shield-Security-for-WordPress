<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg\Table;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Table\BaseFileEntryFormatter;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;
use FernleafSystems\Wordpress\Services\Services;

class EntryFormatter extends BaseFileEntryFormatter {

	protected function getBaseData() :array {
		$data = parent::getBaseData();
		/** @var Ptg\ResultItem $item */
		$item = $this->getResultItem();

		if ( $item->context == 'plugins' ) {
			$asset = Services::WpPlugins()->getPluginAsVo( $item->slug );
			if ( !empty( $asset ) ) {
				$data[ 'path_details' ][] = sprintf( '%s: %s v%s',
					__( 'Plugin', 'wp-simple-firewall' ), $asset->Name, $asset->version );
			}
		}
		else {
			$asset = Services::WpThemes()->getThemeAsVo( $item->slug );
			if ( !empty( $asset ) ) {
				$data[ 'path_details' ][] = sprintf( '%s: %s v%s',
					__( 'Theme', 'wp-simple-firewall' ), $asset->wp_theme->get( 'Name' ), $asset->version );
			}
		}

		return $data;
	}

	public function format() :array {
		/** @var Ptg\ResultItem $item */
		$item = $this->getResultItem();

		$e = $this->getBaseData();
		$e[ 'status' ] = $item->is_different ? __( 'Modified', 'wp-simple-firewall' )
			: ( $item->is_missing ? __( 'Missing', 'wp-simple-firewall' ) : __( 'Unrecognised', 'wp-simple-firewall' ) );
		return $e;
	}

	/**
	 * @inheritDoc
	 */
	protected function getActionDefinitions() :array {
		/** @var Ptg\ResultItem $item */
		$item = $this->getResultItem();
		$assetType = ( $item->context == 'plugins' ? __( 'Plugin', 'wp-simple-firewall' ) : __( 'Theme', 'wp-simple-firewall' ) );
		return array_merge(
			parent::getActionDefinitions(),
			[
				'asset_accept'    => [
					'text'    => sprintf( __( 'Accept %s', 'wp-simple-firewall' ), $assetType ),
					'title'   => sprintf( __( 'Accept all current scan results for this %s.' ), $assetType ),
					'classes' => [ 'asset_accept' ],
					'data'    => [],
				],
				'asset_reinstall' => [
					'text'    => sprintf( __( 'Re-Install %s', 'wp-simple-firewall' ), $assetType ),
					'classes' => [ 'asset_reinstall' ],
					'data'    => []
				],
			]
		);
	}

	/**
	 * @return string[]
	 */
	protected function getExplanation() :array {
		/** @var Ptg\ResultItem $item */
		$item = $this->getResultItem();

		if ( $item->is_different ) {
			$expl = [
				__( "This file appears to have been modified from its original content.", 'wp-simple-firewall' )
				.' '.__( "This may be okay if you're editing files directly on your site.", 'wp-simple-firewall' ),
				__( "You may want to download it to ensure that the contents are as you expect.", 'wp-simple-firewall' )
				.' '.sprintf( __( "You can then click to '%s' the file.", 'wp-simple-firewall' ),
					__( 'Ignore', 'wp-simple-firewall' ) ),
			];
		}
		elseif ( $item->is_missing ) {
			$expl = [
				__( "This file appears to have been removed from your site.", 'wp-simple-firewall' )
				.' '.__( "This may be okay if you're editing files directly on your site.", 'wp-simple-firewall' ),
				__( "If you're unsure, you should check whether this is okay.", 'wp-simple-firewall' )
				.' '.sprintf( __( "You can then click to '%s' the file.", 'wp-simple-firewall' ),
					__( 'Ignore', 'wp-simple-firewall' ) ),
			];
		}
		else {
			$expl = [
				__( "This file appears to have been added to your site.", 'wp-simple-firewall' ),
				__( "This is not normal in the vast majority of cases.", 'wp-simple-firewall' ),
				__( "You may want to download it to ensure that the contents are what you expect.", 'wp-simple-firewall' )
				.' '.sprintf( __( "You can then click to '%s' or '%s' the file.", 'wp-simple-firewall' ),
					__( 'Ignore', 'wp-simple-firewall' ), __( 'Delete', 'wp-simple-firewall' ) ),
			];
		}

		return $expl;
	}

	/**
	 * @inheritDoc
	 */
	protected function getSupportedActions() :array {
		/** @var Ptg\ResultItem $item */
		$item = $this->getResultItem();

		$extras = [
			'asset_accept'
		];

		if ( $item->context == 'plugins' ) {
			$asset = Services::WpPlugins()->getPluginAsVo( $item->slug );
		}
		else {
			$asset = Services::WpThemes()->getThemeAsVo( $item->slug );
		}

		$canRepair = ( new Ptg\Utilities\Repair() )
			->setScanItem( $item )
			->canRepair();
		$hasUpdate = $asset->hasUpdate();

		if ( $hasUpdate ) {
			$extras[] = 'update';
		}

		if ( $item->is_unrecognised ) {
			$extras[] = 'delete';
		}
		elseif ( $canRepair ) {
			$extras[] = 'repair';
		}

		if ( $canRepair && !$hasUpdate ) {
			$extras[] = 'asset_reinstall';
		}

		if ( !$item->is_missing ) {
			$extras[] = 'download';
		}

		return array_merge( parent::getSupportedActions(), $extras );
	}
}