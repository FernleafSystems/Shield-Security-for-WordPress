<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class Debug {

	use ModConsumer;

	public function __construct() {
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ] );
		add_action( 'shutdown', [ $this, 'onShutdown' ] );
	}

	public function run() {
	}

	public function onWpLoaded() {
	}

	public function onShutdown() {
	}
}