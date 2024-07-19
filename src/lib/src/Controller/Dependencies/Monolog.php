<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Dependencies;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class Monolog {

	use PluginControllerConsumer;

	public const API_VERSION_REQUIRED = '2';

	/**
	 * Since this is a shutdown method, use \version_compare( $this->con()->cfg->version(), 'x.y.z', '<' )
	 * to test whether we can run it (if there are changes)
	 *
	 * @throws Exceptions\LibraryNotFoundException
	 * @throws \Exception
	 */
	public function assess() :void {
		if ( \method_exists( self::con(), 'includePrefixedVendor' ) ) {
			self::con()->includePrefixedVendor();
		}
		if ( !@\class_exists( '\AptowebDeps\Monolog\Logger' ) ) {
			throw new Exceptions\LibraryNotFoundException( 'Prefixed Monolog library could not be found.' );
		}
	}
}