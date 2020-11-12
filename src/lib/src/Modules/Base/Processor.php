<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Utilities\Logic\OneTimeExecute;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;

abstract class Processor {

	use Modules\ModConsumer;
	use Shield\Crons\PluginCronsConsumer;
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

	/**
	 * @deprecated 10.1
	 */
	public function deactivatePlugin() {
	}

	/**
	 * @var BaseProcessor[]
	 * @deprecated 10.1
	 */
	protected $aSubPros;

	/**
	 * @deprecated 10.1
	 */
	public function onWpEnqueueJs() {
	}

	/**
	 * @return Modules\Email\Processor
	 * @deprecated 10.1
	 */
	public function getEmailProcessor() {
		return $this->getMod()->getEmailProcessor();
	}
}