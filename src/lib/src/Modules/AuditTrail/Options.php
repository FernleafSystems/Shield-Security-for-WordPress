<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers\Utility\LogFileDirCreate;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Options extends BaseShield\Options {

	public function getLogFilePath() :string {
		try {
			$dir = ( new LogFileDirCreate() )
				->setMod( $this->getMod() )
				->run();
		}
		catch ( \Exception $e ) {
			$dir = '';
		}
		$dir = apply_filters( 'shield/audit_trail_log_file_dir', $dir );
		return empty( $dir ) ? '' :
			apply_filters( 'shield/audit_trail_log_file_dir', path_join( $dir, 'shield.log' ) );
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

	public function getLogLevelsFile() :array {
		$levels = $this->getOpt( 'log_level_file', [] );
		if ( empty( $levels ) || !is_array( $levels ) ) {
			$this->resetOptToDefault( 'log_level_file' );
		}
		elseif ( count( $levels ) > 1 ) {
			if ( in_array( 'disabled', $levels ) ) {
				$this->setOpt( 'log_level_file', [ 'disabled' ] );
			}
			elseif ( in_array( 'same_as_db', $levels ) ) {
				$this->setOpt( 'log_level_file', [ 'same_as_db' ] );
			}
		}
		$levels = $this->getOpt( 'log_level_file', [] );
		return in_array( 'same_as_db', $levels ) ? $this->getLogLevelsDB() : $levels;
	}

	public function getAutoCleanDays() :int {
		$days = $this->getOpt( 'audit_trail_auto_clean' );
		if ( !$this->isPremium() ) {
			$this->setOpt( 'audit_trail_auto_clean', min( $days, 7 ) );
		}
		return (int)$this->getOpt( 'audit_trail_auto_clean' );
	}

	public function isLogToDB() :bool {
		return !in_array( 'disabled', $this->getLogLevelsDB() );
	}

	public function isLogToFile() :bool {
		return !in_array( 'disabled', $this->getLogLevelsFile() )
			   && !empty( $this->getLogFilePath() );
	}

	/**
	 * @deprecated 12.0
	 */
	public function getMaxEntries() :int {
		return PHP_INT_MAX;
	}
}