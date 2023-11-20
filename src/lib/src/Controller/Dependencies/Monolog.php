<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Dependencies;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Monolog {

	use PluginControllerConsumer;

	public const API_VERSION_REQUIRED = '2';

	/**
	 * Since this is a shutdown method, use \version_compare( $this->con()->cfg->version(), 'x.y.z', '<' )
	 * to test whether we can run it (if there are changes)
	 *
	 * @throws Exceptions\LibraryNotFoundException
	 * @throws Exceptions\LibraryTooOldException
	 * @throws Exceptions\LibraryTooOldToBeUseableException
	 */
	public function assess() :void {
		$newAutoLoad = path_join( \dirname( self::con()->root_file ), 'src/lib_scoped/vendor/scoper-autoload.php' );
		if ( Services::WpFs()->isAccessibleFile( $newAutoLoad ) ) {
			require_once( $newAutoLoad );
		}
		if ( !@\class_exists( '\AptowebDeps\Monolog\Logger' ) ) {
			throw new Exceptions\LibraryNotFoundException( 'Scoped Monolog library could not be found.' );
		}
	}
}