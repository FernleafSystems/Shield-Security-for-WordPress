<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

abstract class ScanResultsBase extends Base {

	public const WEIGHT = 6;

	abstract protected function countResults() :int;

	protected function hrefFull() :string {
		return self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS );
	}

	protected function isCritical() :bool {
		return !$this->testIfProtected();
	}

	protected function testIfProtected() :bool {
		return $this->countResults() === 0;
	}

	protected function weight() :int {
		return $this->countResults() > 0 ? static::WEIGHT : 2;
	}
}