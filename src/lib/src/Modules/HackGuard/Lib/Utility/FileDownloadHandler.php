<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ScanResults\Ops\{
	Handler,
	Record
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class FileDownloadHandler {

	use ModConsumer;

	/**
	 * @param int $itemID
	 */
	public function downloadByItemId( int $itemID ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Record $record */
		$record = $mod->getDbH_ScanResults()
					  ->getQuerySelector()
					  ->byId( $itemID );
		if ( !empty( $record ) && $record->item_type === Handler::ITEM_TYPE_FILE && !empty( $record->item_id ) ) {
			$path = path_join( ABSPATH, $record->item_id );
			$FS = Services::WpFs();
			if ( $FS->isFile( $path ) ) {
				header( 'Set-Cookie: fileDownload=true; path=/' );
				Services::Response()->downloadStringAsFile( $FS->getFileContent( $path ), basename( $path ) );
			}
		}

		wp_die( "Something about this request wasn't right" );
	}
}