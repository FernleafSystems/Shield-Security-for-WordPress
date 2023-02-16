<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StorageDir;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Base {

	use ModConsumer;

	private static $tempDir;

	private static $storageDir;

	protected function isTempDirAvailable() :bool {
		return !empty( $this->getTempDir() );
	}

	protected function getTempDir() :string {
		{ // TODO: remove this
			if ( !empty( self::$tempDir ) ) {
				return self::$tempDir;
			}
		}

		if ( empty( self::$storageDir ) ) {
			self::$storageDir = ( new StorageDir() )->setCon( $this->getCon() );
		}
		return self::$storageDir->getTempDir();
	}
}