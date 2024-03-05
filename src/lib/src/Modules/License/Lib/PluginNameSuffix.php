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
			$con->isPremiumActive() && !$con->comps->whitelabel->isEnabled() );
	}

	protected function run() {
		add_filter( self::con()->prefix( 'labels' ), function ( Labels $labels ) {
			$labels->Name = 'ShieldPRO';
			$labels->Title = 'ShieldPRO';
			$labels->MenuTitle = 'ShieldPRO';
			$labels->url_img_logo_small = self::con()->urls->forImage( 'plugin_logo_prem.svg' );
			return $labels;
		} );
	}
}
