<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Dependencies;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\{
	Plugin,
	Theme
};

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
			$newAutoLoad = path_join( \dirname( self::con()->root_file ), 'src/lib_monolog/vendor/autoload.php' );
			if ( Services::WpFs()->isAccessibleFile( $newAutoLoad ) ) {
				require_once( $newAutoLoad );
			}
			if ( !@class_exists( '\Monolog\Logger' ) ) {
				throw new Exceptions\LibraryNotFoundException( 'Monolog library could not be found.' );
			}
		}
		elseif ( !\defined( \Monolog\Logger::class.'::API' ) ) {
			throw new Exceptions\LibraryTooOldToBeUseableException( sprintf( 'Monolog library is too old to be usable. Location "%s".', $this->getMonologLoggerLocation() ) );
		}
		elseif ( \version_compare( (string)\Monolog\Logger::API, self::API_VERSION_REQUIRED, '<' ) ) {
			throw new Exceptions\LibraryTooOldException( sprintf( 'Monolog library is version 1. Version %s is required. Location: "%s".',
				self::API_VERSION_REQUIRED, $this->getMonologLoggerLocation()
			) );
		}
	}

	public function getMonologLoggerLocation() :string {
		$item = '';

		$fullFile = \class_exists( '\Monolog\Logger' ) ? ( new \ReflectionClass( \Monolog\Logger::class ) )->getFileName() : '';
		if ( !empty( $fullFile ) ) {
			$plugin = ( new Plugin\Files() )->findPluginFromFile( $fullFile );
			if ( empty( $plugin ) ) {
				$theme = ( new Theme\Files() )->findThemeFromFile( $fullFile );
				if ( !empty( $theme ) ) {
					$item = sprintf( '%s - %s', __( 'Theme' ), $plugin->Name );
				}
			}
			else {
				$item = sprintf( '%s - %s', __( 'Plugin' ), $plugin->Name );
			}
			if ( empty( $item ) ) {
				$item = \str_replace( ABSPATH, '', \class_exists( '\Monolog\Logger' ) ? $fullFile : '' );
			}
		}

		return $item;
	}
}