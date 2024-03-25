<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Enum\EnumModules;

abstract class IpBase extends Base {

	use Traits\OptConfigBased;

	protected function testIfProtected() :bool {
		return self::con()->comps->opts_lookup->isModEnabled( EnumModules::IPS );
	}

	protected function getOptConfigKey() :string {
		return 'enable_ips';
	}
}