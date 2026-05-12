<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;

class MaintenancePluginVo extends WpPluginVo {

	public string $file;
	public string $Title;
	public string $Name;
	public string $Version;

	public function __construct( string $file, string $title, string $version ) {
		$this->file = $file;
		$this->Title = $title;
		$this->Name = $title;
		$this->Version = $version;
	}
}
