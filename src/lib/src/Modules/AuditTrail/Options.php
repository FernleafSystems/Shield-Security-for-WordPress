<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers\Utility\LogFileDirCreate;

class Options extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options {

	/**
	 * @deprecated 19.1
	 */
	public function getLogFilePath() :string {
		try {
			$dir = ( new LogFileDirCreate() )->run();
		}
		catch ( \Exception $e ) {
			$dir = '';
		}

		$path = empty( $dir ) ? '' : path_join( $dir, 'shield.log' );
		return apply_filters( 'shield/audit_trail_log_file_path', $path );
	}

	/**
	 * @deprecated 19.1
	 */
	public function getLogFileRotationLimit() :int {
		return (int)apply_filters( 'shield/audit_trail_log_file_rotation_limit', 5 );
	}

	/**
	 * @deprecated 19.1
	 */
	public function getLogLevelsDB() :array {
		$levels = $this->getOpt( 'log_level_db', [] );
		if ( empty( $levels ) || !\is_array( $levels ) ) {
			$this->resetOptToDefault( 'log_level_db' );
		}
		elseif ( \count( $levels ) > 1 && \in_array( 'disabled', $levels ) ) {
			$this->setOpt( 'log_level_db', [ 'disabled' ] );
		}
		return $this->getOpt( 'log_level_db', [] );
	}

	/**
	 * Don't put caps into cfg as this option is always available, but limited to 7.
	 * @deprecated 19.1
	 */
	public function getAutoCleanDays() :int {
		$days = (int)\min( $this->getOpt( 'audit_trail_auto_clean' ), self::con()->caps->getMaxLogRetentionDays() );
		$this->setOpt( 'audit_trail_auto_clean', $days );
		return $days;
	}

	/**
	 * @deprecated 19.1
	 */
	public function isLogToDB() :bool {
		$aCon = self::con()->getModule_AuditTrail()->getAuditCon();
		return !\in_array( 'disabled',
			\method_exists( $aCon, 'getLogLevelsDB' ) ? $aCon->getLogLevelsDB() : $this->opts()->getLogLevelsDB()
		);
	}
}