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
		$crons = Services::WpCron()->getCrons();

		$pattern = sprintf( '#^(%s|%s)#', $this->con()->getParentSlug(), $this->con()->getPluginSlug() );
		foreach ( $crons as $cron ) {
			if ( \is_array( $crons ) ) {
				foreach ( $cron as $key => $cronEntry ) {
					if ( \is_string( $key ) && \preg_match( $pattern, $key ) ) {
						Services::WpCron()->deleteCronJob( $key );
					}
				}
			}
		}
	}
}