<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Options extends BaseShield\Options {

	public function getAutoCleanDays() :int {
		return (int)$this->getOpt( 'audit_trail_auto_clean' );
	}

	public function getMaxEntries() :int {
		return $this->isPremium() ?
			(int)$this->getOpt( 'audit_trail_max_entries' ) :
			(int)$this->getDef( 'audit_trail_free_max_entries' );
	}

	/**
	 * @return bool
	 * @deprecated 10.1
	 */
	public function isEnabledAuditing() :bool {
		return true;
	}

	/**
	 * @return bool
	 * @deprecated 10.1
	 */
	public function isAuditEmails() :bool {
		return $this->isOpt( 'enable_audit_context_emails', 'Y' );
	}

	/**
	 * @return bool
	 * @deprecated 10.1
	 */
	public function isAuditPlugins() :bool {
		return $this->isOpt( 'enable_audit_context_plugins', 'Y' );
	}

	/**
	 * @return bool
	 * @deprecated 10.1
	 */
	public function isAuditPosts() :bool {
		return $this->isOpt( 'enable_audit_context_posts', 'Y' );
	}

	/**
	 * @return bool
	 * @deprecated 10.1
	 */
	public function isAuditShield() :bool {
		return $this->isOpt( 'enable_audit_context_wpsf', 'Y' );
	}

	/**
	 * @return bool
	 * @deprecated 10.1
	 */
	public function isAuditThemes() :bool {
		return $this->isOpt( 'enable_audit_context_themes', 'Y' );
	}

	/**
	 * @return bool
	 * @deprecated 10.1
	 */
	public function isAuditUsers() :bool {
		return $this->isOpt( 'enable_audit_context_users', 'Y' );
	}

	/**
	 * @return bool
	 * @deprecated 10.1
	 */
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