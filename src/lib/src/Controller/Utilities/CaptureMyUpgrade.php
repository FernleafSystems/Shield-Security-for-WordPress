<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Utilities;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield;

class CaptureMyUpgrade {

	use Shield\Modules\PluginControllerConsumer;
	use ExecOnce;

	protected function run() {
		add_action( 'upgrader_process_complete', [ $this, 'captureMyUpgrade' ], 10, 2 );
	}

	private function captureMyUpgrade( $upgradeHandler, $data ) {
		if ( ( $data[ 'action' ] ?? null === 'update' )
			 && ( $data[ 'type' ] ?? null === 'plugin' )
			 && is_array( $data[ 'plugins' ] ?? null ) ) {
			foreach ( $data[ 'plugins' ] as $item ) {
				if ( $item === $this->getCon()->root_file ) {
					$this->getCon()->is_my_upgrade = true;
					break;
				}
			}
		}
	}
}