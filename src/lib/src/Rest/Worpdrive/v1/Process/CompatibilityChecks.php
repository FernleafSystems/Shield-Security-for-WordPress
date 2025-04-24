<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\v1\Process;

use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive;

class CompatibilityChecks extends BaseWorpdrive {

	protected function process() :array {
		return ( new Worpdrive\CompatibilityChecks( $this->getWpRestRequest()->get_param( 'uuid' ), 0 ) )->run();
	}
}