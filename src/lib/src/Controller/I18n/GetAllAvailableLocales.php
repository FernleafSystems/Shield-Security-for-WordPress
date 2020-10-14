<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\I18n;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class GetAllAvailableLocales {

	use PluginControllerConsumer;

	public function run() :array {
		$con = $this->getCon();

		$locales = [ 'en_US' ];
		$dirIT = new \DirectoryIterator( $con->getPath_Languages() );
		foreach ( $dirIT as $file ) {
			if ( $file->isFile() && $file->getExtension() === 'mo' ) {
				$locales[] = str_replace( $con->getTextDomain().'-', '', $file->getBasename( '.mo' ) );
			}
		}
		asort( $locales );
		return array_unique( $locales );
	}
}