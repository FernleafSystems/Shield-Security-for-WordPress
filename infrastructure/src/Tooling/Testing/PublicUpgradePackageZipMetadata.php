<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

class PublicUpgradePackageZipMetadata {

	private string $zipPath;

	private string $version;

	private string $pluginFile;

	public function __construct( string $zipPath, string $version, string $pluginFile ) {
		$this->zipPath = $zipPath;
		$this->version = $version;
		$this->pluginFile = $pluginFile;
	}

	public function zipPath() :string {
		return $this->zipPath;
	}

	public function version() :string {
		return $this->version;
	}

	public function pluginFile() :string {
		return $this->pluginFile;
	}
}
