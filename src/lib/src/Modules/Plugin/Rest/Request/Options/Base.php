<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rest\Request\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest\Request\Process;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Export;

abstract class Base extends Process {

	/**
	 * @return RequestVO
	 */
	protected function newReqVO() {
		return new RequestVO();
	}

	protected function getAllOptions() :array {
		/** @var RequestVO $req */
		$req = $this->getRequestVO();
		$all = [];
		$filterFields = $req->filter_fields;
		foreach ( ( new Export() )->getRawOptionsExport() as $modOpts ) {
			foreach ( \array_keys( $modOpts ) as $key ) {
				if ( empty( $req->filter_keys ) || \in_array( $key, $req->filter_keys ) ) {
					$optDef = $this->getOptionData( $key );
					$all[] = empty( $filterFields ) ? $optDef : array_intersect_key( $optDef, $filterFields );
				}
			}
		}
		return $all;
	}

	/**
	 * Option key existence is checked in the Route.
	 */
	protected function getOptionData( string $key ) :array {
		$def = [];
		foreach ( $this->con()->modules as $module ) {
			$opts = $module->getOptions();
			$maybe = $opts->getOptDefinition( $key );
			if ( !empty( $maybe ) ) {
				$def = $maybe;
				$def[ 'module' ] = $module->cfg->slug;
				$def[ 'value' ] = $opts->getOpt( $key );
				break;
			}
		}
		return $def;
	}
}