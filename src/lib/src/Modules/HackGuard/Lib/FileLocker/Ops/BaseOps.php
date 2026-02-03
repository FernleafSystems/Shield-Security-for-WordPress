<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\FileLocker\Ops as FileLockerDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Exceptions\PublicKeyRetrievalFailure;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\FileLocker\GetPublicKey;

/**
 * @depecated 21.1
 */
class BaseOps {

	use PluginControllerConsumer;

	/**
	 * @var FileLocker\File
	 */
	protected $file;

	/**
	 * @depecated 21.1
	 */
	protected function findLockRecordForFile() :?FileLockerDB\Record {
		return ( new FileLocker\Utility\FindLockRecordForFile() )->find( $this->file );
	}

	/**
	 * @throws PublicKeyRetrievalFailure
	 * @depecated 21.1
	 */
	protected function getPublicKey() :array {
		return ( new FileLocker\Utility\RetrievePublicKey() )->retrieve();
	}

	/**
	 * @depecated 21.1
	 */
	public function setWorkingFile( FileLocker\File $file ) :self {
		$this->file = $file;
		return $this;
	}
}
