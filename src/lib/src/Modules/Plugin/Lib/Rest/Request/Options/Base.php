<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Request\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Request\Process;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Export;

abstract class Base extends Process {

	protected function getAllOptions() :array {
		$req = $this->getRequestVO();
		$export = ( new Export() )
			->setMod( $this->getMod() )
			->getRawOptionsExport();

		$filterFields = $this->getFilterFields();
		$filterKeys = is_array( $req->filter_keys ) ? $req->filter_keys : [];

		$allOptions = [];
		foreach ( $export as $modSlug => $modOpts ) {
			$mod = $this->getCon()->modules[ $modSlug ];
			$opts = $mod->getOptions();
			foreach ( $modOpts as $optKey => $optValue ) {

				if ( empty( $filterKeys ) || in_array( $optKey, $filterKeys ) ) {
					$optDef = $opts->getOptDefinition( $optKey );
					$optDef[ 'module' ] = $modSlug;
					$optDef[ 'value' ] = $opts->getOpt( $optKey );
					$allOptions[] = empty( $filterFields ) ? $optDef : array_intersect_key( $optDef, $filterFields );
				}
			}
		}
		return $allOptions;
	}

	private function getFilterFields() :array {
		$req = $this->getRequestVO();
		$defaultFilterFields = [
			'key',
			'value',
			'module',
		];
		if ( !empty( $req->filter_fields ) ) {
			$customFilterFields = array_map( 'trim', explode( ',', $req->filter_fields ) );
			if ( in_array( 'all', $customFilterFields ) ) {
				$filterFields = [];
			}
			else {
				$filterFields = array_merge( $defaultFilterFields, $customFilterFields );
			}
		}
		else {
			$filterFields = $defaultFilterFields;
		}
		return array_flip( $filterFields );
	}

	/**
	 * Option key existence is checked in the Route.
	 */
	protected function getOptionData( string $key ) :array {
		$def = [];
		foreach ( $this->getCon()->modules as $module ) {
			$opts = $module->getOptions();
			$maybe = $opts->getOptDefinition( $key );
			if ( !empty( $maybe ) ) {
				$def = $maybe;
				$def[ 'module' ] = $module->getSlug();
				$def[ 'value' ] = $opts->getOpt( $key );
				break;
			}
		}
		return $def;
	}
}