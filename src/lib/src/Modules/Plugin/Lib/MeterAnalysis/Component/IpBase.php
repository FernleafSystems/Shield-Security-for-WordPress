<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

abstract class IpBase extends Base {

	use Traits\OptConfigBased;

	protected function testIfProtected() :bool {
		return $this->con()->getModule_IPs()->isModOptEnabled();
	}

	protected function getOptConfigKey() :string {
		return 'enable_ips';
	}
}