<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Dependencies;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class Monolog {

	use PluginControllerConsumer;

	public const API_VERSION_REQUIRED = '2';

	/**
	 * @throws Exceptions\LibraryNotFoundException
	 * @throws Exceptions\LibraryTooOldException
	 * @throws Exceptions\LibraryTooOldToBeUseableException
	 */
	public function assess() :void {
		if ( !@class_exists( '\Monolog\Logger' ) ) {
			require_once path_join( dirname( $this->getCon()->root_file ), 'src/lib_monolog/vendor/autoload.php' );
			if ( !@class_exists( '\Monolog\Logger' ) ) {
				throw new Exceptions\LibraryNotFoundException( 'Monolog library could not be found.' );
			}
		}
		elseif ( !defined( \Monolog\Logger::class.'::API' ) ) {
			throw new Exceptions\LibraryTooOldToBeUseableException( sprintf( 'Monolog library is too old to be usable. Location "%s".', $this->getMonologLoggerLocation() ) );
		}
		elseif ( version_compare( (string)\Monolog\Logger::API, self::API_VERSION_REQUIRED, '<' ) ) {
			throw new Exceptions\LibraryTooOldException( sprintf( 'Monolog library is version 1. Version %s is required. Location: "%s".',
				self::API_VERSION_REQUIRED, $this->getMonologLoggerLocation()
			) );
		}
	}

	public function getMonologLoggerLocation() :string {
		return str_replace( ABSPATH, '', class_exists( '\Monolog\Logger' ) ? ( new \ReflectionClass( \Monolog\Logger::class ) )->getFileName() : '' );
	}
}