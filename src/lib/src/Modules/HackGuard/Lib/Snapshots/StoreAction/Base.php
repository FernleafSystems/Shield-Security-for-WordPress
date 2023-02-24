<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StorageDir;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class Base {

	use ModConsumer;

	public const MOD = ModCon::SLUG;

	/**
	 * @deprecated 17.0
	 */
	private static $tempDir;

	private static $storageDir;

	protected function isTempDirAvailable() :bool {
		return !empty( $this->getTempDir() );
	}

	protected function getTempDir() :string {
		return ( self::$storageDir ?? self::$storageDir = ( new StorageDir() )->setCon( $this->getCon() ) )->getTempDir();
	}
}