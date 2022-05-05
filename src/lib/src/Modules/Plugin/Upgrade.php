<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Upgrade extends Base\Upgrade {

	protected function runEveryUpgrade() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$mod->deleteAllPluginCrons();
	}

	protected function upgrade_1200() {
		// remove old tables that have somehow been missed in the past.
		$WPDB = Services::WpDb();
		foreach ( [ 'geoip', 'reporting', 'spambot_comments_filter', 'statistics', ] as $table ) {
			$table = sprintf( '%s%s%s', $WPDB->getPrefix(), $this->getCon()->getOptionStoragePrefix(), $table );
			if ( $WPDB->tableExists( $table ) ) {
				$WPDB->doDropTable( $table );
			}
		}
		$WPDB->clearResultShowTables();
	}
}