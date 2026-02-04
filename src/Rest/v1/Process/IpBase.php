<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process;

abstract class IpBase extends Base {

	protected function ip() :string {
		return $this->getWpRestRequest()->get_param( 'ip' );
	}
}