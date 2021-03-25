<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\HandlerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;
use FernleafSystems\Wordpress\Services\Services;

class FileDownloadHandler {

	use HandlerConsumer;

	/**
	 * @param int $itemID
	 */
	public function downloadByItemId( int $itemID ) {
		/** @var Scanner\EntryVO $entry */
		$entry = $this->getDbHandler()
					  ->getQuerySelector()
					  ->byId( (int)$itemID );
		if ( $entry instanceof Scanner\EntryVO && !empty( $entry->meta[ 'path_full' ] ) ) {
			$path = $entry->meta[ 'path_full' ];
			$FS = Services::WpFs();
			if ( $FS->isFile( $path ) ) {
				header( 'Set-Cookie: fileDownload=true; path=/' );
				Services::Response()->downloadStringAsFile( $FS->getFileContent( $path ), basename( $path ) );
			}
		}

		wp_die( "Something about this request wasn't right" );
	}
}