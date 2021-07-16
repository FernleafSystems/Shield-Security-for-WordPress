<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Table;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseFileEntryFormatter extends BaseEntryFormatter {

	protected function getBaseData() :array {
		$data = parent::getBaseData();
		$item = $this->getResultItem();
		$data[ 'explanation' ] = $this->getExplanation();
		$data[ 'path' ] = $item->path_fragment;
		$data[ 'path_relabs' ] = Services::WpFs()->getPathRelativeToAbsPath( $item->path_full );
		$data[ 'path_details' ] = [];
		$data[ 'created_at' ] = $this->formatTimestampField( $this->getEntryVO()->created_at );
		$data[ 'custom_row' ] = false;

		$actionDefs = array_intersect_key(
			$this->getActionDefinitions(),
			array_flip( array_unique( $this->getSupportedActions() ) )
		);
		foreach ( $actionDefs as $key => $actionDef ) {
			$actionDefs[ $key ][ 'data' ] = array_merge(
				$actionDef[ 'data' ],
				[
					'rid'         => $this->getEntryVO()->id,
					'item_action' => $key,
				]
			);
			$actionDefs[ $key ][ 'classes' ] = array_merge(
				$actionDef[ 'classes' ],
				[ 'action', 'item_action' ]
			);
		}
		$data[ 'actions' ] = $actionDefs;

		return $data;
	}

	/**
	 * @return array[]
	 */
	protected function getActionDefinitions() :array {
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
				'data'    => [ 'href-download' => $this->getScanController()->createFileDownloadLink( $this->getEntryVO()->id ) ]
			],
		];
	}

	/**
	 * @return string[]
	 */
	protected function getExplanation() :array {
		return [];
	}
}
