<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\HandlerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;
use FernleafSystems\Wordpress\Services\Services;

class FileDownloadHandler {

	use HandlerConsumer;

	/**
	 * @param int $nItemId
	 */
	public function downloadByItemId( $nItemId ) {
		/** @var Scanner\EntryVO $oEntry */
		$oEntry = $this->getDbHandler()
					   ->getQuerySelector()
					   ->byId( (int)$nItemId );
		if ( $oEntry instanceof Scanner\EntryVO && !empty( $oEntry->meta[ 'path_full' ] ) ) {
			$sPath = $oEntry->meta[ 'path_full' ];
			$oFs = Services::WpFs();
			if ( $oFs->isFile( $sPath ) ) {
				header( 'Set-Cookie: fileDownload=true; path=/' );
				Services::Response()->downloadStringAsFile( $oFs->getFileContent( $sPath ), basename( $sPath ) );
			}
		}

		wp_die( "Something about this request wasn't right" );
	}
}