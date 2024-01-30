<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Updates;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield;

class CaptureMyUpgrade {

	use Shield\Modules\PluginControllerConsumer;
	use ExecOnce;

	protected function run() {
		add_filter( 'upgrader_post_install', [ $this, 'captureMyInstall' ], 10, 2 );
		add_action( 'upgrader_process_complete', [ $this, 'captureMyUpgrade' ], 10, 2 );
	}

	public function captureMyInstall( $true, $hooksExtra ) {
		if ( !empty( $hooksExtra[ 'plugin' ] ) && $hooksExtra[ 'plugin' ] === self::con()->base_file ) {
			self::con()->is_my_upgrade = true;
		}
		return $true;
	}

	public function captureMyUpgrade( $upgradeHandler, $data ) {
		if ( ( $data[ 'action' ] ?? null === 'update' )
			 && ( $data[ 'type' ] ?? null === 'plugin' )
			 && \is_array( $data[ 'plugins' ] ?? null )
		) {
			foreach ( $data[ 'plugins' ] as $item ) {
				if ( $item === self::con()->root_file ) {
					self::con()->is_my_upgrade = true;
					break;
				}
			}
		}
	}
}