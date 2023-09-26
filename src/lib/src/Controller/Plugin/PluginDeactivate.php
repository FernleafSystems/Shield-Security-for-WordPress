<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class PluginDeactivate {

	use Shield\Modules\PluginControllerConsumer;
	use ExecOnce;

	protected function run() {
		$this->modDeactivate();
		$this->deleteCrons();
	}

	private function modDeactivate() {
		// 1. Clean out the scanners
		foreach ( self::con()->modules as $mod ) {
			$mod->onPluginDeactivate();
		}
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