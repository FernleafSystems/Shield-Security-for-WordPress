<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Upgrade extends Base\Upgrade {

	protected function upgradeCommon() {
		parent::upgradeCommon();
		$SP = Services::ServiceProviders();
		if ( \method_exists( $SP, 'clearProviders' ) ) {
			$SP->clearProviders();
		}
	}

	protected function upgrade_1834() {
		// remove old tables
		$WPDB = Services::WpDb();
		foreach (
			[
				'geoip',
				'reporting',
				'spambot_comments_filter',
				'statistics',
				'ip_lists',
				'notes',
				'report',
				'sessions'
			] as $table
		) {
			$table = sprintf( '%s%s%s', $WPDB->getPrefix(), self::con()->getOptionStoragePrefix(), $table );
			if ( $WPDB->tableExists( $table ) ) {
				$WPDB->doDropTable( $table );
			}
		}

		foreach (
			[
				'icwp_wpsf_reporting_options',
				'icwp_wpsf_sessions_options',
				'icwp_wpsf_install_id',
			] as $opt
		) {
			Services::WpGeneral()->deleteOption( $opt );
		}

		$WPDB->clearResultShowTables();
	}
}