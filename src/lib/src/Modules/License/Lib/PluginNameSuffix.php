<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Labels;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class PluginNameSuffix {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		$con = self::con();
		return (bool)apply_filters( 'shield/add_pro_suffix',
			$con->isPremiumActive() && !$con->getModule_SecAdmin()->getWhiteLabelController()->isEnabled() );
	}

	protected function run() {
		add_filter( self::con()->prefix( 'labels' ), function ( Labels $labels ) {
			$labels->Name = 'ShieldPRO';
			$labels->Title = 'ShieldPRO';
			$labels->MenuTitle = 'ShieldPRO';
			return $labels;
		} );
	}
}
