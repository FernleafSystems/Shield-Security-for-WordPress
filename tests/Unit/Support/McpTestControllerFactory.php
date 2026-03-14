<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;

class McpTestControllerFactory {

	public static function install( array $comps = [], bool $canRestLevel2 = true, string $version = '1.2.3' ) :Controller {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->cfg = new class( $version ) {
			private string $version;

			public function __construct( string $version ) {
				$this->version = $version;
			}

			public function version() :string {
				return $this->version;
			}
		};
		$controller->caps = new class( $canRestLevel2 ) {
			private bool $canRestLevel2;

			public function __construct( bool $canRestLevel2 ) {
				$this->canRestLevel2 = $canRestLevel2;
			}

			public function canRestAPILevel2() :bool {
				return $this->canRestLevel2;
			}
		};
		$controller->comps = (object)$comps;

		PluginControllerInstaller::install( $controller );
		return $controller;
	}
}
