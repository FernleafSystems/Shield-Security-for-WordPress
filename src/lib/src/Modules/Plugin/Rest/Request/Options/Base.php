<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rest\Request\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Export;

abstract class Base extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest\Request\Process {

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
		foreach ( ( new Export() )->getRawOptionsExport() as $optKey ) {
			if ( empty( $req->filter_keys ) || \in_array( $optKey, $req->filter_keys ) ) {
				$optDef = $this->getOptionData( $optKey );
				$all[] = empty( $filterFields ) ? $optDef : \array_intersect_key( $optDef, $filterFields );
			}
		}
		return $all;
	}

	/**
	 * Option key existence is checked in the Route.
	 */
	protected function getOptionData( string $key ) :array {
		$def = [];
		$opts = self::con()->opts;
		if ( $opts->optExists( $key ) ) {
			$def = \array_merge( $opts->optDef( $key ), [
				'module' => self::con()->cfg->configuration->modFromOpt( $key ),
				'value'  => $opts->optGet( $key ),
			] );
		}
		return $def;
	}
}