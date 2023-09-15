<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Dependencies;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Monolog {

	use PluginControllerConsumer;

	public const API_VERSION_REQUIRED = '2';

	/**
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

	/**
	 * @deprecated 18.3.5
	 */
	public function getMonologLoggerLocation() :string {
		return '';
	}
}