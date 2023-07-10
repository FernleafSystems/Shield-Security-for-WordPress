<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers\Utility\LogFileDirCreate;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Options extends BaseShield\Options {

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

	public function getLogFileRotationLimit() :int {
		return (int)apply_filters( 'shield/audit_trail_log_file_rotation_limit', 5 );
	}

	public function getLogLevelsDB() :array {
		$levels = $this->getOpt( 'log_level_db', [] );
		if ( empty( $levels ) || !is_array( $levels ) ) {
			$this->resetOptToDefault( 'log_level_db' );
		}
		elseif ( count( $levels ) > 1 && in_array( 'disabled', $levels ) ) {
			$this->setOpt( 'log_level_db', [ 'disabled' ] );
		}
		return $this->getOpt( 'log_level_db', [] );
	}

	/**
	 * Don't put cap "activity_logs_unlimited" into cfg as this option is always available, but limited to 7.
	 */
	public function getAutoCleanDays() :int {
		$days = $this->getOpt( 'audit_trail_auto_clean' );
		if ( $days > $this->getOptDefault( 'audit_trail_auto_clean' ) && !$this->con()->caps->canActivityLogsUnlimited() ) {
			$this->resetOptToDefault( 'audit_trail_auto_clean' );
		}
		return (int)$this->getOpt( 'audit_trail_auto_clean' );
	}

	public function isLogToDB() :bool {
		return !\in_array( 'disabled', $this->getLogLevelsDB() );
	}

	/**
	 * @deprecated 18.2
	 */
	public function isLogToFile() :bool {
		return !\in_array( 'disabled', $this->getLogLevelsFile() ) && !empty( $this->getLogFilePath() );
	}

	/**
	 * @deprecated 18.2
	 */
	public function getLogLevelsFile() :array {
		$levels = $this->getOpt( 'log_level_file', [] );
		if ( empty( $levels ) ) {
			$this->resetOptToDefault( 'log_level_file' );
		}
		elseif ( \count( $levels ) > 1 ) {
			if ( \in_array( 'disabled', $levels ) ) {
				$this->setOpt( 'log_level_file', [ 'disabled' ] );
			}
			elseif ( \in_array( 'same_as_db', $levels ) ) {
				$this->setOpt( 'log_level_file', [ 'same_as_db' ] );
			}
		}
		$levels = $this->getOpt( 'log_level_file', [] );
		return \in_array( 'same_as_db', $levels ) ? $this->getLogLevelsDB() : $levels;
	}
}