<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Updates;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Services;

class HandleUpgrade {

	use Modules\PluginControllerConsumer;
	use ExecOnce;

	protected function canRun() :bool {
		$previous = self::con()->cfg->previous_version;
		return !empty( $previous );
	}

	protected function run() {
		$con = self::con();
		$prev = $con->cfg->previous_version;

		$hook = $con->prefix( 'plugin-upgrade' );
		if ( \version_compare( $prev, $con->cfg->version(), '<' ) && !wp_next_scheduled( $hook, [ $prev ] ) ) {
			$con->getModule_Plugin()->deleteAllPluginCrons();
			wp_schedule_single_event( Services::Request()->ts() + 1, $hook, [ $prev ] );
		}

		add_action( $hook, function ( $previousVersion ) {
			Services::ServiceProviders()->clearProviders();
			foreach ( self::con()->modules as $mod ) {
				$H = $mod->getUpgradeHandler();
				if ( $H instanceof Modules\Base\Upgrade ) {
					$H->setPrevious( $previousVersion )->execute();
				}
			}
		} );

		$con->cfg->previous_version = $con->cfg->version();
	}
}