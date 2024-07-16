<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ScanResults\Ops\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveItems;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class FileDownloadHandler {

	use PluginControllerConsumer;

	/**
	 * @throws \Exception
	 */
	public function downloadByItemId( int $resultID ) :array {
		$item = ( new RetrieveItems() )->byID( $resultID );

		if ( empty( $item ) || $item->VO->item_type !== Handler::ITEM_TYPE_FILE || empty( $item->VO->item_id ) ) {
			throw new \Exception( "Invalid download item data." );
		}

		$path = path_join( ABSPATH, $item->VO->item_id );
		$FS = Services::WpFs();
		if ( !$FS->isAccessibleFile( $path ) ) {
			throw new \Exception( "File doesn't exist." );
		}
		if ( $FS->getFileSize( $path ) === 0 ) {
			throw new \Exception( "File appears to be empty." );
		}

		return [
			'name'    => \basename( $path ),
			'content' => $FS->getFileContent( $path )
		];
	}
}