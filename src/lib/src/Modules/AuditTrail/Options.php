<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Options extends BaseShield\Options {

	public function getLogLevelDB() :string {
		return $this->getOpt( 'log_level_db' );
	}

	public function getLogLevelFile() :string {
		$level = $this->getOpt( 'log_level_file' );
		return $level === 'same_as_db' ? $this->getLogLevelDB() : $level;
	}

	public function getAutoCleanDays() :int {
		return (int)$this->getOpt( 'audit_trail_auto_clean' );
	}

	public function getMaxEntries() :int {
		return $this->isPremium() ?
			(int)$this->getOpt( 'audit_trail_max_entries' ) :
			(int)$this->getDef( 'audit_trail_free_max_entries' );
	}

	public function isLogToFile() :bool {
		return $this->getLogLevelFile() !== 'disabled';
	}
}