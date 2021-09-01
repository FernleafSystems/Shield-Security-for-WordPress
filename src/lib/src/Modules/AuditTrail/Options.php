<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Options extends BaseShield\Options {

	public function getLogFilePath() :string {
		return apply_filters(
			'shield/audit_trail_log_file_path',
			$this->getCon()->getPluginCachePath( 'logs/shield.log' )
		);
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
		return (int)$this->getOpt( 'audit_trail_auto_clean' );
	}

	public function getMaxEntries() :int {
		return $this->isPremium() ?
			(int)$this->getOpt( 'audit_trail_max_entries' ) :
			(int)$this->getDef( 'audit_trail_free_max_entries' );
	}

	public function isLogToDB() :bool {
		return !in_array( 'disabled', $this->getLogLevelsDB() );
	}

	public function isLogToFile() :bool {
		return !in_array( 'disabled', $this->getLogLevelsFile() );
	}
}