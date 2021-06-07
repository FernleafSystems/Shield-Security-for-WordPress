<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;

class PluginNameSuffix {

	use Modules\ModConsumer;
	use ExecOnce;

	protected function canRun() :bool {
		$con = $this->getCon();
		return (bool)apply_filters( 'shield/add_pro_suffix',
			$con->isPremiumActive() && !$con->getModule_SecAdmin()->getWhiteLabelController()->isEnabled() );
	}

	protected function run() {
		add_filter( $this->getCon()->prefix( 'plugin_labels' ), function ( $labels ) {
			$labels[ 'Name' ] = 'ShieldPRO';
			$labels[ 'Title' ] = 'ShieldPRO';
			$labels[ 'MenuTitle' ] = 'ShieldPRO';
			return $labels;
		} );
	}
}
