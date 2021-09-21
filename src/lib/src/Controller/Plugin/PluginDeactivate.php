<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class PluginDeactivate {

	use Shield\Modules\PluginControllerConsumer;
	use ExecOnce;

	protected function run() {
		$this->deleteCrons();
	}

	private function deleteCrons() {
		$con = $this->getCon();
		$WPCron = Services::WpCron();
		$crons = $WPCron->getCrons();

		$pattern = sprintf( '#^(%s|%s)#', $con->getParentSlug(), $con->getPluginSlug() );
		foreach ( $crons as $cron ) {
			if ( is_array( $crons ) ) {
				foreach ( $cron as $key => $cronEntry ) {
					if ( is_string( $key ) && preg_match( $pattern, $key ) ) {
						$WPCron->deleteCronJob( $key );
					}
				}
			}
		}
	}
}