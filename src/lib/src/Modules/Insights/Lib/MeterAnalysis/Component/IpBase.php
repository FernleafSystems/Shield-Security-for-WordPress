<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

abstract class IpBase extends Base {

	protected function isProtected() :bool {
		return $this->getCon()->getModule_IPs()->isModOptEnabled();
	}

	public function href() :string {
		return $this->link( 'enable_ips' );
	}
}