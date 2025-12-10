<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\I18n;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class GetAllAvailableLocales {

	use PluginControllerConsumer;

	public function run() :array {
		return $this->enumFromFS();
	}

	protected function enumFromFS() :array {
		$locales = [];
		try {
			$regex = sprintf( '#^%s\-(.+)\.mo$#', self::con()->getTextDomain() );
			foreach ( new \FilesystemIterator( self::con()->getPath_Languages() ) as $fsItem ) {
				/** @var \SplFileInfo $fsItem */
				if ( $fsItem->isFile() && \preg_match( $regex, $fsItem->getBasename(), $matches ) ) {
					$locales[ $matches[ 1 ] ] = $fsItem->getPathname();
				}
			}
		}
		catch ( \Exception $e ) {
		}
		\ksort( $locales );
		return $locales;
	}
}