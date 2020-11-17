<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Utilities\Logic\OneTimeExecute;
use FernleafSystems\Wordpress\Plugin\Shield;

abstract class Processor {

	use Shield\Crons\PluginCronsConsumer;
	use Shield\Modules\ModConsumer;
	use OneTimeExecute;

	/**
	 * @param ModCon $mod
	 */
	public function __construct( $mod ) {
		$this->setMod( $mod );
		add_action( 'init', [ $this, 'onWpInit' ], 9 );
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ] );
		add_action( $mod->prefix( 'plugin_shutdown' ), [ $this, 'onModuleShutdown' ] );
		$this->setupCronHooks();
	}

	public function onWpInit() {
	}

	public function onWpLoaded() {
	}

	public function onModuleShutdown() {
	}
}