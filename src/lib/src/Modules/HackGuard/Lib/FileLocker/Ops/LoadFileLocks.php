<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\FileLocker\Ops as FileLockerDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class LoadFileLocks {

	use PluginControllerConsumer;

	/**
	 * @return FileLockerDB\Record[]
	 */
	public function loadLocks() :array {
		$records = [];
		if ( self::con()->comps->file_locker->isEnabled() ) {
			$all = self::con()
				->db_con
				->file_locker
				->getQuerySelector()
				->setNoOrderBy()
				->all();
			foreach ( \is_array( $all ) ? $all : [] as $lock ) {
				$records[ $lock->id ] = $lock;
			}
		}
		return $records;
	}

	/**
	 * @return FileLockerDB\Record[]
	 */
	public function ofType( string $type ) :array {
		return \array_filter(
			self::con()->comps->file_locker->getLocks(),
			function ( $lock ) use ( $type ) {
				return $lock->type === $type;
			}
		);
	}

	/**
	 * @return FileLockerDB\Record[]
	 */
	public function withProblems() :array {
		return \array_filter(
			self::con()->comps->file_locker->getLocks(),
			function ( $lock ) {
				return $lock->detected_at > 0;
			}
		);
	}

	/**
	 * @return FileLockerDB\Record[]
	 */
	public function withProblemsNotNotified() :array {
		return \array_filter(
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
		return \array_filter(
			self::con()->comps->file_locker->getLocks(),
			function ( $lock ) {
				return $lock->detected_at == 0;
			}
		);
	}
}