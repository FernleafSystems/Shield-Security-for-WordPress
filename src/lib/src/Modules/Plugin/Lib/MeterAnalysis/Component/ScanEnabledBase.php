<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

abstract class ScanEnabledBase extends Base {

	public const WEIGHT = 4;

	protected function testIfProtected() :bool {
		return self::con()
			->comps
			->scans
			->getScanCon( \explode( '_', static::slug() )[ 2 ] )
			->isEnabled();
	}
}