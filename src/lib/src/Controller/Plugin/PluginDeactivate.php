<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class PluginDeactivate {

	use PluginControllerConsumer;

	public function run() {
		do_action( self::con()->prefix( 'deactivate_plugin' ) );
		$this->purgeScans();
		$this->deleteCrons();
	}

	private function purgeScans() {
		// 1. Clean out the scanners
		foreach ( self::con()->comps->scans->getAllScanCons() as $scanCon ) {
			$scanCon->purge();
		}
		self::con()->db_con->scan_items->tableDelete();
		self::con()->db_con->scan_results->tableDelete();
		self::con()->comps->file_locker->purge();
	}

	private function deleteCrons() {
		$cfg = self::con()->cfg;
		$pattern = sprintf( '#^(%s|%s)#', $cfg->properties[ 'slug_parent' ], $cfg->properties[ 'slug_plugin' ] );
		foreach ( Services::WpCron()->getCrons() as $cron ) {
			foreach ( $cron as $key => $cronEntry ) {
				if ( \is_string( $key ) && \preg_match( $pattern, $key ) ) {
					Services::WpCron()->deleteCronJob( $key );
				}
			}
		}
	}
}