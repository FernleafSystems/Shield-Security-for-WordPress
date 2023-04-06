<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\FileLocker\Ops as FileLockerDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModConsumer;

class LoadFileLocks {

	use ModConsumer;

	/**
	 * @var FileLockerDB\Record[]
	 */
	private static $FileLockRecords;

	/**
	 * @return FileLockerDB\Record[]
	 */
	public function loadLocks() :array {
		if ( is_null( self::$FileLockRecords ) ) {

			self::$FileLockRecords = [];
			if ( $this->mod()->getFileLocker()->isEnabled() ) {
				$all = $this->mod()->getDbH_FileLocker()->getQuerySelector()->all();
				if ( is_array( $all ) ) {
					foreach ( $all as $lock ) {
						self::$FileLockRecords[ $lock->id ] = $lock;
					}
				}
			}
		}
		return self::$FileLockRecords;
	}

	/**
	 * @return FileLockerDB\Record[]
	 */
	public function ofType( string $type ) :array {
		return array_filter(
			$this->loadLocks(),
			function ( $lock ) use ( $type ) {
				return $lock->type === $type;
			}
		);
	}

	/**
	 * @return FileLockerDB\Record[]
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
	 * @return FileLockerDB\Record[]
	 */
	public function withProblemsNotNotified() :array {
		return array_filter(
			$this->withProblems(),
			function ( $lock ) {
				return $lock->notified_at == 0;
			}
		);
	}

	/**
	 * @return FileLockerDB\Record[]
	 */
	public function withoutProblems() :array {
		return array_filter(
			$this->loadLocks(),
			function ( $lock ) {
				return $lock->detected_at == 0;
			}
		);
	}

	public function clearLocksCache() {
		self::$FileLockRecords = null;
	}
}