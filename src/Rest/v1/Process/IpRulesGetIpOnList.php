<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process;

class IpRulesGetIpOnList extends IpRulesBase {

	protected function process() :array {
		return [
			'ip' => $this->getIpData( $this->ip(), $this->getWpRestRequest()->get_param( 'list' ) )
		];
	}
}