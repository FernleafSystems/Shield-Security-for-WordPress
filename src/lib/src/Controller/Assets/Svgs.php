<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Svgs {

	use PluginControllerConsumer;

	public function raw( string $image ) :string {
		return (string)Services::WpFs()->getFileContent( self::con()->paths->forSVG( $image ) );
	}

	public function url( string $image ) :string {
		return self::con()->urls->forImage( $image );
	}
}
