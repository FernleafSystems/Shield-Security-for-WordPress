<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

use FernleafSystems\Wordpress\Services\Services;

class Database extends Base {

	protected function run() {
		add_action( 'wp_loaded', [ $this, 'auditDbTables' ] );
	}

	public function auditDbTables() {
		// TODO: snapshot
		Services::WpDb()->showTables();
	}
}