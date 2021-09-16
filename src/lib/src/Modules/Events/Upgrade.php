<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\{
	Base,
	HackGuard
};
use FernleafSystems\Wordpress\Services\Services;

class Upgrade extends Base\Upgrade {

	protected function upgrade_1200() {
		$WPDB = Services::WpDb();
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var HackGuard\Options $optsHG */
		$optsHG = $this->getCon()
					   ->getModule_HackGuard()
					   ->getOptions();
		$scans = $optsHG->getScanSlugs();

		$eventTable = $mod->getDbHandler_Events()->getTableSchema()->table;

		$WPDB->doSql( sprintf( "DELETE FROM `%s` WHERE `event` IN ('%s')",
			$eventTable,
			implode( "','", array_map( function ( $scan ) {
				return $scan.'_alert_sent';
			}, $scans ) )
		) );

		$WPDB->doSql(
			sprintf( "UPDATE `%s` SET `event`='scan_run' WHERE `event` IN ('%s')",
				$eventTable,
				implode( "','", array_map( function ( $scan ) {
					return $scan.'_scan_run';
				}, $scans ) )
			)
		);

		$WPDB->doSql(
			sprintf( "UPDATE `%s` SET `event`='scan_items_found' WHERE `event` IN ('%s')",
				$eventTable,
				implode( "','", array_map( function ( $scan ) {
					return $scan.'_scan_found';
				}, $scans ) )
			)
		);

		$WPDB->doSql(
			sprintf( "UPDATE `%s` SET `event`='firewall_block' WHERE `event` LIKE ('blockparam_%%')", $eventTable )
		);
	}
}