<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers\Utility\LogFileDirCreate;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Options extends BaseShield\Options {

	/**
	 * @inheritDoc
	 */
	protected function preSetOptChecks( string $key, $newValue ) {
		if ( $key === 'audit_trail_auto_clean' && $newValue > $this->con()->caps->getMaxLogRetentionDays() ) {
			throw new \Exception( 'Cannot set log retentions days to anything longer than max' );
		}
	}

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
	 */
	public function getAutoCleanDays() :int {
		$days = (int)\min( $this->getOpt( 'audit_trail_auto_clean' ), $this->con()->caps->getMaxLogRetentionDays() );
		$this->setOpt( 'audit_trail_auto_clean', $days );
		return $days;
	}

	public function isLogToDB() :bool {
		return !\in_array( 'disabled', $this->getLogLevelsDB() );
	}
}