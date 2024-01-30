<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class PluginLabels {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function run() {
		add_filter( 'all_plugins', [ $this, 'applyLabels' ] );
	}

	public function applyLabels( $plugins ) {
		$con = self::con();
		$plugins[ $con->base_file ] = \array_merge( $plugins[ $con->base_file ] ?? [], $con->labels->getRawData() );
		return $plugins;
	}
}