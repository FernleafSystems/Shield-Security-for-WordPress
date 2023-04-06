<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

abstract class EventsListener {

	use PluginControllerConsumer;

	/**
	 * @var bool
	 */
	private $commit;

	public function __construct( Controller\Controller $con, bool $commit = false ) {
		$this->commit = $commit;

		add_action( 'shield/event', function ( $event, $meta = [], $def = [] ) {
			$this->captureEvent( (string)$event, is_array( $meta ) ? $meta : [], is_array( $def ) ? $def : [] );
		}, 10, 3 );

		add_action( $con->prefix( 'plugin_shutdown' ), function () {
			$this->onShutdown();
		}, 100 );

		$this->init();
	}

	protected function init() {
	}

	abstract protected function captureEvent( string $evt, array $meta = [], array $def = [] );

	protected function onShutdown() {

	}

	public function isCommit() :bool {
		return $this->commit;
	}

	/**
	 * @return $this
	 */
	public function setIfCommit( bool $commit ) {
		$this->commit = $commit;
		return $this;
	}
}