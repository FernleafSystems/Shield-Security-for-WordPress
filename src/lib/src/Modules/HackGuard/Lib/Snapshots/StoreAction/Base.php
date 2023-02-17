<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StorageDir;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Base {

	use ModConsumer;

	public const MOD = ModCon::SLUG;

	private static $tempDir;

	private static $storageDir;

	protected function isTempDirAvailable() :bool {
		return !empty( $this->getTempDir() );
	}

	protected function getTempDir() :string {
		{ // @deprecated 17.1? TODO: remove this
			if ( !empty( self::$tempDir ) ) {
				return self::$tempDir;
			}
		}

		if ( empty( self::$storageDir ) ) {
			self::$storageDir = new StorageDir();
		}
		return self::$storageDir->getTempDir();
	}
}