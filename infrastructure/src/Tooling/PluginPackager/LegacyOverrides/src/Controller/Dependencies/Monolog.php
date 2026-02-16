<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Dependencies;

class Monolog {

	public const API_VERSION_REQUIRED = '2';

	public function assess() :void {
		throw new \Exception( 'Legacy shutdown guard: monolog disabled.' );
	}
}
