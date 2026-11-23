<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Svgs {

	use PluginControllerConsumer;

	/**
	 * @deprecated 21.2.2 Bootstrap icon SVG files are deprecated; use iconClass()
	 */
	public function raw( string $image ) :string {
		return (string)Services::WpFs()->getFileContent( self::con()->paths->forSVG( $image ) );
	}

	public function iconClass( string $icon ) :string {
		return \sprintf( 'bi bi-%s', (string)\preg_replace( '#\.svg$#i', '', \ltrim( \trim( $icon ), '/' ) ) );
	}
}
