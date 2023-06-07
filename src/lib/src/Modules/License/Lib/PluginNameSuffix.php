<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Labels;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;

class PluginNameSuffix extends Modules\Base\Common\ExecOnceModConsumer {

	protected function canRun() :bool {
		$con = $this->con();
		return (bool)apply_filters( 'shield/add_pro_suffix',
			$con->isPremiumActive() && !$con->getModule_SecAdmin()->getWhiteLabelController()->isEnabled() );
	}

	protected function run() {
		add_filter( $this->con()->prefix( 'labels' ), function ( Labels $labels ) {
			$labels->Name = 'ShieldPRO';
			$labels->Title = 'ShieldPRO';
			$labels->MenuTitle = 'ShieldPRO';
			return $labels;
		} );
	}
}
