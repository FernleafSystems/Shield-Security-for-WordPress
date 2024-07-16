<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process;

class Debug extends Base {

	protected function process() :array {
		return ( new \FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Debug\Collate() )->run();
	}
}