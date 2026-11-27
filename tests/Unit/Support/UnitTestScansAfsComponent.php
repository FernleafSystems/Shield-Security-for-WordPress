<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

class UnitTestScansAfsComponent {

	private bool $malwareEnabled;

	private bool $wpCoreEnabled;

	private bool $pluginsEnabled;

	private bool $themesEnabled;

	public function __construct(
		bool $malwareEnabled = false,
		bool $wpCoreEnabled = false,
		bool $pluginsEnabled = false,
		bool $themesEnabled = false
	) {
		$this->malwareEnabled = $malwareEnabled;
		$this->wpCoreEnabled = $wpCoreEnabled;
		$this->pluginsEnabled = $pluginsEnabled;
		$this->themesEnabled = $themesEnabled;
	}

	/**
	 * The scanner's own switch, taken as on while any kind of file is scanned.
	 */
	public function isEnabled() :bool {
		return !empty( $this->getFileScanAreas() );
	}

	/**
	 * @return list<string>
	 */
	public function getFileScanAreas() :array {
		return \array_keys( \array_filter( [
			'wp'          => $this->wpCoreEnabled,
			'plugins'     => $this->pluginsEnabled,
			'themes'      => $this->themesEnabled,
			'malware_php' => $this->malwareEnabled,
		] ) );
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
