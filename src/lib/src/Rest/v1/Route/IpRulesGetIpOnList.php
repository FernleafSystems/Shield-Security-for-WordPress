<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Route;

class IpRulesGetIpOnList extends IpRulesBase {

	public function getRoutePath() :string {
		return '/(?P<list>bypass|block|crowdsec)/(?P<ip>[0-9a-f\.:]{3,})';
	}

	protected function getRouteArgsCustom() :array {
		return [
			'ip'   => $this->getRouteArgSchema( 'ip' ),
			'list' => $this->getRouteArgSchema( 'list' ),
		];
	}

	protected function getRequestProcessorClass() :string {
		return \FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process\IpRulesGetIpOnList::class;
	}
}