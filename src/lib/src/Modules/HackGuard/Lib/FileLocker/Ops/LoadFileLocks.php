<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

/**
 * Class LoadFileLocks
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops
 */
class LoadFileLocks {

	use ModConsumer;

	/**
	 * @var FileLocker\EntryVO[]
	 */
	private static $aFileLockRecords;

	/**
	 * @return FileLocker\EntryVO[]
	 */
	public function loadLocks() {
		if ( is_null( self::$aFileLockRecords ) ) {
			/** @var ModCon $mod */
			$mod = $this->getMod();

			self::$aFileLockRecords = [];
			if ( $mod->getFileLocker()->isEnabled() ) {
				$aAll = $mod->getDbHandler_FileLocker()->getQuerySelector()->all();
				if ( is_array( $aAll ) ) {
					foreach ( $aAll as $oLock ) {
						self::$aFileLockRecords[ $oLock->id ] = $oLock;
					}
				}
			}
		}
		return self::$aFileLockRecords;
	}

	/**
	 * @return FileLocker\EntryVO[]
	 */
	public function withProblems() {
		return array_filter(
			$this->loadLocks(),
			function ( $oLock ) {
				return $oLock->detected_at > 0;
			}
		);
	}

	/**
	 * @return FileLocker\EntryVO[]
	 */
	public function withProblemsNotNotified() {
		return array_filter(
			$this->withProblems(),
			function ( $oLock ) {
				return $oLock->notified_at == 0;
			}
		);
	}

	/**
	 * @return FileLocker\EntryVO[]
	 */
	public function withoutProblems() {
		return array_filter(
			$this->loadLocks(),
			function ( $oLock ) {
				return $oLock->detected_at == 0;
			}
		);
	}

	/**
	 * @return $this
	 */
	public function clearLocksCache() {
		self::$aFileLockRecords = null;
		return $this;
	}
}