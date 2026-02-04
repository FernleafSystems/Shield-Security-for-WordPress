<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

abstract class ScanEnabledAfsAutoRepairBase extends Base {

	use Traits\OptConfigBased;

	public const MINIMUM_EDITION = 'business';

	protected function getOptConfigKey() :string {
		return 'file_repair_areas';
	}

	protected function testIfProtected() :bool {
		return self::con()->comps->scans->AFS()->isEnabled();
	}
}