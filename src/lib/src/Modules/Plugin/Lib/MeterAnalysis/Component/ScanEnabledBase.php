<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

abstract class ScanEnabledBase extends Base {

	public const WEIGHT = 4;

	protected function testIfProtected() :bool {
		$mod = $this->getCon()->getModule_HackGuard();
		return $mod->isModOptEnabled() && $mod->getScanCon( explode( '_', static::SLUG )[ 2 ] )->isEnabled();
	}
}