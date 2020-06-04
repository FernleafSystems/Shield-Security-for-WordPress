<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Table;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseFileEntryFormatter extends BaseEntryFormatter {

	/**
	 * @return array
	 */
	protected function getBaseData() {
		$aData = parent::getBaseData();
		$oIt = $this->getResultItem();
		$aData[ 'explanation' ] = $this->getExplanation();
		$aData[ 'path' ] = $oIt->path_fragment;
		$aData[ 'path_relabs' ] = Services::WpFs()->getPathRelativeToAbsPath( $oIt->path_full );
		$aData[ 'path_details' ] = [];
		$aData[ 'created_at' ] = $this->formatTimestampField( $this->getEntryVO()->created_at );
		$aData[ 'custom_row' ] = false;

		$aActionDefs = array_intersect_key(
			$this->getActionDefinitions(),
			array_flip( array_unique( $this->getSupportedActions() ) )
		);
		foreach ( $aActionDefs as $sKey => $aActionDef ) {
			$aActionDefs[ $sKey ][ 'data' ] = array_merge(
				$aActionDef[ 'data' ],
				[
					'rid'         => $this->getEntryVO()->id,
					'item_action' => $sKey,
				]
			);
			$aActionDefs[ $sKey ][ 'classes' ] = array_merge(
				$aActionDef[ 'classes' ],
				[ 'action', 'item_action' ]
			);
		}
		$aData[ 'actions' ] = $aActionDefs;

		return $aData;
	}

	/**
	 * @return array[]
	 */
	protected function getActionDefinitions() {
		return [
			'ignore'   => [
				'text'    => sprintf( __( 'Ignore %s', 'wp-simple-firewall' ), __( 'File', 'wp-simple-firewall' ) ),
				'classes' => [],
				'data'    => []
			],
			'delete'   => [
				'text'    => sprintf( __( 'Delete %s', 'wp-simple-firewall' ), __( 'File', 'wp-simple-firewall' ) ),
				'classes' => [ 'text-danger' ],
				'data'    => []
			],
			'repair'   => [
				'text'    => sprintf( __( 'Repair %s', 'wp-simple-firewall' ), __( 'File', 'wp-simple-firewall' ) ),
				'classes' => [ 'text-success' ],
				'data'    => []
			],
			'download' => [
				'text'    => sprintf( __( 'Download %s', 'wp-simple-firewall' ), __( 'File', 'wp-simple-firewall' ) ),
				'classes' => [ 'href-download', 'text-info' ],
				'data'    => [ 'href-download' => $this->getScanController()->createFileDownloadLink( $this->getEntryVO() ) ]
			],
		];
	}

	/**
	 * @return string[]
	 */
	protected function getExplanation() {
		return [];
	}
}
