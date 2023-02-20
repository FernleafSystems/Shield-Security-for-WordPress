<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Upgrade extends Base\Upgrade {

	protected function upgrade_1710() {
		// remove old tables
		$WPDB = Services::WpDb();
		foreach (
			[
				'geoip',
				'reporting',
				'spambot_comments_filter',
				'statistics',
				'ip_lists',
				'sessions'
			] as $table
		) {
			$table = sprintf( '%s%s%s', $WPDB->getPrefix(), $this->getCon()->getOptionStoragePrefix(), $table );
			if ( $WPDB->tableExists( $table ) ) {
				$WPDB->doDropTable( $table );
			}
		}
		$WPDB->clearResultShowTables();
	}
}