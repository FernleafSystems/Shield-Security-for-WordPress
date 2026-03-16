<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

class UnitTestScansAfsComponent {

	public function __construct(
		private bool $malwareEnabled = false,
		private bool $wpCoreEnabled = false,
		private bool $pluginsEnabled = false,
		private bool $themesEnabled = false,
	) {
	}

	public function isEnabledMalwareScanPHP() :bool {
		return $this->malwareEnabled;
	}

	public function isScanEnabledWpCore() :bool {
		return $this->wpCoreEnabled;
	}

	public function isScanEnabledPlugins() :bool {
		return $this->pluginsEnabled;
	}

	public function isScanEnabledThemes() :bool {
		return $this->themesEnabled;
	}
}
