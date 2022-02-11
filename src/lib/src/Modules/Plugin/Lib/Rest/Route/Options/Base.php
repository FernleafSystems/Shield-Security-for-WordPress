<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Route\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Route\RouteBase;

abstract class Base extends RouteBase {

	public function getRoutePathPrefix() :string {
		return '/options';
	}

	protected function optKeyExists( string $key ) :bool {
		$exists = false;
		foreach ( $this->getCon()->modules as $module ) {
			if ( $module->getOptions()->optExists( $key ) ) {
				$exists = true;
				break;
			}
		}
		return $exists;
	}
}