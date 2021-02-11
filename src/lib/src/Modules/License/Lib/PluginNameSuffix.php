<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib;

use FernleafSystems\Utilities\Logic\OneTimeExecute;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

class PluginNameSuffix {

	use Modules\ModConsumer;
	use OneTimeExecute;

	protected function canRun() {
		$con = $this->getCon();
		/** @var SecurityAdmin\Options $optsSecAdmin */
		$optsSecAdmin = $con->getModule_SecAdmin()->getOptions();
		return (bool)apply_filters( 'shield/add_pro_suffix', $con->isPremiumActive() && !$optsSecAdmin->isEnabledWhitelabel() );
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
