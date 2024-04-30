<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Updates;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CaptureFirstDetected {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function run() {
		add_filter( 'site_transient_update_plugins', [ $this, 'capture' ] );
	}

	public function capture( $updates ) {
		$con = self::con();
		$file = $con->base_file;
		if ( \is_object( $updates ) ) {
			$new = $updates->response[ $file ]->new_version ?? '';
			if ( !empty( $new ) ) {
				$con->cfg->update_first_detected = \array_slice(
					\array_merge( [
						$new => Services::Request()->ts()
					], $con->cfg->update_first_detected ),
					-3
				);
			}
		}

		return $updates;
	}
}