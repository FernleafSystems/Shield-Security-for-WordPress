<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ScanResults\Ops\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class FileDownloadHandler {

	use ModConsumer;

	/**
	 * @param int $resultID
	 */
	public function downloadByItemId( int $resultID ) {
		try {
			$item = ( new Retrieve() )
				->setMod( $this->getMod() )
				->byID( $resultID );

			if ( !empty( $item ) &&
				 $item->VO->item_type === Handler::ITEM_TYPE_FILE && !empty( $item->VO->item_id ) ) {
				$path = path_join( ABSPATH, $item->VO->item_id );
				$FS = Services::WpFs();
				if ( $FS->isFile( $path ) ) {
					header( 'Set-Cookie: fileDownload=true; path=/' );
					Services::Response()->downloadStringAsFile( $FS->getFileContent( $path ), basename( $path ) );
				}
			}
		}
		catch ( \Exception $e ) {
		}

		wp_die( "Something about this request wasn't right" );
	}
}