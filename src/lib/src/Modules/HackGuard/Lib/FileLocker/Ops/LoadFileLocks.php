<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class LoadFileLocks {

	use ModConsumer;

	/**
	 * @var FileLocker\EntryVO[]
	 */
	private static $aFileLockRecords;

	/**
	 * @return FileLocker\EntryVO[]
	 */
	public function loadLocks() :array {
		if ( is_null( self::$aFileLockRecords ) ) {
			/** @var ModCon $mod */
			$mod = $this->getMod();

			self::$aFileLockRecords = [];
			if ( $mod->getFileLocker()->isEnabled() ) {
				$all = $mod->getDbHandler_FileLocker()->getQuerySelector()->all();
				if ( is_array( $all ) ) {
					foreach ( $all as $lock ) {
						self::$aFileLockRecords[ $lock->id ] = $lock;
					}
				}
			}
		}
		return self::$aFileLockRecords;
	}

	/**
	 * @return FileLocker\EntryVO[]
	 */
	public function withProblems() :array {
		return array_filter(
			$this->loadLocks(),
			function ( $lock ) {
				return $lock->detected_at > 0;
			}
		);
	}

	/**
	 * @return FileLocker\EntryVO[]
	 */
	public function withProblemsNotNotified() {
		return array_filter(
			$this->withProblems(),
			function ( $lock ) {
				return $lock->notified_at == 0;
			}
		);
	}

	/**
	 * @return FileLocker\EntryVO[]
	 */
	public function withoutProblems() {
		return array_filter(
			$this->loadLocks(),
			function ( $lock ) {
				return $lock->detected_at == 0;
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