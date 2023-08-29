<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\I18n;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class GetAllAvailableLocales {

	use PluginControllerConsumer;

	public function run() :array {
		$locales = [ 'en_US' ];
		foreach ( new \DirectoryIterator( self::con()->getPath_Languages() ) as $file ) {
			if ( $file->isFile() && $file->getExtension() === 'mo' ) {
				$locales[] = \str_replace( self::con()->getTextDomain().'-', '', $file->getBasename( '.mo' ) );
			}
		}
		\asort( $locales );
		return \array_unique( $locales );
	}
}