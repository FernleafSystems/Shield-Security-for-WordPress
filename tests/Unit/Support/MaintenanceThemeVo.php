<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpThemeVo;

class MaintenanceThemeVo extends WpThemeVo {

	public string $stylesheet;
	public string $Name;
	public string $Version;

	public function __construct( string $stylesheet, string $name, string $version ) {
		$this->stylesheet = $stylesheet;
		$this->Name = $name;
		$this->Version = $version;
	}
}
