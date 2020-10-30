<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Options extends Base\ShieldOptions {

	/**
	 * @return string
	 */
	public function getDbTable_ChangeTracking() {
		return $this->getCon()->prefixOption( $this->getDef( 'table_name_changetracking' ) );
	}

	public function getAutoCleanDays() :int {
		return (int)$this->getOpt( 'audit_trail_auto_clean' );
	}

	public function getMaxEntries() :int {
		return $this->isPremium() ?
			(int)$this->getOpt( 'audit_trail_max_entries' ) :
			(int)$this->getDef( 'audit_trail_free_max_entries' );
	}

	public function isEnabledChangeTracking() :bool {
		return !$this->isOpt( 'enable_change_tracking', 'disabled' );
	}

	/**
	 * @return int
	 */
	public function getCTSnapshotsPerWeek() {
		return (int)$this->getOpt( 'ct_snapshots_per_week', 7 );
	}

	/**
	 * @return int
	 */
	public function getCTMaxSnapshots() {
		return (int)$this->getOpt( 'ct_max_snapshots', 28 );
	}

	/**
	 * @return int
	 */
	public function getCTSnapshotInterval() {
		return WEEK_IN_SECONDS/$this->getCTSnapshotsPerWeek();
	}

	/**
	 * @return int
	 */
	public function getCTLastSnapshotAt() {
		return $this->getOpt( 'ct_last_snapshot_at' );
	}

	/**
	 * @return bool
	 */
	public function isCTSnapshotDue() {
		return ( Services::Request()->ts() - $this->getCTLastSnapshotAt() > $this->getCTSnapshotInterval() );
	}

	public function isEnabledAuditing() :bool {
		return $this->isAuditEmails()
			   || $this->isAuditPlugins()
			   || $this->isAuditThemes()
			   || $this->isAuditPosts()
			   || $this->isAuditShield()
			   || $this->isAuditUsers()
			   || $this->isAuditWp();
	}

	public function isAuditEmails() :bool {
		return $this->isOpt( 'enable_audit_context_emails', 'Y' );
	}

	public function isAuditPlugins() :bool {
		return $this->isOpt( 'enable_audit_context_plugins', 'Y' );
	}

	public function isAuditPosts() :bool {
		return $this->isOpt( 'enable_audit_context_posts', 'Y' );
	}

	public function isAuditShield() :bool {
		return $this->isOpt( 'enable_audit_context_wpsf', 'Y' );
	}

	public function isAuditThemes() :bool {
		return $this->isOpt( 'enable_audit_context_themes', 'Y' );
	}

	public function isAuditUsers() :bool {
		return $this->isOpt( 'enable_audit_context_users', 'Y' );
	}

	public function isAuditWp() :bool {
		return $this->isOpt( 'enable_audit_context_wordpress', 'Y' );
	}

	/**
	 * @return $this
	 */
	public function updateCTLastSnapshotAt() {
		return $this->setOptAt( 'ct_last_snapshot_at' );
	}
}