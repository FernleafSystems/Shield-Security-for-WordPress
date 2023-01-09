<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginURLs;

abstract class ScanResultsBase extends Base {

	public const WEIGHT = 55;

	abstract protected function countResults() :int;

	protected function href() :string {
		return $this->getCon()->plugin_urls->adminTop( PluginURLs::NAV_SCANS_RESULTS );
	}

	protected function isCritical() :bool {
		return !$this->isProtected();
	}

	protected function isProtected() :bool {
		return $this->countResults() === 0;
	}

	protected function weight() :int {
		return $this->countResults() > 0 ? static::WEIGHT : 10;
	}
}