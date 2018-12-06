<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Core\VOs\WpPluginVo;

class ScanWpv extends ScanBase {

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_asset( $aItem ) {
		/** @var WpPluginVo $oAsset */
		$oAsset = $aItem[ 'asset' ];
		$sContent = sprintf( '<span class="asset-title font-weight-bold">%s</span> v%s',
			$oAsset->Name, ltrim( $oAsset->Version, 'v' ) );

		$aButtons = [];

		$aButtons[] = $this->buildActionButton_Custom(
			$aItem[ 'has_update' ] ? _wpsf__( 'Apply Update' ) : _wpsf__( 'No Update Available' ),
			[ ( $aItem[ 'has_update' ] ? 'custom-action' : 'disabled' ) ],
			[
				'rid'           => $aItem[ 'id' ],
				'custom-action' => 'item_applyupdate'
			]
		);

		$aButtons[] = $this->buildActionButton_Custom(
			$aItem[ 'is_active' ] ? _wpsf__( 'Deactivate' ) : _wpsf__( 'Already Deactivated' ),
			[ ( $aItem[ 'is_active' ] ? 'custom-action' : 'disabled' ) ],
			[
				'rid'           => $aItem[ 'id' ],
				'custom-action' => 'item_deactivate'
			]
		);
		
		return $sContent.$this->buildActions( $aButtons );
	}

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_vulnerability( $aItem ) {
		/** @var Scans\Wpv\WpVulnDb\WpVulnVO $oVo */
		$oVo = $aItem[ 'wpvuln_vo' ];
		$sContent = sprintf( '<span class="vuln-title">%s</span>', $oVo->title );

		$aButtons = [
			$this->getActionButton_Ignore( $aItem[ 'id' ] ),
			sprintf( '<a href="%s" class="btn btn-sm btn-link text-info" target="_blank">%s</a>',
				$oVo->getUrl(), _wpsf__( 'More Info' ) ),
		];
		return $sContent.$this->buildActions( $aButtons );
	}

	/**
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'ignore'     => 'Ignore',
			'deactivate' => 'Deactivate',
			'repair'     => 'Apply Updates',
		);
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'            => '&nbsp;',
			'vulnerability' => 'Vulnerability',
			'asset'         => 'Asset Details',
			'created_at'    => 'Discovered',
		);
	}
}