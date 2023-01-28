<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginURLs;

abstract class ScanResultsBase extends Base {

	public const WEIGHT = 6;

	abstract protected function countResults() :int;

	protected function hrefFull() :string {
		return $this->getCon()->plugin_urls->adminTopNav( PluginURLs::NAV_SCANS_RESULTS );
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